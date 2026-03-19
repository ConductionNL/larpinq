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
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

/**
 * Service class for character-related operations.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
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
     * Constructor for CharacterService.
     *
     * @param RegisterObjectFetcher $objectFetcher The register object fetcher.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly RegisterObjectFetcher $objectFetcher
    ) {
        $this->loadAllEntities();
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
     * @return void
     */
    private function loadAllEntities(): void
    {
        $this->allSkills     = $this->indexById(entities: $this->objectFetcher->getObjects('skill'));
        $this->allItems      = $this->indexById(entities: $this->objectFetcher->getObjects('item'));
        $this->allConditions = $this->indexById(entities: $this->objectFetcher->getObjects('condition'));
        $this->allEvents     = $this->indexById(entities: $this->objectFetcher->getObjects('event'));
        $this->allEffects    = $this->indexById(entities: $this->objectFetcher->getObjects('effect'));
        $this->allAbilities  = $this->indexById(entities: $this->objectFetcher->getObjects('ability'));
    }//end loadAllEntities()

    /**
     * Calculate stats for all characters.
     *
     * @return array Updated array of Character objects.
     *
     * @psalm-suppress PossiblyUnusedMethod Public API for batch character stat calculation.
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
     */
    private function initializeAbilityScores(): array
    {
        // @var array<string, array{name: string, base: int, value: int, audit: array}> $abilityScores
        $abilityScores = [];
        foreach ($this->allAbilities as $ability) {
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
     * @param array<string, array<string, mixed>> $abilityScores Reference to ability scores.
     * @param array                               $character     Character data array.
     * @param string                              $property      Character property name (e.g. 'skills').
     * @param array<string, array<string, mixed>> $lookup        Entity lookup table indexed by ID.
     *
     * @return void
     */
    private function applyEntityEffects(
        array &$abilityScores,
        array $character,
        string $property,
        array $lookup
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
                $this->applyEffects(abilities: $abilityScores, effects: $entityEffects);
            }
        }
    }//end applyEntityEffects()

    /**
     * Calculate stats for a single character array.
     *
     * @param array $character Character data array.
     *
     * @return array Updated character data array with calculated stats.
     */
    public function calculateCharacter(array $character): array
    {
        $abilityScores = $this->initializeAbilityScores();

        // Apply effects from each entity type the character has.
        $this->applyEntityEffects(
            abilityScores: $abilityScores,
            character: $character,
            property: 'skills',
            lookup: $this->allSkills
        );
        $this->applyEntityEffects(
            abilityScores: $abilityScores,
            character: $character,
            property: 'items',
            lookup: $this->allItems
        );
        $this->applyEntityEffects(
            abilityScores: $abilityScores,
            character: $character,
            property: 'conditions',
            lookup: $this->allConditions
        );
        $this->applyEntityEffects(
            abilityScores: $abilityScores,
            character: $character,
            property: 'events',
            lookup: $this->allEvents
        );

        // Update character array with calculated stats.
        $character['stats'] = $abilityScores;

        return $character;
    }//end calculateCharacter()

    /**
     * Apply effects to abilities.
     *
     * @param array<string, array{name?: string, base?: int, value: int, audit: array}> $abilities Reference to abilities.
     * @param array|null                                                                $effects   Array of effect IDs.
     *
     * @return void
     *
     * @psalm-suppress MixedArgumentTypeCoercion Abilities array keys may widen during mutation.
     */
    private function applyEffects(array &$abilities, ?array $effects): void
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
                $this->calculateEffect(abilities: $abilities, effect: $effect);
            }
        }
    }//end applyEffects()

    /**
     * Collect all ability IDs affected by a given effect.
     *
     * @param array<string, mixed> $effect Effect data.
     *
     * @return array List of ability IDs.
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

        return $effectAbilities;
    }//end collectEffectAbilities()

    /**
     * Apply a modifier to a single ability based on an effect.
     *
     * @param array<string, array<string, mixed>> $abilities Reference to abilities.
     * @param string                              $abilityId The ability ID.
     * @param array<string, mixed>                $effect    Effect data.
     *
     * @return void
     */
    private function applyModifierToAbility(array &$abilities, string $abilityId, array $effect): void
    {
        if (isset($abilities[$abilityId]['value']) === false) {
            $abilities[$abilityId]['value'] = 0;
        }

        // Get current value and modifiers.
        $currentValue = (int) $abilities[$abilityId]['value'];
        $modifier     = (int) ($effect['modifier'] ?? 0);
        // @var string $modification.
        $modification = $effect['modification'] ?? 'positive';

        // Apply modification based on type.
        if ($modification === 'positive') {
            $abilities[$abilityId]['value'] = $currentValue + $modifier;
        } else if ($modification === 'negative') {
            $abilities[$abilityId]['value'] = $currentValue - $modifier;
        }

        // Add audit trail.
        $abilities[$abilityId]['audit'][] = [
            'type'   => 'effect',
            'effect' => $effect,
            'old'    => $currentValue,
            'new'    => $abilities[$abilityId]['value'],
        ];
    }//end applyModifierToAbility()

    /**
     * Calculate and apply a single effect.
     *
     * @param array<string, array{name?: string, base?: int, value: int, audit: array}> $abilities Reference to abilities.
     * @param array<string, mixed>                                                      $effect    Effect data.
     *
     * @return void
     */
    private function calculateEffect(array &$abilities, array $effect): void
    {
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
    }//end calculateEffect()
}//end class
