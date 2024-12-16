<?php

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

use TCPDF;
// And in case of open registers
use OCA\OpenRegister\Db\ObjectEntity;

class CharacterService
{
    private array $allSkills = [];
    private array $allItems = [];
    private array $allConditions = [];
    private array $allEvents = [];
    private array $allEffects = [];
    private array $allAbilities = [];

    public function __construct(
        private readonly CharacterMapper $characterMapper,
        private readonly AbilityMapper $abilityMapper,		
        private readonly SkillMapper $skillMapper,
        private readonly ItemMapper $itemMapper,
        private readonly ConditionMapper $conditionMapper,
        private readonly EventMapper $eventMapper,
        private readonly EffectMapper $effectMapper,
        private readonly ObjectService $objectService
    ) {
        $this->loadAllEntities();
    }

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
                'value' => $ability['base'] ?? 0
            ];
        }

        // Apply effects from skills if character has any
        if (isset($character['skills']) && is_array($character['skills'])) {
            foreach ($character['skills'] as $skillId) {
                $skill = $this->allSkills[$skillId] ?? null;
                if ($skill) {
                    $this->applyEffects($abilityScores, $skill['effects']);
                }
            }
        }

        // Apply effects from items if character has any
        if (isset($character['items']) && is_array($character['items'])) {
            foreach ($character['items'] as $itemId) {
                $item = $this->allItems[$itemId] ?? null;
                if ($item) {
                    $this->applyEffects($abilityScores, $item['effects']);
                }
            }
        }

        // Apply effects from conditions if character has any
        if (isset($character['conditions']) && is_array($character['conditions'])) {
            foreach ($character['conditions'] as $conditionId) {
                $condition = $this->allConditions[$conditionId] ?? null;
                if ($condition) {
                    $this->applyEffects($abilityScores, $condition['effects']);
                }
            }
        }

        // Apply effects from events if character has any
        if (isset($character['events']) && is_array($character['events'])) {
            foreach ($character['events'] as $eventId) {
                $event = $this->allEvents[$eventId] ?? null;
                if ($event) {
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
     * @param array $abilities Reference to the abilities array
     * @param array $effects Array of effect IDs
     */
    private function applyEffects(array &$abilities, array $effects): void
    {
        foreach ($effects as $effectId) {
            $effect = $this->allEffects[$effectId] ?? null;
            if ($effect) {
                $this->calculateEffect($abilities, $effect);
            }
        }
    }

    /**
     * Calculate and apply a single effect
     *
     * @param array $abilities Reference to the abilities array
     * @param array $effect Effect array containing stat_id, modifier and modification
     */
    private function calculateEffect(array &$abilities, array $effect): void
    {
        // Initialize array to track affected abilities
        $effectAbilities = isset($effect['abilities']) && is_array($effect['abilities']) ? $effect['abilities'] : [];

        // Add stat_id to affected abilities if present
        if (isset($effect['stat_id'])) {
            $effectAbilities[] = $effect['stat_id'];
        }

        // Ensure each affected ability exists in abilities array
        foreach ($effectAbilities as $abilityId) {
            if (!isset($abilities[$abilityId]['value'])) {
                $abilities[$abilityId]['value'] = 0;
            }

            // Get current value and modifiers
            $currentValue = $abilities[$abilityId]['value'];
            // Get modifier value from effect, defaulting to 0 if not set
            $modifier = $effect['modifier'] ?? 0;
            // Get modification type, defaulting to 'positive' if not set
            $modification = $effect['modification'] ?? 'positive'; 

            // Apply modification based on type
            if ($modification === 'positive') {
                $abilities[$abilityId]['value'] = $currentValue + $modifier;
            } elseif ($modification === 'negative') {
                $abilities[$abilityId]['value'] = $currentValue - $modifier;
            }
        }
    }

    /**
     * Create a PDF from a character
     *
     * @param Character $character Character object
     * @return string PDF content
     * @throws \Exception If PDF generation fails
     */
    public function createCharacterPdf(Character $character): string
    {
        try {
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // Set document information
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('LarpingApp');
            $pdf->SetTitle('Character Sheet: ' . $character->getName());
            $pdf->SetSubject('Character Sheet');

            // Add a page
            $pdf->AddPage();

            // Set font
            $pdf->SetFont('helvetica', '', 12);

            // Add content
            $pdf->Cell(0, 10, 'Character Sheet: ' . $character->getName(), 0, 1, 'C');
            $pdf->Ln(10);


            // Add stats
            $pdf->SetFont('', 'B', 14);
            $pdf->Cell(0, 10, 'Stats', 0, 1);
            $pdf->SetFont('', '', 12);
            foreach ($character->getStats() as $statId => $stat) {
                $pdf->Cell(0, 10, $stat['name'] . ': ' . $stat['value'], 0, 1);
            }
            $pdf->Ln(10);

            // Add skills, items, conditions, and events
            $sections = [
                'Skills' => $character->getSkills(),
                'Items' => $character->getItems(),
                'Conditions' => $character->getConditions(),
                'Events' => $character->getEvents()
            ];

            foreach ($sections as $title => $ids) {
                $pdf->SetFont('', 'B', 14);
                $pdf->Cell(0, 10, $title, 0, 1);
                $pdf->SetFont('', '', 12);
                foreach ($ids as $id) {
                    $entity = $this->{'all' . $title}[$id] ?? null;
                    if ($entity) {
                        $pdf->Cell(0, 10, $entity->getName(), 0, 1);
                    }
                }
                $pdf->Ln(10);
            }

            return $pdf->Output('character_sheet.pdf', 'S');
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('PDF Generation Failed: ' . $e->getMessage());
            
            // Throw a new exception with a generic error message
            throw new \Exception('PDF Generation Failed', 0, $e);
        }
    }
}
