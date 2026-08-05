<?php

/**
 * Per-mechanic unit tests for the LarpingApp game-mechanics engine.
 *
 * LarpingApp stores every game entity (Ability, Effect, Skill, Item,
 * Condition) as an OpenRegister object — an associative array — rather than a
 * local PHP entity class. These tests therefore exercise each mechanic at the
 * level it actually exists: the data shape each mechanic carries and how that
 * shape is consumed and derived by {@see CharacterService}. One test class
 * groups one focused test per mechanic, satisfying TEST-030 of the
 * game-mechanics spec ("all entity classes must be tested for serialization")
 * against the real OR-object model.
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
use OCA\LarpingApp\Service\EffectApplier;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for each individual game mechanic over the OpenRegister object model.
 *
 * @spec openspec/changes/game-mechanics/tasks.md
 */
class GameMechanicsTest extends TestCase
{

    /**
     * Build a CharacterService whose object fetcher returns the supplied
     * per-type entity collections.
     *
     * @param array $abilities  Ability objects.
     * @param array $effects    Effect objects.
     * @param array $skills     Skill objects.
     * @param array $items      Item objects.
     * @param array $conditions Condition objects.
     * @param array $events     Event objects.
     *
     * @return CharacterService The configured service.
     */
    private function serviceWith(
        array $abilities = [],
        array $effects = [],
        array $skills = [],
        array $items = [],
        array $conditions = [],
        array $events = []
    ): CharacterService {
        $fetcher = $this->createMock(RegisterObjectFetcher::class);
        $fetcher->method('getObjects')
            ->willReturnCallback(
                static function (string $type) use ($abilities, $effects, $skills, $items, $conditions, $events): array {
                    return match ($type) {
                        'ability'   => $abilities,
                        'effect'    => $effects,
                        'skill'     => $skills,
                        'item'      => $items,
                        'condition' => $conditions,
                        'event'     => $events,
                        default     => [],
                    };
                }
            );

        return new CharacterService($fetcher, $this->createMock(LoggerInterface::class), new EffectApplier());
    }//end serviceWith()

    /**
     * Ability mechanic: an Ability object's identity, base score and audit
     * skeleton are preserved verbatim into the derived stats block, so the
     * Ability serialises through stat derivation without losing its scalar
     * shape.
     *
     * @return void
     */
    public function testAbilityMechanicSerialisesIntoStats(): void
    {
        $ability = [
            'id'   => 'abil-str',
            'name' => 'Strength',
            'base' => 12,
        ];

        $service = $this->serviceWith(abilities: [$ability]);
        $result  = $service->calculateCharacter(['id' => 'char-1']);

        self::assertArrayHasKey('abil-str', $result['stats']);
        $stat = $result['stats']['abil-str'];
        self::assertSame('Strength', $stat['name']);
        self::assertSame(12, $stat['base']);
        self::assertSame(12, $stat['value']);
        self::assertSame([], $stat['audit']);
    }//end testAbilityMechanicSerialisesIntoStats()

    /**
     * Effect mechanic: a directly-applied Effect object (positive modification)
     * adds its modifier to the targeted ability and records a faithful audit
     * entry carrying the effect's identity and the before/after delta.
     *
     * @return void
     */
    public function testEffectMechanicAppliesModifierAndAudits(): void
    {
        $ability = ['id' => 'abil-1', 'name' => 'Power', 'base' => 4];
        $effect  = [
            'id'           => 'eff-1',
            'name'         => 'Empower',
            'modifier'     => 6,
            'modification' => 'positive',
            'abilities'    => ['abil-1'],
        ];
        // Attach the effect via a skill so the chain entry point is exercised.
        $skill = ['id' => 'skill-1', 'effects' => ['eff-1']];

        $service = $this->serviceWith(abilities: [$ability], effects: [$effect], skills: [$skill]);
        $result  = $service->calculateCharacter(['id' => 'char-1', 'skills' => ['skill-1']]);

        self::assertSame(10, $result['stats']['abil-1']['value']);
        $audit = $result['stats']['abil-1']['audit'][0];
        self::assertSame('effect', $audit['type']);
        self::assertSame('eff-1', $audit['effectId']);
        self::assertSame('Empower', $audit['effectName']);
        self::assertSame(4, $audit['old']);
        self::assertSame(10, $audit['new']);
    }//end testEffectMechanicAppliesModifierAndAudits()

