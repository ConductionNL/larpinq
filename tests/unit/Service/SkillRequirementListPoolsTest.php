<?php

/**
 * Several pools at once: each skill list is paid from its own ability, and
 * the lists are read only when a skill is in one.
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

use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementChecker;
use OCA\Larpinq\Service\SkillRequirementService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Sela has 30 XP, 2 mana and 1 faith. Firebolt costs 3 mana, Ward 2 mana,
 * Bless 1 faith and Smite 2 faith; Parry is martial and paid from XP.
 */
class SkillRequirementListPoolsTest extends TestCase {
	/**
	 * How often each object type was read.
	 *
	 * @var array<string, int>
	 */
	private array $reads = [];

	/**
	 * The service over Sela's world.
	 *
	 * @return SkillRequirementService
	 */
	private function service(): SkillRequirementService {
		$objects = [
			'ability' => [
				['id' => 'xp', 'name' => 'XP', 'base' => 30],
				['id' => 'mana', 'name' => 'Mana', 'base' => 2],
				['id' => 'faith', 'name' => 'Faith', 'base' => 1],
			],
			'effect' => [
				['id' => 'mana-3', 'modifier' => 3, 'modification' => 'negative', 'abilities' => ['mana']],
				['id' => 'mana-2', 'modifier' => 2, 'modification' => 'negative', 'abilities' => ['mana']],
				['id' => 'faith-1', 'modifier' => 1, 'modification' => 'negative', 'abilities' => ['faith']],
				['id' => 'faith-2', 'modifier' => 2, 'modification' => 'negative', 'abilities' => ['faith']],
				['id' => 'xp-5', 'modifier' => 5, 'modification' => 'negative', 'abilities' => ['xp']],
				['id' => 'xp-5b', 'modifier' => 5, 'modification' => 'negative', 'abilities' => ['xp']],
			],
			'skill' => [
				['id' => 'firebolt', 'name' => 'Firebolt', 'list' => 'arcane', 'effects' => ['mana-3']],
				['id' => 'ward', 'name' => 'Ward', 'list' => 'arcane', 'effects' => ['mana-2']],
				['id' => 'bless', 'name' => 'Bless', 'list' => 'divine', 'effects' => ['faith-1']],
				['id' => 'smite', 'name' => 'Smite', 'list' => 'divine', 'effects' => ['faith-2']],
				['id' => 'parry', 'name' => 'Parry', 'list' => 'martial', 'effects' => ['xp-5']],
				['id' => 'run', 'name' => 'Running', 'effects' => ['xp-5b']],
			],
			'skillList' => [
				['id' => 'arcane', 'name' => 'Arcane spells', 'kind' => 'spells', 'costAbility' => 'mana'],
				['id' => 'divine', 'name' => 'Prayers', 'kind' => 'powers', 'costAbility' => 'faith'],
				['id' => 'martial', 'name' => 'Martial skills', 'kind' => 'skills', 'costAbility' => 'xp'],
			],
		];
		$this->reads = [];
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $type) use ($objects): array {
				$this->reads[$type] = (($this->reads[$type] ?? 0) + 1);
				return ($objects[$type] ?? []);
			}
		);
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
	 * The abilities of the pool blocks, in order.
	 *
	 * @param array<string, mixed> $result The validation result.
	 *
	 * @return list<string>
	 */
	private static function pools(array $result): array {
		return array_column($result['budgets'], 'ability');
	}

	/**
	 * Two lists overspent at once: both pools are refused, each with its own shortfall.
	 *
	 * @return void
	 */
	public function testTwoOverspentPoolsAreBothReported(): void {
		$result = $this->service()->validate(['skills' => ['firebolt', 'smite']], ['skills' => []]);

		$this->assertFalse($result['valid']);
		$this->assertTrue($result['budget']['ok']);
		$this->assertSame(['xp', 'mana', 'faith'], self::pools($result));
		$this->assertSame(
			[
				['ability' => 'mana', 'value' => -1, 'shortfall' => 1, 'ok' => false],
				['ability' => 'faith', 'value' => -1, 'shortfall' => 1, 'ok' => false],
			],
			$result['overspentPools']
		);
	}

	/**
	 * One pool overspent and the other exactly spent: only the overspent one is reported.
	 *
	 * @return void
	 */
	public function testAnExactlySpentPoolIsNotOverspent(): void {
		$result = $this->service()->validate(['skills' => ['ward', 'smite']], ['skills' => []]);

		$this->assertFalse($result['valid']);
		$this->assertSame(['xp', 'mana', 'faith'], self::pools($result));
		$this->assertSame(0, $result['budgets'][1]['value']);
		$this->assertTrue($result['budgets'][1]['ok']);
		$this->assertSame([['ability' => 'faith', 'value' => -1, 'shortfall' => 1, 'ok' => false]], $result['overspentPools']);
	}

	/**
	 * Both pools affordable: the write is valid and nothing is overspent.
	 *
	 * @return void
	 */
	public function testAffordableSpellsAndPrayersPass(): void {
		$result = $this->service()->validate(['skills' => ['ward', 'bless']], ['skills' => []]);

		$this->assertTrue($result['valid']);
		$this->assertSame(['xp', 'mana', 'faith'], self::pools($result));
		$this->assertSame([0, 0], [$result['budgets'][1]['value'], $result['budgets'][2]['value']]);
		$this->assertSame([], $result['overspentPools']);
	}

	/**
	 * A list paid from XP adds no second XP block; its cost lands on the XP budget.
	 *
	 * @return void
	 */
	public function testAListPaidFromXpSharesTheXpBlock(): void {
		$result = $this->service()->validate(['skills' => ['parry', 'run']], ['skills' => []]);

		$this->assertTrue($result['valid']);
		$this->assertSame([['ability' => 'xp', 'value' => 20, 'shortfall' => 0, 'ok' => true]], $result['budgets']);
	}

	/**
	 * The lists are read once for a listed skill, and not at all without one.
	 *
	 * @return void
	 */
	public function testTheListsAreReadOnlyWhenNeeded(): void {
		$this->service()->validate(['skills' => ['run']], ['skills' => []]);
		$this->assertArrayNotHasKey('skillList', $this->reads);

		$this->service()->validate(['skills' => ['firebolt', 'ward', 'bless']], ['skills' => []]);
		$this->assertSame(1, ($this->reads['skillList'] ?? 0));
	}

	/**
	 * A kept spell still draws on its pool: an unrelated edit of an overspent
	 * character reports the pool and is refused.
	 *
	 * @return void
	 */
	public function testAKeptSpellStillCountsAgainstItsPool(): void {
		$result = $this->service()->validate(['skills' => ['firebolt', 'run']], ['skills' => ['firebolt']]);

		$this->assertFalse($result['valid']);
		$this->assertSame(['xp', 'mana'], self::pools($result));
		$this->assertSame(25, $result['budget']['value']);
		$this->assertSame([['ability' => 'mana', 'value' => -1, 'shortfall' => 1, 'ok' => false]], $result['overspentPools']);
	}

	/**
	 * Dropping the overspending spell frees its pool again.
	 *
	 * @return void
	 */
	public function testDroppingTheSpellFreesThePool(): void {
		$result = $this->service()->validate(['skills' => ['ward']], ['skills' => ['firebolt', 'ward']]);

		$this->assertTrue($result['valid']);
		$this->assertSame(['xp', 'mana'], self::pools($result));
		$this->assertSame(0, $result['budgets'][1]['value']);
		$this->assertSame([], $result['overspentPools']);
	}
}//end class
