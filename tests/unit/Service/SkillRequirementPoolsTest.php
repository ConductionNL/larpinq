<?php

/**
 * One budget per pool: a list's skills are paid from the list's own ability.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementChecker;
use OCA\Larpinq\Service\SkillRequirementService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Mirela has 30 XP and 2 mana; Firebolt costs 3 mana, Ward 2.
 */
class SkillRequirementPoolsTest extends TestCase {
	/**
	 * The world: XP and Mana, the arcane list paid from Mana, two spells and a general skill.
	 *
	 * @param array<int, array<string, mixed>> $lists The skill lists.
	 *
	 * @return SkillRequirementService
	 */
	private function service(array $lists): SkillRequirementService {
		$objects = [
			'ability' => [['id' => 'xp', 'name' => 'XP', 'base' => 30], ['id' => 'mana', 'name' => 'Mana', 'base' => 2]],
			'effect' => [
				['id' => 'mana-3', 'name' => '3 mana', 'modifier' => 3, 'modification' => 'negative', 'abilities' => ['mana']],
				['id' => 'mana-2', 'name' => '2 mana', 'modifier' => 2, 'modification' => 'negative', 'abilities' => ['mana']],
				['id' => 'xp-5', 'name' => '5 XP', 'modifier' => 5, 'modification' => 'negative', 'abilities' => ['xp']],
				['id' => 'xp-5b', 'name' => '5 XP', 'modifier' => 5, 'modification' => 'negative', 'abilities' => ['xp']],
				['id' => 'xp-25', 'name' => '25 XP', 'modifier' => 25, 'modification' => 'negative', 'abilities' => ['xp']],
			],
			'skill' => [
				['id' => 'firebolt', 'name' => 'Firebolt', 'list' => 'arcane', 'effects' => ['mana-3']],
				['id' => 'ward', 'name' => 'Ward', 'list' => 'arcane', 'effects' => ['mana-2']],
				['id' => 'sword', 'name' => 'Swordsmanship', 'effects' => ['xp-5']],
				['id' => 'parry', 'name' => 'Parry', 'list' => 'martial', 'effects' => ['xp-5b']],
				['id' => 'big', 'name' => 'Grand mastery', 'effects' => ['xp-25']],
			],
			'skillList' => $lists,
		];
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(static fn (string $type): array => ($objects[$type] ?? []));
		$logger = $this->createMock(LoggerInterface::class);
		$idList = new IdListNormaliser();

		return new SkillRequirementService(
			characterService: new CharacterService(objectFetcher: $fetcher, logger: $logger, effectApplier: new EffectApplier()),
			objectFetcher: $fetcher,
			logger: $logger,
			checker: new SkillRequirementChecker(idList: $idList),
			idList: $idList
		);
	}

	/**
	 * The two lists of Aldmoor.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function lists(): array {
		return [
			['id' => 'arcane', 'name' => 'Arcane spells', 'kind' => 'spells', 'costAbility' => 'mana'],
			['id' => 'martial', 'name' => 'Martial skills', 'kind' => 'skills'],
		];
	}

	/**
	 * Out of mana, not out of XP: refused with a shortfall of 1 on Mana.
	 *
	 * @return void
	 */
	public function testASpellIsRefusedOnManaWhileXpIsFine(): void {
		$result = $this->service(lists: self::lists())->validate(['skills' => ['firebolt']], ['skills' => []]);

		$this->assertFalse($result['valid']);
		$this->assertTrue($result['budget']['ok'], 'XP is fine');
		$this->assertSame(30, $result['budget']['value']);
		$this->assertSame(
			[
				['ability' => 'xp', 'value' => 30, 'shortfall' => 0, 'ok' => true],
				['ability' => 'mana', 'value' => -1, 'shortfall' => 1, 'ok' => false],
			],
			$result['budgets']
		);
		$this->assertSame([['ability' => 'mana', 'value' => -1, 'shortfall' => 1, 'ok' => false]], $result['overspentPools']);
	}

	/**
	 * An affordable spell passes, and its pool is reported.
	 *
	 * @return void
	 */
	public function testAnAffordableSpellPasses(): void {
		$result = $this->service(lists: self::lists())->validate(['skills' => ['ward']], ['skills' => []]);

		$this->assertTrue($result['valid']);
		$this->assertSame(['xp', 'mana'], array_column($result['budgets'], 'ability'));
		$this->assertSame(0, $result['budgets'][1]['value']);
	}

	/**
	 * A general skill, and a skill in a list without a cost ability, pay from XP only.
	 *
	 * @return void
	 */
	public function testSkillsWithoutAPoolPayFromXp(): void {
		$result = $this->service(lists: self::lists())->validate(['skills' => ['sword', 'parry']], ['skills' => []]);

		$this->assertTrue($result['valid']);
		$this->assertSame([['ability' => 'xp', 'value' => 20, 'shortfall' => 0, 'ok' => true]], $result['budgets']);
		$this->assertSame([], $result['overspentPools']);
	}

	/**
	 * Two spells of one list share one pool, reported once.
	 *
	 * @return void
	 */
	public function testOneListIsOnePool(): void {
		$result = $this->service(lists: self::lists())->validate(['skills' => ['firebolt', 'ward']], ['skills' => []]);

		$this->assertFalse($result['valid']);
		$this->assertSame(['xp', 'mana'], array_column($result['budgets'], 'ability'));
		$this->assertSame(3, $result['budgets'][1]['shortfall']);
	}

	/**
	 * When only XP is overspent, it is the `budget` block and no pool is reported on top of it.
	 *
	 * @return void
	 */
	public function testOverspentXpAloneIsNotAPool(): void {
		$result = $this->service(lists: self::lists())->validate(['skills' => ['sword', 'parry', 'ward', 'big']], ['skills' => []]);

		$this->assertFalse($result['valid']);
		$this->assertFalse($result['budget']['ok']);
		$this->assertSame([], $result['overspentPools']);
	}

	/**
	 * When the lists cannot be read, the spell pays from no pool of its own; XP still applies.
	 *
	 * @return void
	 */
	public function testUnknownListsAddNoPool(): void {
		$result = $this->service(lists: [])->validate(['skills' => ['firebolt']], ['skills' => []]);

		$this->assertSame(['xp'], array_column($result['budgets'], 'ability'));
	}
}
