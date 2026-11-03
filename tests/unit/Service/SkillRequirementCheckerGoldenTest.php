<?php

/**
 * Golden test for SkillRequirementChecker.
 *
 * Every scenario's entries are compared, as JSON text so key order and
 * scalar types count, with fixtures/skill-requirement-checker.golden.json.
 * Set RECORD_GOLDEN=1 to rewrite the fixture.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\SkillRequirementChecker;
use OCA\Larpinq\Service\SkillUnderCheck;
use PHPUnit\Framework\TestCase;

/**
 * Pins the requirement entries SkillRequirementChecker::check() produces.
 *
 * @covers \OCA\Larpinq\Service\SkillRequirementChecker
 * @uses   \OCA\Larpinq\Service\IdListNormaliser
 * @uses   \OCA\Larpinq\Service\SkillUnderCheck
 */
class SkillRequirementCheckerGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/skill-requirement-checker.golden.json';

	/**
	 * The scenarios: [skill, overridden, context].
	 *
	 * @return array<string,array{0:array<string,mixed>,1:bool,2:array<string,mixed>}>
	 */
	private function scenarios(): array {
		$context = [
			'skills' => ['s-a', 's-b'],
			'allSkills' => [
				's-a' => ['name' => 'Alchemy'],
				's-b' => ['name' => 'Bowyer'],
				's-c' => ['name' => 'Cartography'],
				's-n' => [],
			],
			'stats' => [
				'st-str' => ['name' => 'Strength', 'value' => 4],
				'st-int' => ['name' => 'Intellect', 'value' => '2'],
				'st-raw' => [],
			],
			'conditions' => ['c-1'],
			'effects' => ['e-1', 'e-2'],
		];

		return [
			'nothing required' => [[], false, $context],
			'empty context' => [
				[
					'requiredSkills' => ['s-a'],
					'requiredStats' => ['st-str'],
					'requiredScore' => 1,
					'requiredConditions' => ['c-1'],
					'requiredEffects' => ['e-1'],
				],
				false,
				[],
			],
			'skills present, absent, unknown, nameless' => [['requiredSkills' => ['s-a', 's-c', 's-x', 's-n']], false, $context],
			'skills overridden' => [['requiredSkills' => [['id' => 's-c'], ['id' => 's-a'], ['name' => 'no id'], '', null]], true, $context],
			'skill as a single string' => [['requiredSkills' => 's-b'], false, $context],
			'stats meet, miss, equal, unknown' => [['requiredStats' => ['st-str', 'st-int', 'st-x', 'st-raw'], 'requiredScore' => 4], false, $context],
			'stats with string score' => [['requiredStats' => ['st-int'], 'requiredScore' => '2'], false, $context],
			'stats without score' => [['requiredStats' => ['st-raw', 'st-x']], false, $context],
			'stats overridden' => [['requiredStats' => ['st-int', 'st-x'], 'requiredScore' => 9], true, $context],
			'conditions and effects' => [['requiredConditions' => ['c-1', 'c-2'], 'requiredEffects' => ['e-2', 'e-9']], false, $context],
			'conditions and effects against numeric ids' => [
				['requiredConditions' => ['1'], 'requiredEffects' => ['0']],
				false,
				['conditions' => [1], 'effects' => [0, 'e-0']],
			],
			'conditions and effects overridden' => [
				['requiredConditions' => ['c-2'], 'requiredEffects' => [['id' => 'e-9'], ['id' => 'e-1']]],
				true,
				$context,
			],
			'everything at once' => [
				[
					'requiredSkills' => ['s-b', 's-x'],
					'requiredStats' => ['st-str', 'st-x'],
					'requiredScore' => 5,
					'requiredConditions' => 'c-1',
					'requiredEffects' => ['e-3'],
				],
				false,
				$context,
			],
		];
	}//end scenarios()

	/**
	 * The entries of every scenario match the recorded fixture.
	 *
	 * @return void
	 */
	public function testEntriesMatchTheGoldenFixture(): void {
		$checker = new SkillRequirementChecker(idList: new IdListNormaliser());
		$actual = [];
		foreach ($this->scenarios() as $name => [$skill, $overridden, $context]) {
			$actual[$name] = $checker->check(
				subject: new SkillUnderCheck(skillId: 'skill-1', skill: $skill, overridden: $overridden),
				context: $context
			);
		}

		$json = json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		$this->assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testEntriesMatchTheGoldenFixture()
}//end class
