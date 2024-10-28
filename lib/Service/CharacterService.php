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
use TCPDF;

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
    ) {
        $this->loadAllEntities();
    }

    private function loadAllEntities(): void
    {
        $this->allSkills = array_reduce($this->skillMapper->findAll(), function($carry, $skill) {
            $carry[$skill->getId()] = $skill;
            return $carry;
        }, []);

        $this->allItems = array_reduce($this->itemMapper->findAll(), function($carry, $item) {
            $carry[$item->getId()] = $item;
            return $carry;
        }, []);

        $this->allConditions = array_reduce($this->conditionMapper->findAll(), function($carry, $condition) {
            $carry[$condition->getId()] = $condition;
            return $carry;
        }, []);

        $this->allEvents = array_reduce($this->eventMapper->findAll(), function($carry, $event) {
            $carry[$event->getId()] = $event;
            return $carry;
        }, []);

        $this->allEffects = array_reduce($this->effectMapper->findAll(), function($carry, $effect) {
            $carry[$effect->getId()] = $effect;
            return $carry;
        }, []);

        $this->allAbilities = array_reduce($this->abilityMapper->findAll(), function($carry, $ability) {
            $carry[$ability->getId()] = $ability;
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
     * Calculate stats for a single character
     *
     * @param Character $character Character object
     * @return Character Updated Character object
     */
    public function calculateCharacter(Character $character): Character
    {
        // Create an array of abilities with their base scores
        $abilityScores = [];

        foreach ($this->allAbilities as $ability) {
            $abilityScores[$ability->getId()] = [
                'name' => $ability->getName(),
                'value' => $ability->getBase() ?? 0
            ];
        }

        // Apply effects from skills
        foreach ($character->getSkills() as $skillId) {
            $skill = $this->allSkills[$skillId] ?? null;
            if ($skill) {
                $this->applyEffects($abilityScores, $skill->getEffects());
            }
        }

        // Apply effects from items
        foreach ($character->getItems() as $itemId) {
            $item = $this->allItems[$itemId] ?? null;
            if ($item) {
                $this->applyEffects($abilityScores, $item->getEffects());
            }
        }

        // Apply effects from conditions
        foreach ($character->getConditions() as $conditionId) {
            $condition = $this->allConditions[$conditionId] ?? null;
            if ($condition) {
                $this->applyEffects($abilityScores, $condition->getEffects());
            }
        }

        // Apply effects from events
        foreach ($character->getEvents() as $eventId) {
            $event = $this->allEvents[$eventId] ?? null;
            if ($event) {
                $this->applyEffects($abilityScores, $event->getEffects());
            }
        }

        // Update character's abilities
        $character->setStats($abilityScores);

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
     * @param Effect $effect Effect object
     */
    private function calculateEffect(array &$abilities, Effect $effect): void
    {
        $statId = $effect->getStatId();
        $modifier = $effect->getModifier();
        $modification = $effect->getModification();

        if (!isset($abilities[$statId]['value'])) {
            $abilities[$statId]['value'] = 0;
        }

        $currentValue = $abilities[$statId]['value'];

        if ($modification === 'positive') {
            $abilities[$statId]['value'] = $currentValue + $modifier;
        } elseif ($modification === 'negative') {
            $abilities[$statId]['value'] = $currentValue - $modifier;
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
