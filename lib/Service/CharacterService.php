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
 * @version   0.1.0
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

namespace OCA\LarpingApp\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use OCP\IURLGenerator;
use Symfony\Component\Uid\Uuid;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Exception;

/**
 * Service class for character-related operations
 *
 * Provides functionality for character calculations, PDF generation,
 * and other character-specific operations.
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
     * Collection of all skills
     * 
     * @var array<string, array>
     */
    private $_allSkills = [];
    
    /**
     * Collection of all items
     * 
     * @var array<string, array>
     */
    private $_allItems = [];
    
    /**
     * Collection of all conditions
     * 
     * @var array<string, array>
     */
    private $_allConditions = [];
    
    /**
     * Collection of all events
     * 
     * @var array<string, array>
     */
    private $_allEvents = [];
    
    /**
     * Collection of all effects
     * 
     * @var array<string, array>
     */
    private $_allEffects = [];
    
    /**
     * Collection of all abilities
     * 
     * @var array<string, array>
     */
    private $_allAbilities = [];

    /**
     * Constructor for CharacterService
     *
     * @param ObjectService $objectService Object service for retrieving entities
     */
    public function __construct(
        private readonly ObjectService $objectService
    ) {
        $this->_loadAllEntities();
    }

    /**
     * Load all entities needed for character calculations
     * 
     * Loads skills, items, conditions, events, effects, and abilities from the object service
     * and indexes them by ID for quick access during character calculations.
     * 
     * @return void
     */
    private function _loadAllEntities(): void
    {
        // Get all skills and index them by ID
        $skills = $this->objectService->getObjects('skill');
        $this->_allSkills = array_reduce(
            $skills,
            function ($carry, $skill) {
                $carry[$skill['id']] = $skill;
                return $carry;
            },
            []
        );

        // Get all items and index them by ID
        $items = $this->objectService->getObjects('item');
        $this->_allItems = array_reduce(
            $items,
            function ($carry, $item) {
                $carry[$item['id']] = $item;
                return $carry;
            },
            []
        );

        // Get all conditions and index them by ID
        $conditions = $this->objectService->getObjects('condition');
        $this->_allConditions = array_reduce(
            $conditions,
            function ($carry, $condition) {
                $carry[$condition['id']] = $condition;
                return $carry;
            },
            []
        );

        // Get all events and index them by ID
        $events = $this->objectService->getObjects('event');
        $this->_allEvents = array_reduce(
            $events,
            function ($carry, $event) {
                $carry[$event['id']] = $event;
                return $carry;
            },
            []
        );

        // Get all effects and index them by ID
        $effects = $this->objectService->getObjects('effect');
        $this->_allEffects = array_reduce(
            $effects,
            function ($carry, $effect) {
                $carry[$effect['id']] = $effect;
                return $carry;
            },
            []
        );

        // Get all abilities and index them by ID
        $abilities = $this->objectService->getObjects('ability');
        $this->_allAbilities = array_reduce(
            $abilities,
            function ($carry, $ability) {
                $carry[$ability['id']] = $ability;
                return $carry;
            },
            []
        );
    }

    /**
     * Calculate stats for a character
     * 
     * Processes a character's skills, items, conditions, and events to calculate
     * their final ability scores based on all applicable effects.
     *
     * @param array $character Character data to calculate stats for
     * 
     * @return array Updated character data with calculated stats
     */
    public function calculateCharacter(array $character): array 
    {
        // Initialize ability scores with base values from character (abilites are calculated and not retrieved from the database)
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
                    $this->_applyEffects($abilityScores, $skill['effects']);
                }
            }
        }

        // Apply effects from items if character has any
        if (isset($character['items']) && is_array($character['items']) && !empty($character['items'])) {
            foreach ($character['items'] as $itemId) {
                $item = $this->_allItems[$itemId] ?? null;
                if ($item && isset($item['effects']) && !empty($item['effects'])) {
                    $this->_applyEffects($abilityScores, $item['effects']);
                }
            }
        }

        // Apply effects from conditions if character has any
        if (isset($character['conditions']) && is_array($character['conditions']) && !empty($character['conditions'])) {
            foreach ($character['conditions'] as $conditionId) {
                $condition = $this->_allConditions[$conditionId] ?? null;
                if ($condition && isset($condition['effects']) && !empty($condition['effects'])) {
                    $this->_applyEffects($abilityScores, $condition['effects']);
                }
            }
        }

        // Apply effects from events if character has any
        if (isset($character['events']) && is_array($character['events']) && !empty($character['events'])) {
            foreach ($character['events'] as $eventId) {
                $event = $this->_allEvents[$eventId] ?? null;
                if ($event && isset($event['effects']) && !empty($event['effects'])) {
                    $this->_applyEffects($abilityScores, $event['effects']);
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
     * Processes a list of effect IDs and applies them to the abilities array.
     *
     * @param array      $abilities Reference to the abilities array
     * @param array|null $effects   Array of effect IDs
     * 
     * @return void
     */
    private function _applyEffects(array &$abilities, ?array $effects): void
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
                $this->_calculateEffect($abilities, $effect);
            }
        }
    }

    /**
     * Calculate and apply a single effect
     *
     * Applies a specific effect to one or more abilities based on the effect configuration.
     *
     * @param array $abilities Reference to the abilities array
     * @param array $effect    Effect array containing stat_id, modifier and modification
     * 
     * @return void
     */
    private function _calculateEffect(array &$abilities, array $effect): void
    {
        // Initialize array to track affected abilities
        $effectAbilities = isset($effect['abilities']) && is_array($effect['abilities']) 
            ? $effect['abilities'] : [];

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
     * Generates a PDF document for a character sheet based on the provided template.
     *
     * @param array $character Character data array containing character information
     * @param array $template  Template data array containing PDF layout configuration
     * 
     * @psalm-param array{name: string, stats: array<string, array{name: string, value: int}>, skills?: array, items?: array, conditions?: array, events?: array} $character
     * @psalm-param array{name: string, orientation?: string, format?: string, sections?: array, template: string} $template
     * 
     * @return Mpdf Generated PDF object
     * 
     * @throws Exception If PDF generation fails
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
