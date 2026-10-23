<?php

/**
 * XP awards controller for Larpinq.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\XpAwardBatchService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Award XP to a whole event in one save (events-xp-batch-award). Every
 * method is for game masters (the `Application::GM_GROUP` group, or a
 * Nextcloud admin); the awards themselves are written through OpenRegister,
 * whose xpAward rules apply as well.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
class XpAwardsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param XpAwardBatchService $batch The batch award.
	 * @param EventRosterService $rosterService The event roster (event lookup).
	 * @param IUserSession $userSession The user session.
	 * @param IGroupManager $groupManager The group manager.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly XpAwardBatchService $batch,
		private readonly EventRosterService $rosterService,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Whether the signed-in user may award XP; the event page shows its
	 * "Award XP" action on this answer.
	 *
	 * @return JSONResponse `{allowed: bool}`, or 401.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	#[NoAdminRequired]
	public function access(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(data: ['allowed' => $this->isGameMaster(uid: $user->getUID())]);
	}//end access()

	/**
	 * The award roster of an event: participants, attendance, existing awards
	 * and the default ticks. Game masters only.
	 *
	 * @param string $id The event UUID.
	 *
	 * @return JSONResponse `{eventName, rows, attendanceAvailable}`, or 401, 403, 404.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	#[NoAdminRequired]
	public function roster(string $id): JSONResponse {
		[, $denied] = $this->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		$event = $this->rosterService->getEvent(eventId: $id);
		if ($event === null) {
			return new JSONResponse(data: ['error' => 'Event not found'], statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(data: ['eventName' => (string)($event['name'] ?? '')] + $this->batch->awardRoster(eventId: $id));
	}//end roster()

	/**
	 * Create one award per row. Game masters only.
	 *
	 * @param string $id The event UUID.
	 * @param array<int,mixed> $rows The rows: {character, amount, reason?, extra?}.
	 *
	 * @return JSONResponse `{created, refused}`, or 400, 401, 403, 404.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	#[NoAdminRequired]
	public function award(string $id, array $rows = []): JSONResponse {
		[$actingUid, $denied] = $this->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		if ($rows === []) {
			return new JSONResponse(data: ['error' => 'No rows to award'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		if ($this->rosterService->getEvent(eventId: $id) === null) {
			return new JSONResponse(data: ['error' => 'Event not found'], statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(data: $this->batch->award(eventId: $id, rows: $rows, actingUid: $actingUid));
	}//end award()

	/**
	 * Resolve the acting game master, or the refusal response to return.
	 *
	 * @return array{0: string, 1: JSONResponse|null} The [actingUid, refusal] pair.
	 */
	private function resolveGameMaster(): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return ['', new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED)];
		}

		$uid = $user->getUID();
		if ($this->isGameMaster(uid: $uid) === false) {
			return ['', new JSONResponse(data: ['error' => 'Access denied'], statusCode: Http::STATUS_FORBIDDEN)];
		}

		return [$uid, null];
	}//end resolveGameMaster()

	/**
	 * Whether a user is a game master (the group, or a Nextcloud admin).
	 *
	 * @param string $uid The user id.
	 *
	 * @return bool True for a game master.
	 */
	private function isGameMaster(string $uid): bool {
		return $this->groupManager->isInGroup($uid, Application::GM_GROUP) === true
			|| $this->groupManager->isAdmin($uid) === true;
	}//end isGameMaster()
}//end class
