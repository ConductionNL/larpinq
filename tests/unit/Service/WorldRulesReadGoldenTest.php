<?php

/**
 * Golden test for how WorldCopyService reads the rule objects of a world.
 *
 * Each case scripts the pages the object fetcher returns per type and
 * records what readRules() returns (row ids per type) or throws, and every
 * fetch it made. Compared with fixtures/world-rules-read.golden.json. Set
 * RECORD_GOLDEN=1 to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\WorldCopyService;
use PHPUnit\Framework\TestCase;

/**
 * Reading the rule objects of a world, case by case.
 *
 * @covers \OCA\Larpinq\Service\WorldCopyService
 * @uses   \OCA\Larpinq\Service\IdListNormaliser
 */
class WorldRulesReadGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/world-rules-read.golden.json';

	/**
	 * The cases: per type, the number of rows the world holds, or a failure.
	 *
	 * @return array<string,array<string,int|string>> Case name to rows per type.
	 */
	private static function cases(): array {
		return [
			'small world' => ['ability' => 3, 'skill' => 2],
			'one full page' => ['effect' => 500],
			'two pages' => ['skill' => 730],
			'exactly the cap' => ['ability' => 1000, 'effect' => 1000],
			'one over the cap' => ['ability' => 1000, 'effect' => 1001],
			'over the cap in one type' => ['item' => 2600],
			'a later type stops at its budget' => ['ability' => 1501, 'effect' => 1000],
			'rows without an id' => ['condition' => 'gaps'],
			'optional type not configured' => ['lorepage' => 'unconfigured', 'ability' => 1],
			'optional type fails on its second page' => ['characterfield' => 'unconfigured-later'],
			'required type not configured' => ['skill' => 'unconfigured'],
			'optional type other failure' => ['lorepage' => 'broken'],
		];
	}//end cases()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach (self::cases() as $name => $world) {
			$actual[$name] = json_encode(value: $this->outcome(world: $world), flags: JSON_PRETTY_PRINT);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: JSON_PRETTY_PRINT)."\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()

	/**
	 * Read one scripted world.
	 *
	 * @param array<string,int|string> $world Rows per type, or a failure.
	 *
	 * @return array<string,mixed> The ids per type or the error, and the fetches.
	 */
	private function outcome(array $world): array {
		$calls = [];
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $objectType, ?int $limit = null, ?int $offset = null, array $filters = []) use ($world, &$calls): array {
				$calls[] = [$objectType, $limit, $offset, $filters];
				return $this->page(rows: ($world[$objectType] ?? 0), type: $objectType, window: [(int)$offset, (int)$limit]);
			}
		);

		$service = new WorldCopyService(objectFetcher: $fetcher, idList: new IdListNormaliser());
		$read = new \ReflectionMethod($service, 'readRules');
		try {
			$objects = $read->invoke($service, 'world-1');
			$outcome = ['ids' => array_map(fn (array $rows): array => array_column($rows, 'id'), $objects)];
		} catch (\Exception $e) {
			$outcome = ['error' => get_class($e).': '.$e->getMessage()];
		}

		return $outcome + ['calls' => $calls];
	}//end outcome()

	/**
	 * One page of a scripted type.
	 *
	 * @param int|string        $rows   The number of rows, or a failure.
	 * @param string            $type   The type.
	 * @param array{0:int,1:int} $window The offset and the limit.
	 *
	 * @return array<int,array<string,mixed>> The page.
	 */
	private function page(int|string $rows, string $type, array $window): array {
		[$offset, $limit] = $window;
		if ($rows === 'unconfigured' || ($rows === 'unconfigured-later' && $offset > 0)) {
			throw new \Exception('Register not configured for '.$type);
		}

		if ($rows === 'broken') {
			throw new \Exception('database gone');
		}

		if ($rows === 'gaps') {
			return [['id' => 'g1'], ['name' => 'no id'], ['id' => 'g2'], ['id' => null]];
		}

		$count = 600;
		if (is_int($rows) === true) {
			$count = $rows;
		}

		$page = [];
		for ($i = $offset; $i < min($count, ($offset + $limit)); $i++) {
			$page[] = ['id' => $type[0].$i];
		}

		return $page;
	}//end page()
}//end class
