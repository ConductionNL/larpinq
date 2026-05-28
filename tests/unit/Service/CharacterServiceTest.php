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
use Psr\Log\LoggerInterface;

/**
 * Tests for the CharacterService stat calculation engine.
 */
class CharacterServiceTest extends TestCase
{

    private CharacterService $service;
    private RegisterObjectFetcher $objectFetcher;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->objectFetcher = $this->createMock(RegisterObjectFetcher::class);
        $this->logger        = $this->createMock(LoggerInterface::class);

        // Return empty arrays for all entity types by default.
        $this->objectFetcher->method('getObjects')
            ->willReturnCallback(function (string $type): array {
                return [];
            });

        $this->service = new CharacterService($this->objectFetcher, $this->logger);
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

        return new CharacterService($fetcher, $this->createMock(LoggerInterface::class));
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
        // Audit stores id/name, not the full effect object.
        self::assertSame('eff-1', $result['stats']['abil-1']['audit'][0]['effectId']);
        self::assertSame('Str Bonus', $result['stats']['abil-1']['audit'][0]['effectName']);
        self::assertArrayNotHasKey('effect', $result['stats']['abil-1']['audit'][0]);
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

        $service = new CharacterService($fetcher, $this->createMock(LoggerInterface::class));
        $results = $service->calculateAllCharacters();

