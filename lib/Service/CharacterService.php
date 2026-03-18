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

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use OCP\IURLGenerator;
use Symfony\Component\Uid\Uuid;
use OCA\LarpingApp\Db\Character;
use OCA\LarpingApp\Db\Ability;
use OCA\LarpingApp\Db\Skill;
use OCA\LarpingApp\Db\Item;
use OCA\LarpingApp\Db\Condition;
use OCA\LarpingApp\Db\Event;
use OCA\LarpingApp\Db\Effect;
use OCA\LarpingApp\Db\CharacterMapper;
use OCA\LarpingApp\Db\AbilityMapper;
use OCA\LarpingApp\Db\SkillMapper;
use OCA\LarpingApp\Db\ItemMapper;
use OCA\LarpingApp\Db\ConditionMapper;
use OCA\LarpingApp\Db\EventMapper;
use OCA\LarpingApp\Db\EffectMapper;
use OCA\LarpingApp\Service\ObjectService;

// And in case of open registers.
use OCA\OpenRegister\Db\ObjectEntity;

/**
 * Service class for character-related operations.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UnusedProperty Mapper properties injected via DI for future direct access.
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
     * The object service for fetching entities.
     *
     * @var ObjectService
     */
    private ObjectService $objectService;

    /**
     * Constructor for CharacterService.
     *
     * @param AbilityMapper   $abilityMapper   Ability mapper.
     * @param CharacterMapper $characterMapper Character mapper.
     * @param ConditionMapper $conditionMapper Condition mapper.
     * @param EffectMapper    $effectMapper    Effect mapper.
     * @param EventMapper     $eventMapper     Event mapper.
     * @param ItemMapper      $itemMapper      Item mapper.
     * @param ObjectService   $objectService   Object service.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        /** @psalm-suppress UnusedProperty Reserved for future direct mapper access. */
        private AbilityMapper $abilityMapper,
        /** @psalm-suppress UnusedProperty Reserved for future direct mapper access. */
        private CharacterMapper $characterMapper,
        /** @psalm-suppress UnusedProperty Reserved for future direct mapper access. */
        private ConditionMapper $conditionMapper,
        /** @psalm-suppress UnusedProperty Reserved for future direct mapper access. */
        private EffectMapper $effectMapper,
        /** @psalm-suppress UnusedProperty Reserved for future direct mapper access. */
        private EventMapper $eventMapper,
        /** @psalm-suppress UnusedProperty Reserved for future direct mapper access. */
        private ItemMapper $itemMapper,
        ObjectService $objectService
    ) {
        $this->objectService = $objectService;
        $this->loadAllEntities();
    }//end __construct()

    /**
     * Load all entities into memory and index them by ID.
     *
     * @return void
     */
    private function loadAllEntities(): void
    {
        // Get all skills and index them by ID.
        $skills          = $this->objectService->getObjects('skill');
        $this->allSkills = array_reduce(
            $skills,
                /**
                 * @param array<string, array<string, mixed>> $carry
                 * @param array<string, mixed>                $skill
                 * @return array<string, array<string, mixed>>
                 */
                function (array $carry, array $skill): array {
                    $carry[(string) $skill['id']] = $skill;
                    return $carry;
                },
                []
        );

        // Get all items and index them by ID.
        $items          = $this->objectService->getObjects('item');
        $this->allItems = array_reduce(
            $items,
                /**
                 * @param array<string, array<string, mixed>> $carry
                 * @param array<string, mixed>                $item
                 * @return array<string, array<string, mixed>>
                 */
                function (array $carry, array $item): array {
                    $carry[(string) $item['id']] = $item;
                    return $carry;
                },
                []
        );

        // Get all conditions and index them by ID.
        $conditions          = $this->objectService->getObjects('condition');
        $this->allConditions = array_reduce(
            $conditions,
                /**
                 * @param array<string, array<string, mixed>> $carry
                 * @param array<string, mixed>                $condition
                 * @return array<string, array<string, mixed>>
                 */
                function (array $carry, array $condition): array {
                    $carry[(string) $condition['id']] = $condition;
                    return $carry;
                },
                []
        );

        // Get all events and index them by ID.
        $events          = $this->objectService->getObjects('event');
        $this->allEvents = array_reduce(
            $events,
                /**
                 * @param array<string, array<string, mixed>> $carry
                 * @param array<string, mixed>                $event
                 * @return array<string, array<string, mixed>>
                 */
                function (array $carry, array $event): array {
                    $carry[(string) $event['id']] = $event;
                    return $carry;
                },
                []
        );

        // Get all effects and index them by ID.
        $effects          = $this->objectService->getObjects('effect');
        $this->allEffects = array_reduce(
            $effects,
                /**
                 * @param array<string, array<string, mixed>> $carry
                 * @param array<string, mixed>                $effect
                 * @return array<string, array<string, mixed>>
                 */
                function (array $carry, array $effect): array {
                    $carry[(string) $effect['id']] = $effect;
                    return $carry;
                },
                []
        );

        // Get all abilities and index them by ID.
        $abilities          = $this->objectService->getObjects('ability');
        $this->allAbilities = array_reduce(
            $abilities,
                /**
                 * @param array<string, array<string, mixed>> $carry
                 * @param array<string, mixed>                $ability
                 * @return array<string, array<string, mixed>>
                 */
                function (array $carry, array $ability): array {
                    $carry[(string) $ability['id']] = $ability;
                    return $carry;
                },
                []
        );
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
        /** @var array<int, array<string, mixed>> $characters */
        $characters        = $this->objectService->getObjects('character');
        $updatedCharacters = [];
        foreach ($characters as $character) {
            $updatedCharacters[] = $this->calculateCharacter(character: $character);
        }

        return $updatedCharacters;
    }//end calculateAllCharacters()

    /**
     * Calculate stats for a single character array.
     *
     * @param array $character Character data array.
     *
     * @return array Updated character data array with calculated stats.
     */
    public function calculateCharacter(array $character): array
    {
        // Create an array of abilities with their base scores.
        $abilityScores = [];

        // Initialize ability scores from base values.
        foreach ($this->allAbilities as $ability) {
            $abilityScores[(string) $ability['id']] = [
                'name'  => $ability['name'],
                'base'  => $ability['base'] ?? 0,
                'value' => $ability['base'] ?? 0,
                'audit' => [],
            ];
        }

        // Apply effects from skills if character has any.
        if (isset($character['skills']) === true
            && is_array($character['skills']) === true
            && empty($character['skills']) === false
        ) {
            foreach ($character['skills'] as $skillId) {
                /** @var array<string,mixed>|null $skill */
                $skill = $this->allSkills[(string) $skillId] ?? null;
                if ($skill !== null && isset($skill['effects']) === true && empty($skill['effects']) === false) {
                    $this->applyEffects(abilities: $abilityScores, effects: $skill['effects']);
                }
            }
        }

        // Apply effects from items if character has any.
        if (isset($character['items']) === true
            && is_array($character['items']) === true
            && empty($character['items']) === false
        ) {
            foreach ($character['items'] as $itemId) {
                /** @var array<string,mixed>|null $item */
                $item = $this->allItems[(string) $itemId] ?? null;
                if ($item !== null && isset($item['effects']) === true && empty($item['effects']) === false) {
                    $this->applyEffects(abilities: $abilityScores, effects: $item['effects']);
                }
            }
        }

        // Apply effects from conditions if character has any.
        if (isset($character['conditions']) === true
            && is_array($character['conditions']) === true
            && empty($character['conditions']) === false
        ) {
            foreach ($character['conditions'] as $conditionId) {
                /** @var array<string,mixed>|null $condition */
                $condition = $this->allConditions[(string) $conditionId] ?? null;
                if ($condition !== null && isset($condition['effects']) === true && empty($condition['effects']) === false) {
                    $this->applyEffects(abilities: $abilityScores, effects: $condition['effects']);
                }
            }
        }

        // Apply effects from events if character has any.
        if (isset($character['events']) === true
            && is_array($character['events']) === true
            && empty($character['events']) === false
        ) {
            foreach ($character['events'] as $eventId) {
                /** @var array<string,mixed>|null $event */
                $event = $this->allEvents[(string) $eventId] ?? null;
                if ($event !== null && isset($event['effects']) === true && empty($event['effects']) === false) {
                    $this->applyEffects(abilities: $abilityScores, effects: $event['effects']);
                }
            }
        }

        // Update character array with calculated stats.
        $character['stats'] = $abilityScores;

        return $character;
    }//end calculateCharacter()

    /**
     * Apply effects to abilities.
     *
     * @param array      $abilities Reference to the abilities array.
     * @param array|null $effects   Array of effect IDs.
     *
     * @return void
     */
    private function applyEffects(array &$abilities, ?array $effects): void
    {
        // Return early if effects is null or empty.
        if ($effects === null || count($effects) === 0) {
            return;
        }

        foreach ($effects as $effectId) {
            // Skip if effectId is null.
            if ($effectId === null) {
                continue;
            }

            /** @var array<string,mixed>|null $effect */
            $effect = $this->allEffects[(string) $effectId] ?? null;
            if ($effect !== null) {
                $this->calculateEffect(abilities: $abilities, effect: $effect);
            }
        }
    }//end applyEffects()

    /**
     * Calculate and apply a single effect.
     *
     * @param array<string, array{name?: string, base?: int, value: int, audit: array}> $abilities Reference to the abilities array.
     * @param array<string, mixed> $effect Effect array containing stat_id, modifier and modification.
     *
     * @return void
     */
    private function calculateEffect(array &$abilities, array $effect): void
    {
        // Initialize array to track affected abilities.
        $effectAbilities = [];
        if (isset($effect['abilities']) === true && is_array($effect['abilities']) === true) {
            $effectAbilities = $effect['abilities'];
        }

        // Add stat_id to affected abilities if present and not null.
        if (isset($effect['stat_id']) === true && $effect['stat_id'] !== null) {
            $effectAbilities[] = $effect['stat_id'];
        }

        // Skip if no abilities are affected.
        if (empty($effectAbilities) === true) {
            return;
        }

        // Ensure each affected ability exists in abilities array.
        foreach ($effectAbilities as $rawAbilityId) {
            // Skip if abilityId is null.
            if ($rawAbilityId === null) {
                continue;
            }

            $abilityId = (string) $rawAbilityId;

            if (isset($abilities[$abilityId]['value']) === false) {
                $abilities[$abilityId]['value'] = 0;
            } else if (is_int($abilities[$abilityId]['value']) === false) {
                $abilities[$abilityId]['value'] = (int) $abilities[$abilityId]['value'];
            }

            // Get current value and modifiers.
            $currentValue = (int) $abilities[$abilityId]['value'];
            // Get modifier value from effect, defaulting to 0 if not set.
            $modifier = (int) ($effect['modifier'] ?? 0);
            // Get modification type, defaulting to 'positive' if not set.
            /** @var string $modification */
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
        }//end foreach
    }//end calculateEffect()
}//end class
