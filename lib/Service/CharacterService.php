<?php

declare(strict_types=1);

/**
 * Character service implementation
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

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

use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// And in case of open registers
use OCA\OpenRegister\Db\ObjectEntity;

/**
 * Service class for character-related operations
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
     * @var array<Ability>
     */
    private $_allSkills = [];
    
    /**
     * @var array<Item>
     */
    private $_allItems = [];
    
    /**
     * @var array<Condition>
     */
    private $_allConditions = [];
    
    /**
     * @var array<Event>
     */
    private $_allEvents = [];
    
    /**
     * @var array<Effect>
     */
    private $_allEffects = [];
    
    /**
     * @var array<Ability>
     */
    private $_allAbilities = [];

    /**
     * Constructor for CharacterService
     *
     * @param AbilityMapper   $abilityMapper   Ability mapper
     * @param CharacterMapper $characterMapper Character mapper
     * @param ConditionMapper $conditionMapper Condition mapper
     * @param EffectMapper    $effectMapper    Effect mapper
     * @param EventMapper     $eventMapper     Event mapper
     * @param ItemMapper      $itemMapper      Item mapper
     */
    public function __construct(
        private AbilityMapper $abilityMapper,
        private CharacterMapper $characterMapper,
        private ConditionMapper $conditionMapper,
        private EffectMapper $effectMapper,
        private EventMapper $eventMapper,
        private ItemMapper $itemMapper
    ) {
        $this->_loadAllEntities();
    }

    private function _loadAllEntities(): void
    {
        // Get all skills and index them by ID
        $skills = $this->objectService->getObjects('skill');
        $this->_allSkills = array_reduce(
            $skills, function ($carry, $skill) {
                $carry[$skill['id']] = $skill;
                return $carry;
            }, []
        );

        // Get all items and index them by ID
        $items = $this->objectService->getObjects('item');
        $this->_allItems = array_reduce(
            $items, function ($carry, $item) {
                $carry[$item['id']] = $item;
                return $carry;
            }, []
        );

        // Get all conditions and index them by ID
        $conditions = $this->objectService->getObjects('condition');
        $this->_allConditions = array_reduce(
            $conditions, function ($carry, $condition) {
                $carry[$condition['id']] = $condition;
                return $carry;
            }, []
        );

        // Get all events and index them by ID
        $events = $this->objectService->getObjects('event');
        $this->_allEvents = array_reduce(
            $events, function ($carry, $event) {
                $carry[$event['id']] = $event;
                return $carry;
            }, []
        );

        // Get all effects and index them by ID
        $effects = $this->objectService->getObjects('effect');
        $this->_allEffects = array_reduce(
            $effects, function ($carry, $effect) {
                $carry[$effect['id']] = $effect;
                return $carry;
            }, []
        );

        // Get all abilities and index them by ID
        $abilities = $this->objectService->getObjects('ability');
        $this->_allAbilities = array_reduce(
            $abilities, function ($carry, $ability) {
                $carry[$ability['id']] = $ability;
                return $carry;
            }, []
        );
    }

    /**
     * Calculate stats for all characters
     *
     * @return array Updated array of Character objects
     */
    public function calculateAllCharacters(): array
    {
        $characters = $this->characterMapper->findAll();
        $updatedCharacters = [];
        foreach ($characters as $character) {
            $updatedCharacters[] = $this->calculateCharacter($character);
        }
        return $updatedCharacters;
    }

    /**
     * Calculate stats for a single character array
     *
     * @param  array $character Character data array
     * @return array Updated character data array with calculated stats
     */
    public function calculateCharacter(array $character): array 
    {
        // Create an array of abilities with their base scores
        $abilityScores = [];

        // Initialize ability scores from base values
        foreach ($this->_allAbilities as $ability) {
            $abilityScores[$ability['id']] = [
                'name' => $ability['name'],
                'base' => $ability['base'] ?? 0,
                'value' => $ability['base'] ?? 0,
                'audit' => []
            ];
        }

        // Apply effects from skills if character has any
        if (isset($character['skills']) && is_array($character['skills']) && !empty($character['skills'])) {
            foreach ($character['skills'] as $skillId) {
                $skill = $this->_allSkills[$skillId] ?? null;
                if ($skill && isset($skill['effects']) && !empty($skill['effects'])) {
                    $this->applyEffects($abilityScores, $skill['effects']);
                }
            }
        }

        // Apply effects from items if character has any
        if (isset($character['items']) && is_array($character['items']) && !empty($character['items'])) {
            foreach ($character['items'] as $itemId) {
                $item = $this->_allItems[$itemId] ?? null;
                if ($item && isset($item['effects']) && !empty($item['effects'])) {
                    $this->applyEffects($abilityScores, $item['effects']);
                }
            }
        }

        // Apply effects from conditions if character has any
        if (isset($character['conditions']) && is_array($character['conditions']) && !empty($character['conditions'])) {
            foreach ($character['conditions'] as $conditionId) {
                $condition = $this->_allConditions[$conditionId] ?? null;
                if ($condition && isset($condition['effects']) && !empty($condition['effects'])) {
                    $this->applyEffects($abilityScores, $condition['effects']);
                }
            }
        }

        // Apply effects from events if character has any
        if (isset($character['events']) && is_array($character['events']) && !empty($character['events'])) {
            foreach ($character['events'] as $eventId) {
                $event = $this->_allEvents[$eventId] ?? null;
                if ($event && isset($event['effects']) && !empty($event['effects'])) {
                    $this->applyEffects($abilityScores, $event['effects']);
                }
            }
        }

        // Update character array with calculated stats
        $character['stats'] = $abilityScores;

        return $character;
    }

    /**
     * Apply effects to abilities
     *
     * @param array      $abilities Reference to the abilities array
     * @param array|null $effects   Array of effect IDs
     */
    private function applyEffects(array &$abilities, ?array $effects): void
    {
        // Return early if effects is null or empty
        if (empty($effects)) {
            return;
        }

        foreach ($effects as $effectId) {
            // Skip if effectId is null
            if ($effectId === null) {
                continue;
            }
            
            $effect = $this->_allEffects[$effectId] ?? null;
            if ($effect) {
                $this->calculateEffect($abilities, $effect);
            }
        }
    }

    /**
     * Calculate and apply a single effect
     *
     * @param array $abilities Reference to the abilities array
     * @param array $effect    Effect array containing stat_id, modifier and modification
     */
    private function calculateEffect(array &$abilities, array $effect): void
    {
        // Initialize array to track affected abilities
        $effectAbilities = isset($effect['abilities']) && is_array($effect['abilities']) ? $effect['abilities'] : [];

        // Add stat_id to affected abilities if present and not null
        if (isset($effect['stat_id']) && $effect['stat_id'] !== null) {
            $effectAbilities[] = $effect['stat_id'];
        }

        // Skip if no abilities are affected
        if (empty($effectAbilities)) {
            return;
        }

        // Ensure each affected ability exists in abilities array
        foreach ($effectAbilities as $abilityId) {
            // Skip if abilityId is null
            if ($abilityId === null) {
                continue;
            }

            if (!isset($abilities[$abilityId]['value'])) {
                $abilities[$abilityId]['value'] = 0;
            } elseif (!is_int($abilities[$abilityId]['value'])) {
                $abilities[$abilityId]['value'] = (int) $abilities[$abilityId]['value'];
            }

            // Get current value and modifiers
            $currentValue = $abilities[$abilityId]['value'];
            // Get modifier value from effect, defaulting to 0 if not set
            $modifier = (int) ($effect['modifier'] ?? 0);
            // Get modification type, defaulting to 'positive' if not set
            $modification = $effect['modification'] ?? 'positive'; 

            // Apply modification based on type
            if ($modification === 'positive') {
                $abilities[$abilityId]['value'] = $currentValue + $modifier;
            } elseif ($modification === 'negative') {
                $abilities[$abilityId]['value'] = $currentValue - $modifier;
            }

            // Add audit trail
            $abilities[$abilityId]['audit'][] = [
                'type' => 'effect',
                'effect' => $effect,
                'old' => $currentValue,
                'new' => $abilities[$abilityId]['value']
            ];
        }
    }

    /**
     * Create a PDF from a character using a specified template
     *
     * @param       array $character Character data array containing character information
     * @param       array $template  Template data array containing PDF layout configuration
     * @return      string Generated PDF content as string
     * @throws      \Exception If PDF generation fails
     * @psalm-param array{name: string, stats: array<string, array{name: string, value: int}>, skills?: array, items?: array, conditions?: array, events?: array} $character
     * @psalm-param array{name: string, orientation?: string, format?: string, sections?: array} $template
     */
    public function createCharacterPdf(array $character, array $template): Mpdf
    {
        // Getting al the twig stuff going
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new Environment($loader);
        $template = $twig->createTemplate($template['template']);
        $html = $template->render(['character' => $character, 'template' => $template]);
        
        // Check if the directory exists, if not, create it
        if (file_exists(filename: '/tmp/mpdf') === false) {
            mkdir(directory: '/tmp/mpdf', recursive: true);
        }

        // Set permissions for the directory (ensure it's writable)
        chmod(filename: '/tmp/mpdf', permissions: 0777);

        // Initialize mPDF
        $mpdf = new Mpdf(config: ['tempDir' => '/tmp/mpdf']);

        // Write HTML to PDF
        $mpdf->WriteHTML(html: $html);

        return $mpdf;
    }
}
