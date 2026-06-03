<?php

/**
 * CharacterService for LarpingApp
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-64
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-65
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-66
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-67
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-68
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-69
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-70
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-71
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-72
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-73
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-74
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-75
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-76
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-77
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-78
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-79
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

use Psr\Log\LoggerInterface;

/**
 * Service class for character-related operations.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-64
 */
class CharacterService
{

    /**
     * All skills indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $allSkills = [];

    /**
     * All items indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $allItems = [];

    /**
     * All conditions indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $allConditions = [];

    /**
     * All events indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $allEvents = [];

    /**
     * All effects indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $allEffects = [];

    /**
     * All abilities indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $allAbilities = [];

    /**
     * Flag indicating whether entity collections have been loaded.
     *
     * @var boolean
     */
    private bool $entitiesLoaded = false;

    /**
     * Constructor for CharacterService.
     *
     * Entity collections are NOT loaded here. Loading is deferred until
     * calculateCharacter() is first called, so DI resolution of this service
     * does not issue 6 OR queries unless stat calculation is actually needed.
     * Closes #217.
     *
     * @param RegisterObjectFetcher $objectFetcher The register object fetcher.
     * @param LoggerInterface       $logger        The logger interface.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly RegisterObjectFetcher $objectFetcher,
        private readonly LoggerInterface $logger
    ) {
    }//end __construct()

    /**
     * Index an array of entities by their ID field.
     *
     * @param array $entities The entities to index.
     *
     * @return array<string, array<string, mixed>> Entities indexed by ID.
     */
    private function indexById(array $entities): array
    {
        // @var array<string, array<string, mixed>> $indexed
        $indexed = [];
        // @psalm-suppress MixedAssignment Entity entries from object fetcher
        foreach ($entities as $entity) {
            $indexed[(string) $entity['id']] = $entity;
        }

        return $indexed;
    }//end indexById()

    /**
     * Load all entities into memory and index them by ID.
     *
     * Guarded by $entitiesLoaded so the 6 OR queries are only issued once per
     * service instance and only when a calculation is actually requested.
     * Closes #217.
     *
     * @return void
     */
    private function loadAllEntities(): void
    {
        if ($this->entitiesLoaded === true) {
            return;
        }

        $this->allSkills      = $this->indexById(entities: $this->objectFetcher->getObjects('skill'));
        $this->allItems       = $this->indexById(entities: $this->objectFetcher->getObjects('item'));
        $this->allConditions  = $this->indexById(entities: $this->objectFetcher->getObjects('condition'));
        $this->allEvents      = $this->indexById(entities: $this->objectFetcher->getObjects('event'));
        $this->allEffects     = $this->indexById(entities: $this->objectFetcher->getObjects('effect'));
        $this->allAbilities   = $this->indexById(entities: $this->objectFetcher->getObjects('ability'));
        $this->entitiesLoaded = true;
    }//end loadAllEntities()

    /**
     * Calculate stats for all characters.
     *
     * @return array Updated array of Character objects.
     *
     * @psalm-suppress PossiblyUnusedMethod Public API for batch character stat calculation.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-64
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-65
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-66
     */
    public function calculateAllCharacters(): array
    {
        // @var array<int, array<string, mixed>> $characters
        $characters        = $this->objectFetcher->getObjects('character');
        $updatedCharacters = [];
        foreach ($characters as $character) {
            $updatedCharacters[] = $this->calculateCharacter(character: $character);
        }

        return $updatedCharacters;
    }//end calculateAllCharacters()

    /**
     * Initialize ability scores from base ability values.
     *
     * @return array<string, array{name: string, base: int, value: int, audit: array}> Ability scores.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-71
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-72
     */
    private function initializeAbilityScores(): array
    {
        // @var array<string, array{name: string, base: int, value: int, audit: array}> $abilityScores
        $abilityScores = [];
        foreach ($this->allAbilities as $ability) {
            if (isset($ability['base']) === true && is_numeric($ability['base']) === false) {
                $this->logger->warning(
                    'LarpingApp: ability has non-numeric base value; defaulting to 0',
                    [
                        'abilityId'   => (string) ($ability['id'] ?? 'unknown'),
                        'abilityName' => (string) ($ability['name'] ?? 'unknown'),
                        'base'        => $ability['base'],
                    ]
                );
            }

            $abilityScores[(string) $ability['id']] = [
                'name'  => (string) ($ability['name'] ?? ''),
                'base'  => (int) ($ability['base'] ?? 0),
                'value' => (int) ($ability['base'] ?? 0),
                'audit' => [],
            ];
        }

        return $abilityScores;
    }//end initializeAbilityScores()

