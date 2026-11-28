<?php

/**
 * Unit tests for SkillListPools: which pool a character's listed skills pay from.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\SkillListPools;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Aldmoor: the arcane list pays from Mana, the divine list from Faith, the
 * martial list from XP itself and the lore list names no ability at all.
 */
#[CoversClass(SkillListPools::class)]
final class SkillListPoolsTest extends TestCase {
	/**
	 * Every skill of Aldmoor, by id.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function allSkills(): array {
		return [
			'firebolt' => ['name' => 'Firebolt', 'list' => 'arcane'],
			'ward' => ['name' => 'Ward', 'list' => 'arcane'],
			'bless' => ['name' => 'Bless', 'list' => 'divine'],
			'parry' => ['name' => 'Parry', 'list' => 'martial'],
			'herbs' => ['name' => 'Herb lore', 'list' => 'lore'],
			'ghost' => ['name' => 'Ghost step', 'list' => 'unknown'],
			'run' => ['name' => 'Running'],
			'swim' => ['name' => 'Swimming', 'list' => ''],
		];
	}

	/**
	 * Every skill list of Aldmoor, by id.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function allLists(): array {
		return [
			'arcane' => ['name' => 'Arcane spells', 'costAbility' => 'mana'],
			'divine' => ['name' => 'Prayers', 'costAbility' => 'faith'],
			'martial' => ['name' => 'Martial skills', 'costAbility' => 'xp'],
			'lore' => ['name' => 'Lore', 'costAbility' => ''],
		];
	}

	/**
	 * The XP block of a character with this much XP left.
	 *
	 * @param int $value The XP left.
	 *
	 * @return array{ability: string, value: int, shortfall: int, ok: bool}
	 */
	private static function xpBlock(int $value): array {
		return ['ability' => 'xp', 'value' => $value, 'shortfall' => max(0, -$value), 'ok' => ($value >= 0)];
	}

	/**
	 * A validation context for these skills and stats.
	 *
	 * @param list<string>          $skills The character's skill ids.
	 * @param array<string, int>    $stats  The ability values left, by ability id.
	 * @param int                   $xp     The XP left.
	 *
	 * @return array<string, mixed>
	 */
	private static function context(array $skills, array $stats = [], int $xp = 10): array {
		$byId = [];
		foreach ($stats as $ability => $value) {
			$byId[$ability] = ['value' => $value];
		}

		return [
			'budget' => self::xpBlock(value: $xp),
			'stats' => $byId,
			'skills' => $skills,
			'allSkills' => self::allSkills(),
		];
	}

	public function testCostAbilitiesNamesEachListAbilityOnceInSkillOrder(): void {
		$abilities = SkillListPools::costAbilities(
			context: ['skills' => ['bless', 'firebolt', 'ward', 'parry'], 'allSkills' => self::allSkills()],
			allLists: self::allLists()
		);

		$this->assertSame(['faith', 'mana', 'xp'], $abilities);
	}

	public function testCostAbilitiesSkipsListlessUnknownAndAbilitylessSkills(): void {
		$abilities = SkillListPools::costAbilities(
			context: ['skills' => ['run', 'swim', 'ghost', 'herbs', 'nobody'], 'allSkills' => self::allSkills()],
			allLists: self::allLists()
		);

		$this->assertSame([], $abilities);
	}

	public function testCostAbilitiesOfNoSkillsIsEmpty(): void {
		$this->assertSame([], SkillListPools::costAbilities(context: ['skills' => [], 'allSkills' => []], allLists: self::allLists()));
	}

	public function testAnyListedIsTrueOnlyForASkillInANamedList(): void {
		$this->assertTrue(SkillListPools::anyListed(context: ['skills' => ['run', 'parry'], 'allSkills' => self::allSkills()]));
		$this->assertTrue(SkillListPools::anyListed(context: ['skills' => ['ghost'], 'allSkills' => self::allSkills()]));
		$this->assertFalse(SkillListPools::anyListed(context: ['skills' => ['run', 'swim', 'nobody'], 'allSkills' => self::allSkills()]));
		$this->assertFalse(SkillListPools::anyListed(context: ['skills' => [], 'allSkills' => self::allSkills()]));
	}

