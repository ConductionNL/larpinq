<?php
declare(strict_types=1);
namespace OCA\LarpingApp\Tests\Unit\Service;

use OCA\LarpingApp\Service\CharacterService;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CharacterServiceTest extends TestCase
{
    private RegisterObjectFetcher&MockObject $objectFetcher;
    private CharacterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectFetcher = $this->createMock(RegisterObjectFetcher::class);
        $abilities = [
            ['id' => 'ability-xp', 'name' => 'XP', 'base' => 0],
            ['id' => 'ability-reputation', 'name' => 'Reputation', 'base' => 0],
            ['id' => 'ability-strength', 'name' => 'Strength', 'base' => 10],
            ['id' => 'ability-mana', 'name' => 'Mana', 'base' => 5],
        ];
        $effects = [
            ['id' => 'effect-xp50', 'name' => '+50 XP', 'modifier' => 50, 'modification' => 'positive', 'abilities' => ['ability-xp'], 'stat_id' => null],
            ['id' => 'effect-rep10', 'name' => '+10 Rep', 'modifier' => 10, 'modification' => 'positive', 'abilities' => ['ability-reputation'], 'stat_id' => null],
            ['id' => 'effect-rep5', 'name' => '+5 Rep', 'modifier' => 5, 'modification' => 'positive', 'abilities' => ['ability-reputation'], 'stat_id' => null],
            ['id' => 'effect-str3', 'name' => '+3 Str', 'modifier' => 3, 'modification' => 'positive', 'abilities' => [], 'stat_id' => 'ability-strength'],
            ['id' => 'effect-str-neg2', 'name' => '-2 Str', 'modifier' => 2, 'modification' => 'negative', 'abilities' => ['ability-strength'], 'stat_id' => null],
        ];
        $events = [
            ['id' => 'event-tournament', 'name' => 'Tournament', 'effects' => ['effect-xp50']],
            ['id' => 'event-battle', 'name' => 'Battle', 'effects' => ['effect-rep10']],
            ['id' => 'event-council', 'name' => 'Council', 'effects' => ['effect-rep5']],
            ['id' => 'event-social', 'name' => 'Social', 'effects' => []],
        ];
        $skills = [['id' => 'skill-sword', 'name' => 'Sword', 'effects' => ['effect-str3']]];
        $items = [['id' => 'item-shield', 'name' => 'Shield', 'effects' => []]];
        $conditions = [['id' => 'cond-cursed', 'name' => 'Cursed', 'effects' => ['effect-str-neg2']]];
        $this->objectFetcher->method('getObjects')
            ->willReturnCallback(function (string $type) use ($skills, $items, $conditions, $events, $effects, $abilities) {
                return match ($type) {
                    'skill' => $skills, 'item' => $items, 'condition' => $conditions,
                    'event' => $events, 'effect' => $effects, 'ability' => $abilities,
                    default => [],
                };
            });
        $this->service = new CharacterService(objectFetcher: $this->objectFetcher);
    }

    public function testEventEffectsApplied(): void
    {
        $c = ['id' => 'c1', 'name' => 'F', 'skills' => [], 'items' => [], 'conditions' => [], 'events' => ['event-tournament']];
        $r = $this->service->calculateCharacter(character: $c);
        self::assertSame(50, $r['stats']['ability-xp']['value']);
        self::assertCount(1, $r['stats']['ability-xp']['audit']);
    }

    public function testMultipleEventEffectsStack(): void
    {
        $c = ['id' => 'c2', 'name' => 'A', 'skills' => [], 'items' => [], 'conditions' => [], 'events' => ['event-battle', 'event-council']];
        $r = $this->service->calculateCharacter(character: $c);
        self::assertSame(15, $r['stats']['ability-reputation']['value']);
    }

    public function testEventWithNoEffectsSkipped(): void
    {
        $c = ['id' => 'c3', 'name' => 'B', 'skills' => [], 'items' => [], 'conditions' => [], 'events' => ['event-social']];
        $r = $this->service->calculateCharacter(character: $c);
        self::assertSame(0, $r['stats']['ability-xp']['value']);
        self::assertSame(10, $r['stats']['ability-strength']['value']);
    }

    public function testMissingEventSkipped(): void
    {
        $c = ['id' => 'c4', 'name' => 'O', 'skills' => [], 'items' => [], 'conditions' => [], 'events' => ['nonexistent']];
        $r = $this->service->calculateCharacter(character: $c);
        self::assertSame(10, $r['stats']['ability-strength']['value']);
    }

    public function testEffectsAppliedInOrder(): void
    {
        $c = ['id' => 'c5', 'name' => 'M', 'skills' => ['skill-sword'], 'items' => [], 'conditions' => ['cond-cursed'], 'events' => ['event-tournament']];
        $r = $this->service->calculateCharacter(character: $c);
        self::assertSame(11, $r['stats']['ability-strength']['value']);
        self::assertSame(50, $r['stats']['ability-xp']['value']);
    }

    public function testNoEventsProperty(): void
    {
        $c = ['id' => 'c6', 'name' => 'N', 'skills' => [], 'items' => [], 'conditions' => []];
        $r = $this->service->calculateCharacter(character: $c);
        self::assertSame(0, $r['stats']['ability-xp']['value']);
    }

    public function testEmptyEventsArray(): void
    {
        $c = ['id' => 'c7', 'name' => 'E', 'skills' => [], 'items' => [], 'conditions' => [], 'events' => []];
        $r = $this->service->calculateCharacter(character: $c);
        self::assertArrayHasKey('stats', $r);
    }

    public function testStatsStructure(): void
    {
        $c = ['id' => 'c8', 'name' => 'T', 'skills' => [], 'items' => [], 'conditions' => [], 'events' => []];
        $r = $this->service->calculateCharacter(character: $c);
        self::assertArrayHasKey('stats', $r);
        self::assertArrayHasKey('ability-xp', $r['stats']);
        self::assertArrayHasKey('name', $r['stats']['ability-xp']);
        self::assertArrayHasKey('base', $r['stats']['ability-xp']);
        self::assertArrayHasKey('value', $r['stats']['ability-xp']);
        self::assertArrayHasKey('audit', $r['stats']['ability-xp']);
    }
}
