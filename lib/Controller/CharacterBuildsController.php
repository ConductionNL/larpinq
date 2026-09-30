<?php

/**
 * Character builds controller for Larpinq
 *
 * Checks a build of a character against the skill requirements and the XP
 * budget without writing anything, and tells the build page whether the
 * caller may apply builds. A separate controller keeps CharactersController's
 * constructor (and every test that builds it) unchanged.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-builds/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\CharacterStatsPresenter;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * GET /api/builds/{id}/report and GET /api/builds/apply-access.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-builds/spec.md
 */
class CharacterBuildsController extends Controller {

	/**
	 * The character lists a build replaces, with the object type of each.
	 *
	 * @var array<string, string>
	 */
	public const LISTS = ['skills' => 'skill', 'items' => 'item', 'conditions' => 'condition'];

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param RegisterObjectFetcher $objectFetcher Reads the build and its character as the calling user.
	 * @param CharacterService $characterService The stat engine.
	 * @param SkillRequirementService $requirementService The requirement and XP budget check.
	 * @param CharacterStatsPresenter $presenter Shapes the stat sheet of the build.
	 * @param IUserSession $userSession The current user session.
	 * @param IGroupManager $groupManager The group manager (game master check).
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly CharacterService $characterService,
		private readonly SkillRequirementService $requirementService,
		private readonly CharacterStatsPresenter $presenter,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The check of one build: the requirement report and the stat sheet of the
	 * character as it would be with the build's skills, items and conditions.
	 * The character's own XP awards count, so the budget is real. Nothing is
	 * written.
	 *
	 * @param string $id The build UUID.
	 *
	 * @return JSONResponse `{build, character, report, stats, lists, changes}`, or 404.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @SuppressWarnings(PHPMD.ShortVariable)
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 *
	 * @no-admin-idor-exempt OR-delegated reads via
	 * RegisterObjectFetcher::getObject (ADR-022), which reads with RBAC on as
	 * the calling user. A build the caller may not read (the build's rules
	 * admit game masters and the character's player only), or whose character
	 * the caller may not read, raises an exception this method answers with
	 * 404, so an out-of-scope id is indistinguishable from a missing one.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function report(string $id): JSONResponse {
		try {
			$build = $this->objectFetcher->getObject(objectType: 'characterbuild', id: $id);
			$character = $this->objectFetcher->getObject(objectType: 'character', id: $this->idOf(value: ($build['character'] ?? null)));
		} catch (\Exception $exception) {
			return new JSONResponse(data: ['error' => 'Build not found'], statusCode: Http::STATUS_NOT_FOUND);
		}

		$candidate = $this->candidate(character: $character, build: $build);
		$stats = (array)($this->characterService->calculateCharacter(character: $candidate)['stats'] ?? []);

		return new JSONResponse(
			data: [
				'build' => ['id' => $id, 'name' => (string)($build['name'] ?? '')],
				'character' => ['id' => (string)($character['id'] ?? ''), 'name' => (string)($character['name'] ?? '')],
				'report' => $this->requirementService->validate(candidate: $candidate, oldCharacter: $character),
				'lists' => array_intersect_key($candidate, self::LISTS),
				'changes' => $this->changes(character: $character, candidate: $candidate),
				'stats' => $this->presenter->present(stats: $stats, xpAbilityId: $this->requirementService->resolveXpAbility(stats: $stats)),
			]
		);
	}//end report()

	/**
	 * Whether the caller may apply builds to character sheets: game masters
	 * and admins. The build page shows its Apply action on this answer; the
	 * write itself is checked by OpenRegister and the requirement listener.
	 *
	 * @return JSONResponse `{allowed: bool}`.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function access(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['allowed' => false]);
		}

		$uid = $user->getUID();
		$allowed = $this->groupManager->isInGroup($uid, Application::GM_GROUP) === true
			|| $this->groupManager->isAdmin($uid) === true;

		return new JSONResponse(data: ['allowed' => $allowed]);
	}//end access()

	/**
	 * What applying the build adds to and removes from each list, with names,
	 * so the Apply dialog can show it before the write.
	 *
	 * @param array<string, mixed> $character The character.
	 * @param array<string, mixed> $candidate The character with the build's lists.
	 *
	 * @return array<string, array{added: array<int, array{id: string, name: string}>, removed: array<int, array{id: string, name: string}>}> Per list.
	 */
	private function changes(array $character, array $candidate): array {
		$changes = [];
		foreach (self::LISTS as $list => $objectType) {
			$now = array_map(fn (mixed $value): string => $this->idOf(value: $value), (array)($character[$list] ?? []));
			$names = [];
			foreach ($this->objectFetcher->getObjects(objectType: $objectType) as $object) {
				$names[(string)($object['id'] ?? '')] = (string)($object['name'] ?? '');
			}

			$named = static fn (string $uuid): array => ['id' => $uuid, 'name' => ($names[$uuid] ?? $uuid)];
			$changes[$list] = [
				'added' => array_map($named, array_values(array_diff($candidate[$list], $now))),
				'removed' => array_map($named, array_values(array_diff($now, $candidate[$list]))),
			];
		}

		return $changes;
	}//end changes()

	/**
	 * The character with the build's skills, items and conditions in place of
	 * its own.
	 *
	 * @param array<string, mixed> $character The character.
	 * @param array<string, mixed> $build The build.
	 *
	 * @return array<string, mixed> The candidate character.
	 */
	private function candidate(array $character, array $build): array {
		foreach (array_keys(self::LISTS) as $list) {
			$ids = [];
			foreach ((array)($build[$list] ?? []) as $value) {
				$ids[] = $this->idOf(value: $value);
			}

			$character[$list] = array_values(array_filter($ids, static fn (string $uuid): bool => $uuid !== ''));
		}

		return $character;
	}//end candidate()

	/**
	 * The UUID of a relation value, which OpenRegister may hand over as a
	 * string or as an object with an id.
	 *
	 * @param mixed $value The relation value.
	 *
	 * @return string The UUID, or ''.
	 */
	private function idOf(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? $value['uuid'] ?? '');
		}

		if (is_string($value) === false) {
			return '';
		}

		return $value;
	}//end idOf()
}//end class
