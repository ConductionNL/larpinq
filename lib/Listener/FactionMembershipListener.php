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

use OCA\Larpinq\Support\AppIdentity;
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
	 * The schemas this listener checks, in the order their ids are compared.
	 *
	 * @var string[]
	 */
	private const KINDS = ['faction', 'factionmember', 'relationship', 'characterbuild'];

	/**
	 * The refusal when the guard could not read the write.
	 *
	 * @var array<string,string>
	 */
	private const UNVERIFIABLE = [
		'code' => 'connection_unverifiable',
		'message' => 'The faction or character of this write could not be read, so the write is refused.',
	];

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
		if ($this->isPreWrite(event: $event) === false) {
			return;
		}

		try {
			$errors = $this->collectVeto(event: $event);
		} catch (\Throwable $e) {
			// An authorization check does not fail open: a guard that throws
			// refuses the write and says why in the log.
			$this->logger->error('Larpinq: faction and relationship check errored; refusing the write.', ['exception' => $e]);
			$errors = self::UNVERIFIABLE;
		}

		PreWriteEvent::refuseOnErrors(event: $event, errors: $errors);
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
		[$entity, $old] = PreWriteEvent::entitiesOf(event: $event);
		$kind = $this->kindOf(entity: $entity);
		if ($kind === null) {
			return null;
		}

		$userId = $this->restrictedUserId();
		if ($userId === null) {
			return null;
		}

		return $this->verdict(kind: $kind, write: [(array)$entity->getObject(), $old, $userId]);
	}//end collectVeto()

	/**
	 * Whether the event is one of OpenRegister's vetoable pre-write events.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return bool True for ObjectCreatingEvent and ObjectUpdatingEvent.
	 *
	 * @psalm-assert-if-true \OCA\OpenRegister\Event\ObjectCreatingEvent|\OCA\OpenRegister\Event\ObjectUpdatingEvent $event
	 */
	private function isPreWrite(Event $event): bool {
		return $event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent
			|| $event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent;
	}//end isPreWrite()

	/**
	 * The signed-in user whose writes the guard checks, or null when none is checked.
	 *
	 * A write without a signed-in user is the app itself (seeding, import
	 * from the command line); OpenRegister's schema rules already decide
	 * who may write through the API. Administrators and game masters are
	 * not checked either.
	 *
	 * @return string|null The user id, or null.
	 */
	private function restrictedUserId(): ?string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}

		$userId = $user->getUID();
		if ($this->groupManager->isAdmin($userId) === true
			|| $this->groupManager->isInGroup($userId, AppIdentity::GM_GROUP) === true
		) {
			return null;
		}

		return $userId;
	}//end restrictedUserId()

	/**
	 * The stored object of an update, or null on a create.
	 *
	 * @param object|null $old The stored entity.
	 *
	 * @return array<string,mixed>|null The stored data.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
	 */
	private function storedData(?object $old): ?array {
		if ($old === null) {
			return null;
		}

		return (array)$old->getObject();
	}//end storedData()

	/**
	 * The guard verdict for one write of a kind.
	 *
	 * Factions and relationships are judged on the new data alone; memberships
	 * and builds compare it with the stored object, which is read only for them.
	 *
	 * @param string                                     $kind  The kind kindOf() named.
	 * @param array{0:array<string,mixed>,1:object|null,2:string} $write The new data, the stored entity (null on a create) and the user id.
	 *
	 * @return array<string, string>|null The errors payload, or null to allow.
	 */
	private function verdict(string $kind, array $write): ?array {
		[$data, $old, $userId] = $write;
		if ($kind === 'faction') {
			return $this->guard->checkFaction(faction: $data, userId: $userId);
		}

		if ($kind === 'relationship') {
			return $this->guard->checkRelationship(relationship: $data, userId: $userId);
		}

		if ($kind === 'characterbuild') {
			return $this->guard->checkBuild(build: $data, old: $this->storedData(old: $old), userId: $userId);
		}

		return $this->guard->checkMembership(membership: $data, old: $this->storedData(old: $old), userId: $userId);
	}//end verdict()

	/**
	 * Which of the three schemas the entity belongs to, or null for any other.
	 *
	 * The probe is `is_callable()`: `getSchema()` is a magic getter on the
	 * real ObjectEntity, which `method_exists()` does not see (larpinq#308).
	 *
	 * @param object $entity The object entity.
	 *
	 * @return string|null 'faction', 'factionmember', 'relationship', 'characterbuild' or null.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
	 */
	private function kindOf(object $entity): ?string {
		if (is_callable([$entity, 'getSchema']) === false) {
			return null;
		}

		return $this->kindForSchema(schema: (string)$entity->getSchema());
	}//end kindOf()

	/**
	 * The kind whose configured schema id is this one, or null.
	 *
	 * @param string $schema The schema id; '' matches nothing.
	 *
	 * @return string|null The kind.
	 */
	private function kindForSchema(string $schema): ?string {
		if ($schema === '') {
			return null;
		}

		foreach (self::KINDS as $kind) {
			if ($this->config->getValueString(AppIdentity::APP_ID, $kind . '_schema', '') === $schema) {
				return $kind;
			}
		}

		return null;
	}//end kindForSchema()
}//end class
