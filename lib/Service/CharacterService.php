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
 */
class CharacterService
{

    /**
     * All skills indexed by ID.
     *
     * @var array<Ability>
     */
    private $allSkills = [];

    /**
     * All items indexed by ID.
     *
     * @var array<Item>
     */
    private $allItems = [];

    /**
     * All conditions indexed by ID.
     *
     * @var array<Condition>
     */
    private $allConditions = [];

    /**
     * All events indexed by ID.
     *
     * @var array<Event>
     */
    private $allEvents = [];

    /**
     * All effects indexed by ID.
     *
     * @var array<Effect>
     */
    private $allEffects = [];

    /**
     * All abilities indexed by ID.
     *
     * @var array<Ability>
     */
    private $allAbilities = [];

    /**
     * Constructor for CharacterService.
     *
     * @param AbilityMapper   $abilityMapper   Ability mapper.
     * @param CharacterMapper $characterMapper Character mapper.
     * @param ConditionMapper $conditionMapper Condition mapper.
     * @param EffectMapper    $effectMapper    Effect mapper.
     * @param EventMapper     $eventMapper     Event mapper.
     * @param ItemMapper      $itemMapper      Item mapper.
     */
    public function __construct(
        private AbilityMapper $abilityMapper,
        private CharacterMapper $characterMapper,
        private ConditionMapper $conditionMapper,
        private EffectMapper $effectMapper,
        private EventMapper $eventMapper,
        private ItemMapper $itemMapper
    ) {
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
                function ($carry, $skill) {
                    $carry[$skill['id']] = $skill;
                    return $carry;
                },
                []
        );

        // Get all items and index them by ID.
        $items          = $this->objectService->getObjects('item');
        $this->allItems = array_reduce(
            $items,
                function ($carry, $item) {
                    $carry[$item['id']] = $item;
                    return $carry;
                },
                []
        );

        // Get all conditions and index them by ID.
        $conditions          = $this->objectService->getObjects('condition');
        $this->allConditions = array_reduce(
            $conditions,
                function ($carry, $condition) {
                    $carry[$condition['id']] = $condition;
                    return $carry;
                },
                []
        );

        // Get all events and index them by ID.
        $events          = $this->objectService->getObjects('event');
        $this->allEvents = array_reduce(
            $events,
                function ($carry, $event) {
                    $carry[$event['id']] = $event;
                    return $carry;
                },
                []
        );

        // Get all effects and index them by ID.
        $effects          = $this->objectService->getObjects('effect');
        $this->allEffects = array_reduce(
            $effects,
                function ($carry, $effect) {
                    $carry[$effect['id']] = $effect;
                    return $carry;
                },
                []
        );

        // Get all abilities and index them by ID.
        $abilities          = $this->objectService->getObjects('ability');
        $this->allAbilities = array_reduce(
            $abilities,
                function ($carry, $ability) {
                    $carry[$ability['id']] = $ability;
                    return $carry;
                },
                []
        );
    }//end loadAllEntities()

    /**
     * Calculate stats for all characters.
     *
     * @return array Updated array of Character objects.
     */
    public function calculateAllCharacters(): array
    {
        $characters        = $this->characterMapper->findAll();
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
            $abilityScores[$ability['id']] = [
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
                $skill = $this->allSkills[$skillId] ?? null;
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
                $item = $this->allItems[$itemId] ?? null;
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
                $condition = $this->allConditions[$conditionId] ?? null;
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
                $event = $this->allEvents[$eventId] ?? null;
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
        if (empty($effects) === true) {
            return;
        }

        foreach ($effects as $effectId) {
            // Skip if effectId is null.
            if ($effectId === null) {
                continue;
            }

            $effect = $this->allEffects[$effectId] ?? null;
            if ($effect !== null) {
                $this->calculateEffect(abilities: $abilities, effect: $effect);
            }
        }
    }//end applyEffects()

    /**
     * Calculate and apply a single effect.
     *
     * @param array $abilities Reference to the abilities array.
     * @param array $effect    Effect array containing stat_id, modifier and modification.
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
        foreach ($effectAbilities as $abilityId) {
            // Skip if abilityId is null.
            if ($abilityId === null) {
                continue;
            }

            if (isset($abilities[$abilityId]['value']) === false) {
                $abilities[$abilityId]['value'] = 0;
            } else if (is_int($abilities[$abilityId]['value']) === false) {
                $abilities[$abilityId]['value'] = (int) $abilities[$abilityId]['value'];
            }

            // Get current value and modifiers.
            $currentValue = $abilities[$abilityId]['value'];
            // Get modifier value from effect, defaulting to 0 if not set.
            $modifier = (int) ($effect['modifier'] ?? 0);
            // Get modification type, defaulting to 'positive' if not set.
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
