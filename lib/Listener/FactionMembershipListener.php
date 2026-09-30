<?php

/**
 * FactionMembershipListener for Larpinq
 *
 * Server-side veto on player writes to factions, memberships and
 * relationships. Listens for OpenRegister's vetoable pre-write events
 * (ObjectCreatingEvent / ObjectUpdatingEvent), scoped to the faction,
 * faction member and relationship schemas, and refuses what a row rule cannot
 * see: the status a membership moves to, and whether the caller leads the
 * group or owns the character.
 *
 * @category  Listener
 * @package   OCA\Larpinq\Listener
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-connections/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\CharacterConnectionGuard;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Vetoes player writes that break the faction, membership or relationship rules.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
 *
 * @psalm-suppress UndefinedClass OpenRegister event classes are an optional dependency.
 *
 * @spec openspec/specs/character-connections/spec.md
 */
class FactionMembershipListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param CharacterConnectionGuard $guard The rules.
	 * @param IAppConfig $config Config (schema id resolution).
	 * @param IUserSession $userSession The current user session.
	 * @param IGroupManager $groupManager The group manager (game master check).
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly CharacterConnectionGuard $guard,
		private readonly IAppConfig $config,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle an OpenRegister pre-write event.
	 *
	 * @param Event $event The dispatched event (Creating or Updating).
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedArgument    OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress UndefinedMethod  The OpenRegister event accessors are resolved at runtime.
	 *
	 * @spec openspec/specs/character-connections/spec.md
	 */
	public function handle(Event $event): void {
		if (($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) === false
			&& ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) === false
		) {
			return;
		}

		try {
			$errors = $this->collectVeto(event: $event);
		} catch (\Throwable $e) {
			// An authorization check does not fail open: a guard that throws
			// refuses the write and says why in the log.
			$this->logger->error('Larpinq: faction and relationship check errored; refusing the write.', ['exception' => $e]);
			$errors = [
				'code' => 'connection_unverifiable',
				'message' => 'The faction or character of this write could not be read, so the write is refused.',
			];
		}

		if ($errors !== null) {
			$event->stopPropagation();
			// @phpstan-ignore-next-line
			$event->setErrors($errors);
		}
	}//end handle()

	/**
	 * The veto payload for a write, or null when it may pass.
	 *
	 * @param Event $event The Creating or Updating event.
	 *
	 * @return array<string, string>|null The errors payload, or null to allow.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
	 */
	private function collectVeto(Event $event): ?array {
		[$entity, $old] = $this->entitiesOf(event: $event);
		$kind = $this->kindOf(entity: $entity);
		if ($kind === null) {
			return null;
		}

		// A write without a signed-in user is the app itself (seeding, import
		// from the command line); OpenRegister's schema rules already decide
		// who may write through the API.
		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}

		$userId = $user->getUID();
		if ($this->groupManager->isAdmin($userId) === true
			|| $this->groupManager->isInGroup($userId, Application::GM_GROUP) === true
		) {
			return null;
		}

		$data = (array)$entity->getObject();
		if ($kind === 'faction') {
			return $this->guard->checkFaction(faction: $data, userId: $userId);
		}

		if ($kind === 'relationship') {
			return $this->guard->checkRelationship(relationship: $data, userId: $userId);
		}

		$previous = null;
		if ($old !== null) {
			$previous = (array)$old->getObject();
		}

		return $this->guard->checkMembership(membership: $data, old: $previous, userId: $userId);
	}//end collectVeto()

	/**
	 * Which of the three schemas the entity belongs to, or null for any other.
	 *
	 * The probe is `is_callable()`: `getSchema()` is a magic getter on the
	 * real ObjectEntity, which `method_exists()` does not see (larpinq#308).
	 *
	 * @param object $entity The object entity.
	 *
	 * @return string|null 'faction', 'factionmember', 'relationship' or null.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
	 */
	private function kindOf(object $entity): ?string {
		if (is_callable([$entity, 'getSchema']) === false) {
			return null;
		}

		$schema = (string)$entity->getSchema();
		if ($schema === '') {
			return null;
		}

		foreach (['faction', 'factionmember', 'relationship'] as $kind) {
			if ($schema === $this->config->getValueString(Application::APP_ID, $kind . '_schema', '')) {
				return $kind;
			}
		}

		return null;
	}//end kindOf()

	/**
	 * The new entity and the stored one (null on a create) of a pre-write event.
	 * ObjectUpdatingEvent has no getObject(), so the accessor follows the class.
	 *
	 * @param Event $event The Creating or Updating event.
	 *
	 * @return array{0:object,1:object|null} The new and the old entity.
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event classes are optional dependencies.
	 * @psalm-suppress UndefinedMethod  The OpenRegister event accessors are resolved at runtime.
	 */
	private function entitiesOf(Event $event): array {
		if ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) {
			// @phpstan-ignore-next-line
			return [$event->getNewObject(), $event->getOldObject()];
		}

		// @phpstan-ignore-next-line
		return [$event->getObject(), null];
	}//end entitiesOf()
}//end class
