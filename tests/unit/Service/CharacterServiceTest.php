<?php

/**
 * Unit tests for CharacterService.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Service;

use OCA\LarpingApp\Service\CharacterService;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CharacterService stat calculation engine.
 */
class CharacterServiceTest extends TestCase
{

    private CharacterService $service;
    private RegisterObjectFetcher $objectFetcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->objectFetcher = $this->createMock(RegisterObjectFetcher::class);

        // Return empty arrays for all entity types by default.
        $this->objectFetcher->method('getObjects')
            ->willReturnCallback(function (string $type): array {
                return [];
            });

        $this->service = new CharacterService($this->objectFetcher);
    }

    /**
     * Helper to create a CharacterService with specific entity data.
     */
    private function createServiceWithData(
        array $abilities = [],
        array $effects = [],
        array $skills = [],
        array $items = [],
        array $conditions = [],
        array $events = []
    ): CharacterService {
        $fetcher = $this->createMock(RegisterObjectFetcher::class);
        $fetcher->method('getObjects')
            ->willReturnCallback(function (string $type) use ($abilities, $effects, $skills, $items, $conditions, $events): array {
                return match ($type) {
                    'ability' => $abilities,
                    'effect' => $effects,
                    'skill' => $skills,
                    'item' => $items,
                    'condition' => $conditions,
                    'event' => $events,
                    default => [],
                };
            });

        return new CharacterService($fetcher);
    }

    public function testCalculateCharacterWithNoAbilities(): void
    {
        $character = ['id' => 'char-1', 'name' => 'Test'];
        $result = $this->service->calculateCharacter($character);

        self::assertArrayHasKey('stats', $result);
        self::assertEmpty($result['stats']);
    }

    public function testCalculateCharacterInitializesAbilityScores(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
            ['id' => 'abil-2', 'name' => 'Mana', 'base' => 5],
        ];

        $service = $this->createServiceWithData(abilities: $abilities);
        $character = ['id' => 'char-1', 'name' => 'Fighter'];
        $result = $service->calculateCharacter($character);

        self::assertArrayHasKey('abil-1', $result['stats']);
        self::assertSame('Strength', $result['stats']['abil-1']['name']);
        self::assertSame(10, $result['stats']['abil-1']['base']);
        self::assertSame(10, $result['stats']['abil-1']['value']);
        self::assertEmpty($result['stats']['abil-1']['audit']);

        self::assertArrayHasKey('abil-2', $result['stats']);
        self::assertSame(5, $result['stats']['abil-2']['base']);
        self::assertSame(5, $result['stats']['abil-2']['value']);
    }

    public function testCalculateCharacterAppliesPositiveSkillEffect(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $effects = [
            ['id' => 'eff-1', 'name' => 'Str Bonus', 'modifier' => 5, 'modification' => 'positive', 'abilities' => ['abil-1']],
        ];
        $skills = [
            ['id' => 'skill-1', 'name' => 'Swordsmanship', 'effects' => ['eff-1']],
        ];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
        $character = ['id' => 'char-1', 'name' => 'Fighter', 'skills' => ['skill-1']];
        $result = $service->calculateCharacter($character);

        self::assertSame(15, $result['stats']['abil-1']['value']);
        self::assertCount(1, $result['stats']['abil-1']['audit']);
        self::assertSame(10, $result['stats']['abil-1']['audit'][0]['old']);
        self::assertSame(15, $result['stats']['abil-1']['audit'][0]['new']);
    }

    public function testCalculateCharacterAppliesNegativeEffect(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'HP', 'base' => 20],
        ];
        $effects = [
            ['id' => 'eff-1', 'name' => 'Poison', 'modifier' => 3, 'modification' => 'negative', 'abilities' => ['abil-1']],
        ];
        $conditions = [
            ['id' => 'cond-1', 'name' => 'Poisoned', 'effects' => ['eff-1']],
        ];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, conditions: $conditions);
        $character = ['id' => 'char-1', 'name' => 'Victim', 'conditions' => ['cond-1']];
        $result = $service->calculateCharacter($character);

        self::assertSame(17, $result['stats']['abil-1']['value']);
    }

    public function testCalculateCharacterAppliesEffectsFromMultipleEntityTypes(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $effects = [
            ['id' => 'eff-1', 'modifier' => 2, 'modification' => 'positive', 'abilities' => ['abil-1']],
            ['id' => 'eff-2', 'modifier' => 3, 'modification' => 'positive', 'abilities' => ['abil-1']],
            ['id' => 'eff-3', 'modifier' => 1, 'modification' => 'negative', 'abilities' => ['abil-1']],
        ];
        $skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];
        $items = [['id' => 'item-1', 'effects' => ['eff-2']]];
        $conditions = [['id' => 'cond-1', 'effects' => ['eff-3']]];

        $service = $this->createServiceWithData(
            abilities: $abilities,
            effects: $effects,
            skills: $skills,
            items: $items,
            conditions: $conditions
        );

        $character = [
            'id' => 'char-1',
            'skills' => ['skill-1'],
            'items' => ['item-1'],
            'conditions' => ['cond-1'],
        ];
        $result = $service->calculateCharacter($character);

        // 10 + 2 (skill) + 3 (item) - 1 (condition) = 14
        self::assertSame(14, $result['stats']['abil-1']['value']);
        self::assertCount(3, $result['stats']['abil-1']['audit']);
    }

    public function testCalculateCharacterSkipsOrphanedEntityReferences(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];

        $service = $this->createServiceWithData(abilities: $abilities);
        $character = [
            'id' => 'char-1',
            'skills' => ['nonexistent-skill'],
            'items' => ['nonexistent-item'],
        ];
        $result = $service->calculateCharacter($character);

        self::assertSame(10, $result['stats']['abil-1']['value']);
    }

    public function testCalculateCharacterSkipsOrphanedEffectReferences(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $skills = [
            ['id' => 'skill-1', 'effects' => ['nonexistent-effect']],
        ];

        $service = $this->createServiceWithData(abilities: $abilities, skills: $skills);
        $character = ['id' => 'char-1', 'skills' => ['skill-1']];
        $result = $service->calculateCharacter($character);

        self::assertSame(10, $result['stats']['abil-1']['value']);
    }

    public function testCalculateCharacterHandlesEmptySkillsArray(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];

        $service = $this->createServiceWithData(abilities: $abilities);
        $character = ['id' => 'char-1', 'skills' => []];
        $result = $service->calculateCharacter($character);

        self::assertSame(10, $result['stats']['abil-1']['value']);
    }

    public function testCalculateCharacterHandlesNullEffectsOnEntity(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $skills = [
            ['id' => 'skill-1', 'effects' => null],
        ];

        $service = $this->createServiceWithData(abilities: $abilities, skills: $skills);
        $character = ['id' => 'char-1', 'skills' => ['skill-1']];
        $result = $service->calculateCharacter($character);

        self::assertSame(10, $result['stats']['abil-1']['value']);
    }

    public function testCalculateCharacterUsesStatIdAsFallback(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $effects = [
            ['id' => 'eff-1', 'modifier' => 7, 'modification' => 'positive', 'stat_id' => 'abil-1'],
        ];
        $skills = [
            ['id' => 'skill-1', 'effects' => ['eff-1']],
        ];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
        $character = ['id' => 'char-1', 'skills' => ['skill-1']];
        $result = $service->calculateCharacter($character);

        self::assertSame(17, $result['stats']['abil-1']['value']);
    }

    public function testCalculateCharacterDefaultBaseIsZero(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Luck'],
        ];

        $service = $this->createServiceWithData(abilities: $abilities);
        $character = ['id' => 'char-1'];
        $result = $service->calculateCharacter($character);

        self::assertSame(0, $result['stats']['abil-1']['base']);
        self::assertSame(0, $result['stats']['abil-1']['value']);
    }

    public function testCalculateCharacterAppliesEventEffects(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'XP', 'base' => 0],
        ];
        $effects = [
            ['id' => 'eff-1', 'modifier' => 100, 'modification' => 'positive', 'abilities' => ['abil-1']],
        ];
        $events = [
            ['id' => 'evt-1', 'name' => 'Summer LARP', 'effects' => ['eff-1']],
        ];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, events: $events);
        $character = ['id' => 'char-1', 'events' => ['evt-1']];
        $result = $service->calculateCharacter($character);

        self::assertSame(100, $result['stats']['abil-1']['value']);
    }

    public function testCalculateAllCharacters(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];

        $fetcher = $this->createMock(RegisterObjectFetcher::class);
        $fetcher->method('getObjects')
            ->willReturnCallback(function (string $type) use ($abilities): array {
                if ($type === 'ability') {
                    return $abilities;
                }
                if ($type === 'character') {
                    return [
                        ['id' => 'char-1', 'name' => 'Fighter'],
                        ['id' => 'char-2', 'name' => 'Mage'],
                    ];
                }
                return [];
            });

        $service = new CharacterService($fetcher);
        $results = $service->calculateAllCharacters();

        self::assertCount(2, $results);
        self::assertArrayHasKey('stats', $results[0]);
        self::assertArrayHasKey('stats', $results[1]);
    }
}
