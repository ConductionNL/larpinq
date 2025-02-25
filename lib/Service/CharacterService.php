<?php

namespace OCA\LarpingApp\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use OCP\IURLGenerator;
use Symfony\Component\Uid\Uuid;
use Mpdf\Mpdf;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @class CharacterService
 * @category Service
 * @package LarpingApp
 * @author Conduction Team
 * @copyright 2023 Conduction
 * @license EUPL-1.2
 * @version 1.0.0
 * @link https://github.com/OpenCatalogi/larping-app
 * 
 * Service for handling character-related operations
 */
class CharacterService
{
    private array $allSkills = [];
    private array $allItems = [];
    private array $allConditions = [];
    private array $allEvents = [];
    private array $allEffects = [];
    private array $allAbilities = [];

    /**
     * Constructor for CharacterService
     * 
     * @param ObjectService $objectService The object service for interacting with objects
     */
    public function __construct(
        private readonly ObjectService $objectService
    ) {
        $this->loadAllEntities();
    }

    /**
     * Loads all entities needed for character calculations
     * 
     * @return void
     */
    private function loadAllEntities(): void
    {
        // Get all skills and index them by ID
        $skills = $this->objectService->getObjects('skill');
        $this->allSkills = array_reduce($skills, function($carry, $skill) {
            $carry[$skill['id']] = $skill;
            return $carry;
        }, []);

        // Get all items and index them by ID
        $items = $this->objectService->getObjects('item');
        $this->allItems = array_reduce($items, function($carry, $item) {
            $carry[$item['id']] = $item;
            return $carry;
        }, []);

        // Get all conditions and index them by ID
        $conditions = $this->objectService->getObjects('condition');
        $this->allConditions = array_reduce($conditions, function($carry, $condition) {
            $carry[$condition['id']] = $condition;
            return $carry;
        }, []);

        // Get all events and index them by ID
        $events = $this->objectService->getObjects('event');
        $this->allEvents = array_reduce($events, function($carry, $event) {
            $carry[$event['id']] = $event;
            return $carry;
        }, []);

        // Get all effects and index them by ID
        $effects = $this->objectService->getObjects('effect');
        $this->allEffects = array_reduce($effects, function($carry, $effect) {
            $carry[$effect['id']] = $effect;
            return $carry;
        }, []);

        // Get all abilities and index them by ID
        $abilities = $this->objectService->getObjects('ability');
        $this->allAbilities = array_reduce($abilities, function($carry, $ability) {
            $carry[$ability['id']] = $ability;
            return $carry;
        }, []);
    }

    /**
     * Calculate stats for all characters and save the updated characters
     *
     * @return array Updated array of Character objects
     * @throws \Exception If saving characters fails
     */
    public function calculateAllCharacters(): array
    {
        // Get all characters
        $characters = $this->objectService->getObjects('character');
        $updatedCharacters = [];
        
        // Calculate stats for each character and save the updated character
        foreach ($characters as $character) {
            // Calculate the character stats
            $updatedCharacter = $this->calculateCharacter($character);
            
            // Save the updated character to the database
            $this->objectService->saveObject('character', $updatedCharacter);
            
            // Add to the array of updated characters
            $updatedCharacters[] = $updatedCharacter;
        }
        
        return $updatedCharacters;
    }

    /**
     * Calculate stats for a single character array
     *
     * @param array $character Character data array
     * @return array Updated character data array with calculated stats
     */
    public function calculateCharacter(array $character): array 
    {
        // Create an array of abilities with their base scores
        $abilityScores = [];

        // Initialize ability scores from base values
        foreach ($this->allAbilities as $ability) {
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
                $skill = $this->allSkills[$skillId] ?? null;
                if ($skill && isset($skill['effects']) && !empty($skill['effects'])) {
                    $this->applyEffects(
                        $abilityScores, 
                        $skill['effects'], 
                        'skill', 
                        $skill['name'] ?? 'Unknown Skill'
                    );
                }
            }
        }

        // Apply effects from items if character has any
        if (isset($character['items']) && is_array($character['items']) && !empty($character['items'])) {
            foreach ($character['items'] as $itemId) {
                $item = $this->allItems[$itemId] ?? null;
                if ($item && isset($item['effects']) && !empty($item['effects'])) {
                    $this->applyEffects(
                        $abilityScores, 
                        $item['effects'], 
                        'item', 
                        $item['name'] ?? 'Unknown Item'
                    );
                }
            }
        }

        // Apply effects from conditions if character has any
        if (isset($character['conditions']) && is_array($character['conditions']) && !empty($character['conditions'])) {
            foreach ($character['conditions'] as $conditionId) {
                $condition = $this->allConditions[$conditionId] ?? null;
                if ($condition && isset($condition['effects']) && !empty($condition['effects'])) {
                    $this->applyEffects(
                        $abilityScores, 
                        $condition['effects'], 
                        'condition', 
                        $condition['name'] ?? 'Unknown Condition'
                    );
                }
            }
        }

        // Apply effects from events if character has any
        if (isset($character['events']) && is_array($character['events']) && !empty($character['events'])) {
            foreach ($character['events'] as $eventId) {
                $event = $this->allEvents[$eventId] ?? null;
                if ($event && isset($event['effects']) && !empty($event['effects'])) {
                    $this->applyEffects(
                        $abilityScores, 
                        $event['effects'], 
                        'event', 
                        $event['name'] ?? 'Unknown Event'
                    );
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
     * @param array $abilities Reference to the abilities array
     * @param array|null $effects Array of effect IDs
     * @param string $sourceType The type of the source (skill, item, condition, event)
     * @param string $sourceName The name of the source
     * @return void
     */
    private function applyEffects(array &$abilities, ?array $effects, string $sourceType = '', string $sourceName = ''): void
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
            
            $effect = $this->allEffects[$effectId] ?? null;
            if ($effect) {
                $this->calculateEffect($abilities, $effect, $sourceType, $sourceName);
            }
        }
    }

    /**
     * Calculate and apply a single effect
     *
     * @param array $abilities Reference to the abilities array
     * @param array $effect Effect array containing stat_id, modifier and modification
     * @param string $sourceType The type of the source (skill, item, condition, event)
     * @param string $sourceName The name of the source
     * @return void
     */
    private function calculateEffect(array &$abilities, array $effect, string $sourceType = '', string $sourceName = ''): void
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

            // Add audit trail with source information
            $abilities[$abilityId]['audit'][] = [
                'type' => 'effect',
                'effect' => $effect,
                'type' => $sourceType,
                'name' => $sourceName,
                'old' => $currentValue,
                'new' => $abilities[$abilityId]['value']
            ];
        }
    }

    /**
     * Create a PDF from a character using a specified template
     *
     * @param array $character Character data array containing character information
     * @param array $template Template data array containing PDF layout configuration
     * @return string Generated PDF content as string
     * @throws \Exception If PDF generation fails
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