    /**
     * Skill mechanic: a Skill object carrying an effects list routes those
     * effects into stat derivation. A skill with no resolvable effects is a
     * no-op (its serialised shape contributes nothing).
     *
     * @return void
     */
    public function testSkillMechanicRoutesItsEffects(): void
    {
        $ability = ['id' => 'abil-1', 'name' => 'Agility', 'base' => 5];
        $effect  = [
            'id'           => 'eff-fast',
            'name'         => 'Fleet',
            'modifier'     => 3,
            'modification' => 'positive',
            'abilities'    => ['abil-1'],
        ];
        $skills = [
            ['id' => 'skill-active', 'name' => 'Acrobatics', 'effects' => ['eff-fast']],
            ['id' => 'skill-inert',  'name' => 'Cooking',    'effects' => []],
        ];

        $service = $this->serviceWith(abilities: [$ability], effects: [$effect], skills: $skills);

        // Active skill applies; inert skill contributes nothing.
        $withSkill = $service->calculateCharacter(['id' => 'c', 'skills' => ['skill-active']]);
        self::assertSame(8, $withSkill['stats']['abil-1']['value']);

        $inertOnly = $this->serviceWith(abilities: [$ability], effects: [$effect], skills: $skills)
            ->calculateCharacter(['id' => 'c', 'skills' => ['skill-inert']]);
        self::assertSame(5, $inertOnly['stats']['abil-1']['value']);
    }//end testSkillMechanicRoutesItsEffects()

    /**
     * Item mechanic: an Item object's effects apply to a character that carries
     * the item, independently of the skill route.
     *
     * @return void
     */
    public function testItemMechanicAppliesWornEffects(): void
    {
        $ability = ['id' => 'abil-def', 'name' => 'Defence', 'base' => 0];
        $effect  = [
            'id'           => 'eff-armour',
            'name'         => 'Plate Armour',
            'modifier'     => 8,
            'modification' => 'positive',
            'abilities'    => ['abil-def'],
        ];
        $item = ['id' => 'item-plate', 'name' => 'Plate Mail', 'effects' => ['eff-armour']];

        $service = $this->serviceWith(abilities: [$ability], effects: [$effect], items: [$item]);
        $result  = $service->calculateCharacter(['id' => 'char-1', 'items' => ['item-plate']]);

        self::assertSame(8, $result['stats']['abil-def']['value']);
        self::assertSame('eff-armour', $result['stats']['abil-def']['audit'][0]['effectId']);
    }//end testItemMechanicAppliesWornEffects()

    /**
     * Condition mechanic: a Condition object applies a negative modification,
     * subtracting from the targeted ability — the canonical debuff path.
     *
     * @return void
     */
    public function testConditionMechanicAppliesNegativeModifier(): void
    {
        $ability   = ['id' => 'abil-hp', 'name' => 'Health', 'base' => 30];
        $effect    = [
            'id'           => 'eff-bleed',
            'name'         => 'Bleeding',
            'modifier'     => 7,
            'modification' => 'negative',
            'abilities'    => ['abil-hp'],
        ];
        $condition = ['id' => 'cond-wound', 'name' => 'Wounded', 'effects' => ['eff-bleed']];

        $service = $this->serviceWith(abilities: [$ability], effects: [$effect], conditions: [$condition]);
        $result  = $service->calculateCharacter(['id' => 'char-1', 'conditions' => ['cond-wound']]);

        self::assertSame(23, $result['stats']['abil-hp']['value']);
        self::assertSame(30, $result['stats']['abil-hp']['audit'][0]['old']);
        self::assertSame(23, $result['stats']['abil-hp']['audit'][0]['new']);
    }//end testConditionMechanicAppliesNegativeModifier()
}//end class