    /**
     * Apply effects from a character's linked entities of a given type.
     *
     * Looks up each entity ID in the provided lookup table,
     * then applies any effects found on those entities.
     *
     * @param array<string, array<string, mixed>> $abilityScores  Reference to ability scores.
     * @param array                               $character      Character data array.
     * @param string                              $property       Character property name (e.g. 'skills').
     * @param array<string, array<string, mixed>> $lookup         Entity lookup table indexed by ID.
     * @param array<string, bool>                 $appliedEffects Tracks which non-cumulative effects have been applied.
     *
     * @return void
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-69
     */
    private function applyEntityEffects(
        array &$abilityScores,
        array $character,
        string $property,
        array $lookup,
        array &$appliedEffects
    ): void {
        if (isset($character[$property]) === false
            || is_array($character[$property]) === false
            || empty($character[$property]) === true
        ) {
            return;
        }

        // @psalm-suppress MixedAssignment Character array values are mixed
        foreach ($character[$property] as $entityId) {
            $entity = $lookup[(string) $entityId] ?? null;
            if ($entity === null) {
                continue;
            }

            if (isset($entity['effects']) === true && empty($entity['effects']) === false) {
                // @var array|null $entityEffects
                $entityEffects = $entity['effects'];
                $this->applyEffects(
                    abilities: $abilityScores,
                    effects: $entityEffects,
                    appliedEffects: $appliedEffects
                );
            }
        }
    }//end applyEntityEffects()

    /**
     * Calculate stats for a single character array.
     *
     * @param array $character Character data array.
     *
     * @return array Updated character data array with calculated stats.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-67
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-68
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-69
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-70
     */
    public function calculateCharacter(array $character): array
    {
        // Load entity collections lazily on first call. Closes #217.
        $this->loadAllEntities();

        $abilityScores = $this->initializeAbilityScores();

        // Track which non-cumulative effect IDs have already been applied this pass.
        // Resets per calculateCharacter() call (not shared across characters).
        // Closes #208.
        $appliedEffects = [];

        // Apply effects from each entity type the character has.
        $this->applyEntityEffects(
            abilityScores: $abilityScores,
            character: $character,
            property: 'skills',
            lookup: $this->allSkills,
            appliedEffects: $appliedEffects
        );
        $this->applyEntityEffects(
            abilityScores: $abilityScores,
            character: $character,
            property: 'items',
            lookup: $this->allItems,
            appliedEffects: $appliedEffects
        );
        $this->applyEntityEffects(
            abilityScores: $abilityScores,
            character: $character,
            property: 'conditions',
            lookup: $this->allConditions,
            appliedEffects: $appliedEffects
        );
        $this->applyEntityEffects(
            abilityScores: $abilityScores,
            character: $character,
            property: 'events',
            lookup: $this->allEvents,
            appliedEffects: $appliedEffects
        );

        // Update character array with calculated stats.
        $character['stats'] = $abilityScores;

        return $character;
    }//end calculateCharacter()

    /**
     * Apply effects to abilities.
     *
     * @param array<string, array{name?: string, base?: int, value: int, audit: array}> $abilities      Reference to abilities.
     * @param array|null                                                                $effects        Array of effect IDs.
     * @param array<string, bool>                                                       $appliedEffects Tracks applied non-cumulative effects.
     *
     * @return void
     *
     * @psalm-suppress MixedArgumentTypeCoercion Abilities array keys may widen during mutation.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-73
     */
    private function applyEffects(array &$abilities, ?array $effects, array &$appliedEffects): void
    {
        // Return early if effects is null or empty.
        if ($effects === null || count($effects) === 0) {
            return;
        }

        // @psalm-suppress MixedAssignment Effect IDs from entity arrays
        foreach ($effects as $effectId) {
            // Skip if effectId is null.
            if ($effectId === null) {
                continue;
            }

            $effect = $this->allEffects[(string) $effectId] ?? null;
            if ($effect !== null) {
                $this->calculateEffect(
                    abilities: $abilities,
                    effect: $effect,
                    appliedEffects: $appliedEffects
                );
            }
        }
    }//end applyEffects()

    /**
     * Collect all unique ability IDs affected by a given effect.
     *
     * Merges `abilities` array and `stat_id` field, then deduplicates to prevent
     * double-application when `stat_id` is also present in `abilities`. Closes #208.
     *
     * @param array<string, mixed> $effect Effect data.
     *
     * @return array List of unique ability IDs.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-74
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-75
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-76
     */
    private function collectEffectAbilities(array $effect): array
    {
        $effectAbilities = [];
        if (isset($effect['abilities']) === true && is_array($effect['abilities']) === true) {
            $effectAbilities = $effect['abilities'];
        }

        // Add stat_id to affected abilities if present and not null.
        if (isset($effect['stat_id']) === true && $effect['stat_id'] !== null) {
            // @psalm-suppress MixedAssignment Effect array values are mixed
            $effectAbilities[] = $effect['stat_id'];
        }

        // Deduplicate to prevent double-application when stat_id is also in abilities.
        // Closes #208 (stat_id double-apply).
        return array_values(array_unique($effectAbilities));
    }//end collectEffectAbilities()

