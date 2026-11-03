<?php

/**
 * CharacterRequirementListener for Larpinq
 *
 * Server-authoritative veto on character-assignment writes. Listens for
 * OpenRegister's vetoable pre-write events (ObjectCreatingEvent /
 * ObjectUpdatingEvent), scoped to the character schema, and rejects writes
 * whose newly-added skills have unmet prerequisites or that drive the XP
 * budget below zero — unless covered by an explicit GM override.
 *
 * Covers every write path (SPA, REST, GraphQL) because OpenRegister dispatches
 * these events from its central write path.
 *
 * @category  Listener
 * @package   OCA\Larpinq\Listener
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\Service\CustomFieldGuard;
use OCA\Larpinq\Service\SkillRequirementService;
use OCA\Larpinq\Support\AppIdentity;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Vetoes character writes that violate skill requirements or the XP budget.
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
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */
class CharacterRequirementListener implements IEventListener {

	/**
	 * The GM group whose members may author requirement overrides.
	 *
	 * @var string
	 */
	private const GM_GROUP = 'gamemasters';

	/**
	 * The log line for a validator that threw; the write goes through.
	 *
	 * @var string
	 */
	private const VALIDATION_ERRORED = 'Larpinq: skill-requirement validation errored; allowing the write (data-only fallback).';

	/**
	 * The log line for an override check that threw; the write is refused.
	 *
	 * @var string
	 */
	private const OVERRIDE_CHECK_ERRORED = 'Larpinq: requirement override check errored; refusing the write.';

	/**
	 * The error when the override check could not decide.
	 *
	 * @var array<string,string>
	 */
	private const OVERRIDE_UNVERIFIABLE = [
		'code' => 'override_unverifiable',
		'message' => 'The requirement overrides of this write could not be checked, so the write is refused.',
	];

	/**
	 * The error for an override entry without a reason.
	 *
	 * @var array<string,string>
	 */
	private const OVERRIDE_REASON_REQUIRED = [
		'code' => 'override_reason_required',
		'message' => 'A requirement override must carry a non-empty reason.',
	];

	/**
	 * The error for an override authored by someone who is not a game master.
	 *
	 * @var array<string,string>
	 */
	private const OVERRIDE_FORBIDDEN = [
		'code' => 'override_forbidden',
		'message' => 'Only game masters may author requirement overrides.',
	];

	/**
	 * Constructor for CharacterRequirementListener.
	 *
	 * @param SkillRequirementService $requirementService The validation service.
	 * @param IAppConfig $config Config (schema id resolution).
	 * @param IUserSession $userSession The current user session.
	 * @param IGroupManager $groupManager The group manager (GM override check).
	 * @param LoggerInterface $logger The logger.
	 * @param CustomFieldGuard $customFields Checks extra field values against their definitions.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly SkillRequirementService $requirementService,
		private readonly IAppConfig $config,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
		private readonly CustomFieldGuard $customFields,
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
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 * @spec openspec/specs/character-custom-fields/spec.md
	 */
	public function handle(Event $event): void {
		if ($this->isPreWrite(event: $event) === false) {
			return;
		}

		// At this point $event is a vetoable OpenRegister pre-write event.
		// We call its methods via the generic Event reference because both
		// ObjectCreatingEvent and ObjectUpdatingEvent are optional runtime
		// dependencies (OpenRegister may be absent).  Static analysis does not
		// know about these optional classes; see the ignore annotations below.
		// Who may author overrides is decided first and fails closed; only the
		// game rules after it degrade to allowing the write.
		$errors = ($this->overrideVeto(event: $event) ?? $this->gameRuleVeto(event: $event));
		if ($errors !== null) {
			$event->stopPropagation();
			// @phpstan-ignore-next-line
			$event->setErrors($errors);
		}
	}//end handle()