	public function testBudgetsWithoutListedSkillsIsTheXpBlockAndNeverReadsTheLists(): void {
		$reads = 0;
		$budgets = SkillListPools::budgets(
			context: self::context(skills: ['run', 'swim'], xp: 4),
			lists: static function () use (&$reads): array {
				$reads++;
				return self::allLists();
			}
		);

		$this->assertSame([self::xpBlock(value: 4)], $budgets);
		$this->assertSame(0, $reads);
	}

	public function testBudgetsAddsOneBlockPerListAbilityAfterXp(): void {
		$reads = 0;
		$budgets = SkillListPools::budgets(
			context: self::context(skills: ['firebolt', 'bless', 'ward'], stats: ['mana' => -1, 'faith' => 3], xp: 7),
			lists: static function () use (&$reads): array {
				$reads++;
				return self::allLists();
			}
		);

		$this->assertSame(1, $reads);
		$this->assertSame(
			[
				self::xpBlock(value: 7),
				['ability' => 'mana', 'value' => -1, 'shortfall' => 1, 'ok' => false],
				['ability' => 'faith', 'value' => 3, 'shortfall' => 0, 'ok' => true],
			],
			$budgets
		);
	}

	public function testBudgetsDoesNotRepeatTheXpPoolForAListPaidFromXp(): void {
		$budgets = SkillListPools::budgets(
			context: self::context(skills: ['parry', 'firebolt'], stats: ['mana' => 0, 'xp' => 99], xp: -2),
			lists: static fn (): array => self::allLists()
		);

		$this->assertSame(
			[
				self::xpBlock(value: -2),
				['ability' => 'mana', 'value' => 0, 'shortfall' => 0, 'ok' => true],
			],
			$budgets
		);
	}

	public function testBudgetsReadsAMissingStatAsZero(): void {
		$budgets = SkillListPools::budgets(
			context: self::context(skills: ['bless']),
			lists: static fn (): array => self::allLists()
		);

		$this->assertSame(['ability' => 'faith', 'value' => 0, 'shortfall' => 0, 'ok' => true], $budgets[1]);
		$this->assertCount(2, $budgets);
	}

	public function testOverspentListsEveryPoolThatIsNotOk(): void {
		$mana = ['ability' => 'mana', 'value' => -3, 'shortfall' => 3, 'ok' => false];
		$faith = ['ability' => 'faith', 'value' => 1, 'shortfall' => 0, 'ok' => true];
		$xp = self::xpBlock(value: -5);

		$this->assertSame([$xp, $mana], SkillListPools::overspent(result: ['budget' => $xp, 'budgets' => [$xp, $mana, $faith]]));
		$this->assertSame([$mana], SkillListPools::overspent(result: ['budget' => self::xpBlock(value: 2), 'budgets' => [self::xpBlock(value: 2), $mana]]));
	}

	public function testOverspentIsEmptyWhenOnlyTheXpBlockIsOverspent(): void {
		$xp = self::xpBlock(value: -5);

		$this->assertSame([], SkillListPools::overspent(result: ['budget' => $xp, 'budgets' => [$xp]]));
	}

	public function testOverspentIsEmptyWhenEveryPoolIsOkOrThereAreNone(): void {
		$xp = self::xpBlock(value: 1);

		$this->assertSame([], SkillListPools::overspent(result: ['budget' => $xp, 'budgets' => [$xp]]));
		$this->assertSame([], SkillListPools::overspent(result: []));
	}

	public function testOverspentIgnoresEntriesThatAreNotPools(): void {
		$mana = ['ability' => 'mana', 'value' => -1, 'shortfall' => 1, 'ok' => false];

		$this->assertSame(
			[$mana],
			SkillListPools::overspent(result: ['budgets' => ['broken', null, ['ability' => 'faith'], $mana]])
		);
	}
}//end class