    /**
     * Apply a modifier to a single ability based on an effect.
     *
     * The `modifier` value is always coerced to its absolute value; the
     * `modification` enum (positive/negative) controls direction. This
     * eliminates the sign-direction confusion where `{modifier: -3,
     * modification: 'negative'}` would have added 3 instead of subtracting it.
     * Closes #209.
     *
     * The audit trail stores only the minimal scalar fields needed to explain
     * the change (effect ID + label + delta), not the full effect object.
     * This avoids denormalisation bloat if derived stats are ever persisted.
     * Closes #219.
     *
     * @param array<string, array<string, mixed>> $abilities Reference to abilities.
     * @param string                              $abilityId The ability ID.
     * @param array<string, mixed>                $effect    Effect data.
     *
     * @return void
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-77
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-78
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-79
     */
    private function applyModifierToAbility(array &$abilities, string $abilityId, array $effect): void
    {
        if (isset($abilities[$abilityId]['value']) === false) {
            $abilities[$abilityId]['value'] = 0;
        }

        // Get current value and modifiers.
        $currentValue = (int) $abilities[$abilityId]['value'];

        // Coerce modifier to a non-negative integer. The `modification` enum (positive/negative)
        // controls the sign. This prevents sign-direction confusion where a GM authors
        // `{modifier: -3, modification: 'negative'}` and gets +3 instead of -3.
        // Closes #209.
        $modifier = abs((int) ($effect['modifier'] ?? 0));

        // @var string $modification.
        $modification = $effect['modification'] ?? 'positive';

        // Apply modification based on type.
        if ($modification === 'positive') {
            $abilities[$abilityId]['value'] = $currentValue + $modifier;
        } else if ($modification === 'negative') {
            $abilities[$abilityId]['value'] = $currentValue - $modifier;
        }

        $newValue = $abilities[$abilityId]['value'];

        // Store a lean audit entry — effect ID + label + delta only.
        // Storing the full $effect object risks denormalisation bloat when stats
        // are ever persisted back to OR. Closes #219.
        $abilities[$abilityId]['audit'][] = [
            'type'       => 'effect',
            'effectId'   => (string) ($effect['id'] ?? ''),
            'effectName' => (string) ($effect['name'] ?? ''),
            'old'        => $currentValue,
            'new'        => $newValue,
        ];
    }//end applyModifierToAbility()

    /**
     * Calculate and apply a single effect.
     *
     * Skips non-cumulative effects that have already been applied in this
     * calculation pass. Closes #208.
     *
     * @param array<string, array{name?: string, base?: int, value: int, audit: array}> $abilities      Reference to abilities.
     * @param array<string, mixed>                                                      $effect         Effect data.
     * @param array<string, bool>                                                       $appliedEffects Tracks applied non-cumulative effects.
     *
     * @return void
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-76
     */
    private function calculateEffect(array &$abilities, array $effect, array &$appliedEffects): void
    {
        $effectId = (string) ($effect['id'] ?? '');

        // Enforce the non-cumulative dedup rule. If this effect has been applied
        // already and is marked non-cumulative, skip it. Closes #208.
        if ($effectId !== '' && isset($appliedEffects[$effectId]) === true) {
            $cumulative = (string) ($effect['cumulative'] ?? 'non-cumulative');
            if ($cumulative === 'non-cumulative') {
                return;
            }
        }

        $effectAbilities = $this->collectEffectAbilities(effect: $effect);

        // Skip if no abilities are affected.
        if (empty($effectAbilities) === true) {
            return;
        }

        // Apply the effect to each affected ability.
        // @psalm-suppress MixedAssignment Ability IDs from effect arrays.
        foreach ($effectAbilities as $rawAbilityId) {
            // Skip if abilityId is null.
            if ($rawAbilityId === null) {
                continue;
            }

            $this->applyModifierToAbility(
                abilities: $abilities,
                abilityId: (string) $rawAbilityId,
                effect: $effect
            );
        }

        // Record that this effect was applied so non-cumulative dedupe works
        // on the next encounter of the same effect ID within this pass.
        if ($effectId !== '') {
            $appliedEffects[$effectId] = true;
        }
    }//end calculateEffect()
}//end class