	/**
	 * The override refusal for a character write, or null when its overrides may pass.
	 *
	 * Runs outside the game-rule fallback: when the check cannot decide on a
	 * character write, the write is refused.
	 *
	 * @param Event $event The dispatched pre-write event.
	 *
	 * @return array<string,mixed>|null The errors payload, or null.
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedArgument    OpenRegister event/entity classes are optional dependencies.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	private function overrideVeto(Event $event): ?array {
		$entities = $this->characterEntities(event: $event);
		if ($entities === null) {
			return null;
		}

		[$newEntity, $oldEntity] = $entities;
		try {
			return $this->checkOverrideAuthorization(candidate: $newEntity->getObject(), old: ($oldEntity?->getObject() ?? []));
		} catch (\Throwable $e) {
			$this->logger->error(self::OVERRIDE_CHECK_ERRORED, ['exception' => $e]);
			return ['requirementOverrides' => [self::OVERRIDE_UNVERIFIABLE]];
		}
	}//end overrideVeto()

	/**
	 * The new and stored entity of a character write, or null for any other write.
	 *
	 * When the schema cannot be read the write is not treated as a character
	 * write here; the game-rule step meets the same failure, logs it and
	 * lets the write through, as for any other schema.
	 *
	 * @param Event $event The dispatched pre-write event.
	 *
	 * @return array{0: object, 1: object|null}|null The [newEntity, oldEntity] pair.
	 */
	private function characterEntities(Event $event): ?array {
		try {
			$entities = $this->extractEntities(event: $event);
			if ($this->isCharacterSchema(entity: $entities[0]) === false) {
				return null;
			}

			return $entities;
		} catch (\Throwable $e) {
			return null;
		}
	}//end characterEntities()

	/**
	 * The game-rule errors for a write, or null; a rule that throws allows the write.
	 *
	 * @param Event $event The dispatched pre-write event.
	 *
	 * @return array<string,mixed>|null The errors payload, or null.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	private function gameRuleVeto(Event $event): ?array {
		try {
			return $this->vetoFor(event: $event);
		} catch (\Throwable $e) {
			// A game-rule validator that throws must not brick all character
			// writes. Log and allow the write to proceed (degrade to
			// data-only), the "OR predates events" graceful-degradation
			// contract. Override authorization already ran in overrideVeto().
			$this->logger->error(self::VALIDATION_ERRORED, ['exception' => $e]);
			return null;
		}
	}//end gameRuleVeto()

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
	 * The errors that veto a pre-write event, or null when the write may pass.
	 *
	 * @param Event $event The dispatched pre-write event.
	 *
	 * @return array<string,mixed>|null The errors payload, or null to allow.
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedArgument    OpenRegister event/entity classes are optional dependencies.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 * @spec openspec/specs/character-custom-fields/spec.md
	 */
	private function vetoFor(Event $event): ?array {
		[$newEntity, $oldEntity] = $this->extractEntities(event: $event);

		if ($this->isCharacterSchema(entity: $newEntity) === false) {
			return null;
		}

		$candidate = $newEntity->getObject();
		$oldCharacter = ($oldEntity?->getObject() ?? []);

		// Extra field values are checked against their definitions
		// (characters-custom-fields); the guard looks at changed keys only.
		$errors = $this->customFields->check(candidate: $candidate, old: $oldCharacter);

		// Diff-scoping: only validate when an association or override field
		// actually changed. Unrelated edits must never be blocked by a
		// pre-existing unmet state.
		if ($errors === null && $this->associationsChanged(candidate: $candidate, old: $oldCharacter) === true) {
			$errors = $this->collectVeto(candidate: $candidate, oldCharacter: $oldCharacter);
		}

		return $errors;
	}//end vetoFor()

