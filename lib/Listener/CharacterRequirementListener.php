<?php

/**
 * CharacterRequirementListener for LarpingApp
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
 * @package   OCA\LarpingApp\Listener
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Listener;

use OCA\LarpingApp\AppInfo\Application;
use OCA\LarpingApp\Service\SkillRequirementService;
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
 * @package  OCA\LarpingApp\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
 *
 * @psalm-suppress UndefinedClass OpenRegister event classes are an optional dependency.
 *
 * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
 */
class CharacterRequirementListener implements IEventListener
{

    /**
     * The GM group whose members may author requirement overrides.
     *
     * @var string
     */
    private const GM_GROUP = 'gamemasters';

    /**
     * Constructor for CharacterRequirementListener.
     *
     * @param SkillRequirementService $requirementService The validation service.
     * @param IAppConfig              $config             Config (schema id resolution).
     * @param IUserSession            $userSession        The current user session.
     * @param IGroupManager           $groupManager       The group manager (GM override check).
     * @param LoggerInterface         $logger             The logger.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly SkillRequirementService $requirementService,
        private readonly IAppConfig $config,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
        private readonly LoggerInterface $logger
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
     * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
     */
    public function handle(Event $event): void
    {
        if (($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) === false
            && ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) === false
        ) {
            return;
        }

        // At this point $event is a vetoable OpenRegister pre-write event.
        // We call its methods via the generic Event reference because both
        // ObjectCreatingEvent and ObjectUpdatingEvent are optional runtime
        // dependencies (OpenRegister may be absent).  Static analysis does not
        // know about these optional classes; see the ignore annotations below.
        try {
            [$newEntity, $oldEntity] = $this->extractEntities(event: $event);

            if ($this->isCharacterSchema(entity: $newEntity) === false) {
                return;
            }

            $candidate    = $newEntity->getObject();
            $oldCharacter = [];
            if ($oldEntity !== null) {
                $oldCharacter = $oldEntity->getObject();
            }

            // Diff-scoping: only validate when an association or override field
            // actually changed. Unrelated edits must never be blocked by a
            // pre-existing unmet state.
            if ($this->associationsChanged(candidate: $candidate, old: $oldCharacter) === false) {
                return;
            }

            $errors = $this->collectVeto(candidate: $candidate, oldCharacter: $oldCharacter);
            if ($errors !== null) {
                $event->stopPropagation();
                // @phpstan-ignore-next-line
                $event->setErrors($errors);
            }
        } catch (\Throwable $e) {
            // Never fail-open on an unexpected error in the veto path is the
            // safe default for auth, but a game-rule validator that throws must
            // not brick all character writes. Log and allow the write to
            // proceed (degrade to data-only) — matches the "OR predates events"
            // graceful-degradation contract.
            $this->logger->error(
                'LarpingApp: skill-requirement validation errored; allowing the write (data-only fallback).',
                ['exception' => $e]
            );
        }//end try
    }//end handle()

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
     *
     * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
     */
    private function extractEntities(Event $event): array
    {
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
     * @param array<string,mixed> $candidate    The candidate character.
     * @param array<string,mixed> $oldCharacter The persisted character.
     *
     * @return array<string,mixed>|null The errors payload, or null to allow.
     *
     * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
     */
    private function collectVeto(array $candidate, array $oldCharacter): ?array
    {
        // GM-override authorization: a non-GM that adds/alters override
        // entries is rejected outright (auth, not game-rule).
        $authError = $this->checkOverrideAuthorization(candidate: $candidate, old: $oldCharacter);
        if ($authError !== null) {
            return ['requirementOverrides' => [$authError]];
        }

        $result = $this->requirementService->validate(candidate: $candidate, oldCharacter: $oldCharacter);
        if ($result['valid'] === false) {
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
     * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
     */
    private function isCharacterSchema(object $entity): bool
    {
        $configured = $this->config->getValueString(Application::APP_ID, 'character_schema', '');
        if ($configured === '') {
            return false;
        }

        $schema = '';
        if (method_exists($entity, 'getSchema') === true) {
            $schema = (string) $entity->getSchema();
        }

        return ($schema !== '' && $schema === $configured);
    }//end isCharacterSchema()

    /**
     * Whether the relevant association/override fields changed.
     *
     * @param array<string,mixed> $candidate The candidate character.
     * @param array<string,mixed> $old       The persisted character.
     *
     * @return bool True when skills/items/conditions/requirementOverrides differ.
     */
    private function associationsChanged(array $candidate, array $old): bool
    {
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
     * @param array<string,mixed> $old       The persisted character.
     *
     * @return array<string,mixed>|null An error entry when unauthorized, else null.
     */
    private function checkOverrideAuthorization(array $candidate, array $old): ?array
    {
        $newOverrides = $candidate['requirementOverrides'] ?? [];
        $oldOverrides = $old['requirementOverrides'] ?? [];

        if ($newOverrides === $oldOverrides) {
            return null;
        }

        // An empty reason on any override entry is itself invalid.
        if (is_array($newOverrides) === true) {
            foreach ($newOverrides as $override) {
                if (is_array($override) === true && trim((string) ($override['reason'] ?? '')) === '') {
                    return [
                        'code'    => 'override_reason_required',
                        'message' => 'A requirement override must carry a non-empty reason.',
                    ];
                }
            }
        }

        $user = $this->userSession->getUser();
        if ($user === null) {
            return [
                'code'    => 'override_forbidden',
                'message' => 'Only game masters may author requirement overrides.',
            ];
        }

        $isGm = $this->groupManager->isInGroup($user->getUID(), self::GM_GROUP);
        if ($isGm === false) {
            return [
                'code'    => 'override_forbidden',
                'message' => 'Only game masters may author requirement overrides.',
            ];
        }

        return null;
    }//end checkOverrideAuthorization()

    /**
     * Build the structured OR rejection payload from the validation result.
     *
     * @param array<string,mixed> $result The SkillRequirementService result.
     *
     * @return array<string,mixed> The errors payload.
     */
    private function buildErrorPayload(array $result): array
    {
        $unmet = [];
        // @psalm-suppress MixedAssignment Validation result entries are typed in the service.
        foreach (($result['requirements'] ?? []) as $entry) {
            if (is_array($entry) === true
                && (($entry['status'] ?? '') === 'unmet' || ($entry['status'] ?? '') === 'unresolvable')
            ) {
                $unmet[] = $entry;
            }
        }

        $payload = [
            'code'         => 'requirements_not_met',
            'message'      => 'One or more skill requirements are not met.',
            'requirements' => $unmet,
        ];

        $budget = $result['budget'] ?? [];
        if (is_array($budget) === true && ($budget['ok'] ?? true) === false) {
            $payload['budget']  = $budget;
            $payload['message'] = 'Insufficient XP and/or unmet skill requirements.';
        }

        return $payload;
    }//end buildErrorPayload()
}//end class
