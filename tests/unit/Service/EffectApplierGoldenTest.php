<?php

/**
 * Golden test for EffectApplier::applyEffects().
 *
 * Each case runs one effect list over a set of abilities and records the
 * resulting abilities (values and audit) and the applied-effects map.
 * Compared with fixtures/effect-applier.golden.json. Set RECORD_GOLDEN=1 to
 * rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\EffectApplier;
use PHPUnit\Framework\TestCase;

/**
 * The effect arithmetic, case by case.
 *
 * @covers \OCA\Larpinq\Service\EffectApplier
 * @uses   \OCA\Larpinq\Service\EffectRun
 */
class EffectApplierGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/effect-applier.golden.json';

	/**
	 * The effects every case can refer to, by id.
	 *
	 * @return array<string,array<string,mixed>> The lookup.
	 */
	private static function lookup(): array {
		return [
			'plus3' => ['id' => 'plus3', 'name' => 'Plus three', 'modifier' => 3, 'modification' => 'positive', 'abilities' => ['str']],
			'minus2' => ['id' => 'minus2', 'name' => 'Minus two', 'modifier' => -2, 'modification' => 'negative', 'abilities' => ['str', 'dex']],
			'default' => ['id' => 'default', 'name' => 'No direction', 'modifier' => 4, 'abilities' => ['dex']],
			'sideways' => ['id' => 'sideways', 'name' => 'Sideways', 'modifier' => 5, 'modification' => 'other', 'abilities' => ['str']],
			'statid' => ['id' => 'statid', 'name' => 'Stat id', 'modifier' => 1, 'modification' => 'positive', 'abilities' => ['str'], 'stat_id' => 'str'],
			'statonly' => ['id' => 'statonly', 'name' => 'Stat only', 'modifier' => 2, 'modification' => 'positive', 'stat_id' => 'new'],
			'cumul' => ['id' => 'cumul', 'name' => 'Cumulative', 'modifier' => 1, 'modification' => 'positive', 'abilities' => ['str'], 'cumulative' => 'cumulative'],
			'none' => ['id' => 'none', 'name' => 'No abilities', 'modifier' => 9, 'modification' => 'positive'],
			'nullability' => ['id' => 'nullability', 'name' => 'Null ability', 'modifier' => 1, 'modification' => 'positive', 'abilities' => [null, 'dex']],
			'noid' => ['name' => 'No id', 'modifier' => 1, 'modification' => 'positive', 'abilities' => ['str']],
		];
	}//end lookup()

	/**
	 * The cases: effect list and the applied map to start from.
	 *
	 * @return array<string,array{0:array<int,mixed>|null,1:array<string,bool>}> The cases.
	 */
	private static function cases(): array {
		return [
			'null list' => [null, []],
			'empty list' => [[], []],
			'positive' => [['plus3'], []],
			'negative takes the absolute modifier' => [['minus2'], []],
			'no direction is positive' => [['default'], []],
			'unknown direction changes nothing but is audited' => [['sideways'], []],
			'stat id already listed applies once' => [['statid'], []],
			'stat id alone seeds a new ability' => [['statonly'], []],
			'non-cumulative twice applies once' => [['plus3', 'plus3'], []],
			'cumulative twice applies twice' => [['cumul', 'cumul'], []],
			'already applied non-cumulative is skipped' => [['plus3'], ['plus3' => true]],
			'already applied cumulative still applies' => [['cumul'], ['cumul' => true]],
			'no abilities' => [['none'], []],
			'null ability id is skipped' => [['nullability'], []],
			'null and unknown effect ids are skipped' => [[null, 'missing', 'plus3'], []],
			'effect without id applies every time' => [['noid', 'noid'], []],
			'numeric effect id' => [[7], []],
			'mixed list' => [['plus3', 'minus2', 'default', 'cumul', 'plus3', 'cumul'], []],
		];
	}//end cases()

	/**
	 * The abilities every case starts from.
	 *
	 * @return array<string,array<string,mixed>> The abilities.
	 */
	private static function abilities(): array {
		return [
			'str' => ['name' => 'Strength', 'base' => 10, 'value' => 10, 'audit' => []],
			'dex' => ['name' => 'Dexterity', 'base' => 8],
		];
	}//end abilities()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$lookup = self::lookup() + ['7' => ['id' => '7', 'name' => 'Seven', 'modifier' => 7, 'modification' => 'positive', 'abilities' => ['dex']]];
		$actual = [];
		foreach (self::cases() as $name => [$effects, $applied]) {
			$abilities = self::abilities();
			(new EffectApplier())->applyEffects(abilities: $abilities, effects: $effects, appliedEffects: $applied, effectLookup: $lookup);
			$actual[$name] = json_encode(['abilities' => $abilities, 'applied' => $applied], JSON_PRETTY_PRINT);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT)."\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()
}//end class