	/**
	 * Extract the [new, old] entity pair from a vetoable pre-write event.
	 *
	 * A create carries only the new object; an update carries both. Returning
	 * the pair keeps the event-shape knowledge in one place.
	 *
	 * @param Event $event The dispatched pre-write event.
	 *
	 * @return array{0: object, 1: object|null} The [newEntity, oldEntity] pair.
	 *
	 * @psalm-suppress MixedMethodCall     OpenRegister event classes are optional dependencies.
	 * @psalm-suppress MixedReturnStatement OpenRegister event classes are optional dependencies.
	 * @psalm-suppress MixedInferredReturnType OpenRegister event classes are optional dependencies.
	 * @psalm-suppress UndefinedMethod     `getNewObject()`/`getOldObject()` are declared on
	 *                                     OCA\OpenRegister\Event\ObjectUpdatingEvent, which Psalm
	 *                                     resolves as the base OCP Event because OpenRegister is an
	 *                                     optional dependency. The `instanceof` above narrows it at
	 *                                     runtime; the two pre-existing errors this suppresses were
	 *                                     the only Psalm findings in the app.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	private function extractEntities(Event $event): array {
		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) {
			// @phpstan-ignore-next-line
			return [$event->getObject(), null];
		}

		// @phpstan-ignore-next-line
		return [$event->getNewObject(), $event->getOldObject()];
	}//end extractEntities()

	/**
	 * Collect the veto payload for a candidate write, or null when it may pass.
	 *
	 * Two independent grounds, checked in order: a non-GM authoring requirement
	 * overrides (auth, not game-rule) short-circuits before any game-rule
	 * validation runs; otherwise the requirement/budget result decides.
	 *
	 * @param array<string,mixed> $candidate The candidate character.
	 * @param array<string,mixed> $oldCharacter The persisted character.
	 *
	 * @return array<string,mixed>|null The errors payload, or null to allow.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	private function collectVeto(array $candidate, array $oldCharacter): ?array {
		$result = $this->requirementService->validate(candidate: $candidate, oldCharacter: $oldCharacter);
		// Only an explicit pass allows: a result without `valid`, or with any
		// other value, is refused rather than read as an allow.
		if (($result['valid'] ?? null) !== true) {
			return $this->buildErrorPayload(result: $result);
		}

		return null;
	}//end collectVeto()

	/**
	 * Whether the entity belongs to the character schema.
	 *
	 * @param object $entity The OpenRegister object entity.
	 *
	 * @return bool True when this is a character write.
	 *
	 * ⚠️ THE PROBE MUST BE `is_callable()`, NEVER `method_exists()`.
	 * `OCA\OpenRegister\Db\ObjectEntity` declares only `getObject()`; every
	 * other accessor — `getSchema()` among them — is resolved at runtime by
	 * `OCP\AppFramework\Db\Entity::__call()`. `method_exists()` inspects the
	 * DECLARED method table and therefore answers **false** for a magic getter
	 * that is perfectly callable:
	 *
	 *     method_exists($entity, 'getSchema')   -> false
	 *     is_callable([$entity, 'getSchema'])   -> true
	 *     $entity->getSchema()                  -> "17"
	 *     method_exists($entity, 'getObject')   -> true   (declared — control)
	 *
	 * With `method_exists()` this method returned false for EVERY character
	 * write, so `handle()` returned early, no veto was ever raised, and a
	 * character violating a declared `requiredSkills[]` prerequisite persisted
	 * at HTTP 201/200 with no log line — the whole
	 * `skill-requirement-enforcement` capability was inert while its listener
	 * was correctly registered and its validator correctly returned
	 * `valid: false`. Reported as larpinq#308 (which mis-attributed it to
	 * OpenRegister not dispatching the vetoable pre-write event; OpenRegister
	 * dispatches it, and this listener is first in the chain).
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
	 */
	private function isCharacterSchema(object $entity): bool {
		$configured = $this->config->getValueString(AppIdentity::APP_ID, 'character_schema', '');
		if ($configured === '') {
			return false;
		}

		$schema = '';
		if (is_callable([$entity, 'getSchema']) === true) {
			$schema = (string)$entity->getSchema();
		}

		return ($schema !== '' && $schema === $configured);
	}//end isCharacterSchema()

	/**
	 * Whether the relevant association/override fields changed.
	 *
	 * @param array<string,mixed> $candidate The candidate character.
	 * @param array<string,mixed> $old The persisted character.
	 *
	 * @return bool True when skills/items/conditions/requirementOverrides differ.
	 */
	private function associationsChanged(array $candidate, array $old): bool {
		if (empty($old) === true) {
			return true;
		}

		foreach (['skills', 'items', 'conditions', 'requirementOverrides'] as $field) {
			$a = $candidate[$field] ?? null;
			$b = $old[$field] ?? null;
			if ($a !== $b) {
				return true;
			}
		}

		return false;
	}//end associationsChanged()