        self::assertCount(2, $results);
        self::assertArrayHasKey('stats', $results[0]);
        self::assertArrayHasKey('stats', $results[1]);
    }

    // -----------------------------------------------------------------------
    // #208 — non-cumulative dedup + stat_id double-apply fix
    // -----------------------------------------------------------------------

    public function testEffectWithMissingCumulativeFieldDefaultsToNonCumulative(): void
    {
        // When the 'cumulative' field is entirely absent from the effect array the
        // schema default is 'non-cumulative'. The same effect attached to both a skill
        // and an item must therefore apply ONCE, not twice. Fixes C1 (wave-9).
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $effects = [
            [
                'id'           => 'eff-missing-cumulative',
                'modifier'     => 5,
                'modification' => 'positive',
                // 'cumulative' key intentionally absent — no key, no default override.
                'abilities'    => ['abil-1'],
            ],
        ];
        $skills = [['id' => 'skill-1', 'effects' => ['eff-missing-cumulative']]];
        $items  = [['id' => 'item-1',  'effects' => ['eff-missing-cumulative']]];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills, items: $items);
        $character = [
            'id'     => 'char-1',
            'skills' => ['skill-1'],
            'items'  => ['item-1'],
        ];
        $result = $service->calculateCharacter($character);

        // 10 + 5 = 15 (applied once). Were the default 'cumulative' it would be 20.
        self::assertSame(15, $result['stats']['abil-1']['value']);
        self::assertCount(1, $result['stats']['abil-1']['audit']);
    }

    public function testNonCumulativeEffectAppliedOnlyOnce(): void
    {
        // The same non-cumulative effect is attached to BOTH a skill and an item.
        // It should only be applied once regardless.
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $effects = [
            [
                'id'          => 'eff-1',
                'name'        => 'Iron Will',
                'modifier'    => 5,
                'modification' => 'positive',
                'cumulative'  => 'non-cumulative',
                'abilities'   => ['abil-1'],
            ],
        ];
        $skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];
        $items  = [['id' => 'item-1',  'effects' => ['eff-1']]];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills, items: $items);
        $character = [
            'id'     => 'char-1',
            'skills' => ['skill-1'],
            'items'  => ['item-1'],
        ];
        $result = $service->calculateCharacter($character);

        // 10 + 5 (once, not 10). Closes #208.
        self::assertSame(15, $result['stats']['abil-1']['value']);
        self::assertCount(1, $result['stats']['abil-1']['audit']);
    }

    public function testCumulativeEffectAppliedMultipleTimes(): void
    {
        // A cumulative effect on skill + item must stack.
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $effects = [
            [
                'id'          => 'eff-1',
                'modifier'    => 5,
                'modification' => 'positive',
                'cumulative'  => 'cumulative',
                'abilities'   => ['abil-1'],
            ],
        ];
        $skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];
        $items  = [['id' => 'item-1',  'effects' => ['eff-1']]];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills, items: $items);
        $character = ['id' => 'char-1', 'skills' => ['skill-1'], 'items' => ['item-1']];
        $result = $service->calculateCharacter($character);

        // 10 + 5 + 5 = 20 (cumulative stacks).
        self::assertSame(20, $result['stats']['abil-1']['value']);
    }

    public function testStatIdAndAbilitiesDeduplication(): void
    {
        // stat_id = 'abil-1' AND abilities = ['abil-1']: must apply only once. Closes #208.
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $effects = [
            [
                'id'          => 'eff-1',
                'modifier'    => 4,
                'modification' => 'positive',
                'abilities'   => ['abil-1'],
                'stat_id'     => 'abil-1',
            ],
        ];
        $skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
        $character = ['id' => 'char-1', 'skills' => ['skill-1']];
        $result = $service->calculateCharacter($character);

        // 10 + 4 = 14 (not 18, because stat_id dedup removes the duplicate ability). Closes #208.
        self::assertSame(14, $result['stats']['abil-1']['value']);
        self::assertCount(1, $result['stats']['abil-1']['audit']);
    }

    // -----------------------------------------------------------------------
    // #209 — sign-direction confusion fix
    // -----------------------------------------------------------------------

    public function testNegativeModifierWithNegativeModificationSubtracts(): void
    {
        // {modifier: -3, modification: 'negative'} must subtract 3, NOT add 3.
        // Before the fix this would have computed value - (-3) = value + 3.
        // Closes #209.
        $abilities = [
            ['id' => 'abil-1', 'name' => 'HP', 'base' => 20],
        ];
        $effects = [
            [
                'id'          => 'eff-1',
                'modifier'    => -3,
                'modification' => 'negative',
                'abilities'   => ['abil-1'],
            ],
        ];
        $skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
        $character = ['id' => 'char-1', 'skills' => ['skill-1']];
        $result = $service->calculateCharacter($character);

        // abs(-3) = 3; modification='negative' → 20 - 3 = 17.
        self::assertSame(17, $result['stats']['abil-1']['value']);
    }

    public function testNegativeModifierWithPositiveModificationAdds(): void
    {
        // {modifier: -5, modification: 'positive'} must add 5 (abs coercion).
        $abilities = [
            ['id' => 'abil-1', 'name' => 'HP', 'base' => 10],
        ];
        $effects = [
            [
                'id'          => 'eff-1',
                'modifier'    => -5,
                'modification' => 'positive',
                'abilities'   => ['abil-1'],
            ],
        ];
        $skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
        $character = ['id' => 'char-1', 'skills' => ['skill-1']];
        $result = $service->calculateCharacter($character);

        // abs(-5) = 5; modification='positive' → 10 + 5 = 15.
        self::assertSame(15, $result['stats']['abil-1']['value']);
    }

    // -----------------------------------------------------------------------
    // #219 — lean audit trail
    // -----------------------------------------------------------------------

    public function testAuditTrailContainsOnlyMinimalFields(): void
    {
        $abilities = [
            ['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
        ];
        $effects = [
            [
                'id'          => 'eff-42',
                'name'        => 'Battle Fury',
                'modifier'    => 3,
                'modification' => 'positive',
                'abilities'   => ['abil-1'],
                'secret_field' => 'should-not-appear',
            ],
        ];
        $skills = [['id' => 'skill-1', 'effects' => ['eff-42']]];

        $service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
        $character = ['id' => 'char-1', 'skills' => ['skill-1']];
        $result = $service->calculateCharacter($character);

        $audit = $result['stats']['abil-1']['audit'][0];

        // Required fields must be present.
        self::assertArrayHasKey('type', $audit);
        self::assertArrayHasKey('effectId', $audit);
        self::assertArrayHasKey('effectName', $audit);
        self::assertArrayHasKey('old', $audit);
        self::assertArrayHasKey('new', $audit);
        self::assertSame('eff-42', $audit['effectId']);
        self::assertSame('Battle Fury', $audit['effectName']);

        // The full effect object must NOT be stored. Closes #219.
        self::assertArrayNotHasKey('effect', $audit);
        self::assertArrayNotHasKey('secret_field', $audit);
    }
}