	/**
	 * Reject non-GM writes that add or modify requirementOverrides entries.
	 *
	 * @param array<string,mixed> $candidate The candidate character.
	 * @param array<string,mixed> $old The persisted character.
	 *
	 * @return array<string,mixed>|null The requirementOverrides errors payload when refused, else null.
	 */
	private function checkOverrideAuthorization(array $candidate, array $old): ?array {
		if ($this->overridesChanged(candidate: $candidate, old: $old) === false) {
			return null;
		}

		// An empty reason on any override entry is itself invalid.
		if ($this->hasOverrideWithoutReason(candidate: $candidate) === true) {
			return ['requirementOverrides' => [self::OVERRIDE_REASON_REQUIRED]];
		}

		if ($this->callerIsGameMaster() === false) {
			return ['requirementOverrides' => [self::OVERRIDE_FORBIDDEN]];
		}

		return null;
	}//end checkOverrideAuthorization()

	/**
	 * Whether the requirementOverrides of the candidate differ from the stored ones.
	 *
	 * @param array<string,mixed> $candidate The candidate character.
	 * @param array<string,mixed> $old       The persisted character.
	 *
	 * @return bool True when the overrides changed, were added or were removed.
	 */
	private function overridesChanged(array $candidate, array $old): bool {
		return ($candidate['requirementOverrides'] ?? []) !== ($old['requirementOverrides'] ?? []);
	}//end overridesChanged()

	/**
	 * Whether any override entry of the candidate carries an empty or missing reason.
	 *
	 * @param array<string,mixed> $candidate The candidate character.
	 *
	 * @return bool True when an entry has no reason.
	 */
	private function hasOverrideWithoutReason(array $candidate): bool {
		$overrides = ($candidate['requirementOverrides'] ?? []);
		if (is_array($overrides) === false) {
			return false;
		}

		foreach ($overrides as $override) {
			if (is_array($override) === true && trim((string)($override['reason'] ?? '')) === '') {
				return true;
			}
		}

		return false;
	}//end hasOverrideWithoutReason()

	/**
	 * Whether the signed-in user is in the game master group.
	 *
	 * @return bool False when nobody is signed in or the user is not a game master.
	 */
	private function callerIsGameMaster(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		return $this->groupManager->isInGroup($user->getUID(), self::GM_GROUP);
	}//end callerIsGameMaster()

	/**
	 * Build the structured OR rejection payload from the validation result.
	 *
	 * @param array<string,mixed> $result The SkillRequirementService result.
	 *
	 * @return array<string,mixed> The errors payload.
	 */
	private function buildErrorPayload(array $result): array {
		$payload = [
			'code' => 'requirements_not_met',
			'message' => 'One or more skill requirements are not met.',
			'requirements' => $this->unmetRequirements(result: $result),
		];

		$budget = $result['budget'] ?? [];
		if (is_array($budget) === true && ($budget['ok'] ?? true) === false) {
			$payload['budget'] = $budget;
			$payload['message'] = 'Insufficient XP and/or unmet skill requirements.';
		}

		return $payload;
	}//end buildErrorPayload()

	/**
	 * The requirement entries of a validation result that are unmet or unresolvable.
	 *
	 * @param array<string,mixed> $result The SkillRequirementService result.
	 *
	 * @return array<int,array<string,mixed>> The failing entries, in order.
	 */
	private function unmetRequirements(array $result): array {
		$unmet = [];
		// @psalm-suppress MixedAssignment Validation result entries are typed in the service.
		foreach (($result['requirements'] ?? []) as $entry) {
			if (is_array($entry) === true && in_array(($entry['status'] ?? ''), ['unmet', 'unresolvable'], true) === true) {
				$unmet[] = $entry;
			}
		}

		return $unmet;
	}//end unmetRequirements()
}//end class
