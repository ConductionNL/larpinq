<?php

/**
 * Golden test for the schema application-id repair step.
 *
 * Each case scripts the slugs under the new and the old application id (or
 * a failing read or update) and records the repair output, the warnings and
 * the UPDATE statements. Compared with
 * fixtures/migrate-schema-application-id.golden.json. Set RECORD_GOLDEN=1 to
 * rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Repair
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec exclude The application-id migration has no canonical spec.
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Repair;

use OCA\Larpinq\Repair\MigrateSchemaApplicationId;
use OCP\DB\IResult;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The repair step, case by case.
 *
 * @covers \OCA\Larpinq\Repair\MigrateSchemaApplicationId
 */
class MigrateSchemaApplicationIdGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/migrate-schema-application-id.golden.json';

	/**
	 * The cases: slugs under the new and the old id, or 'fail', and slugs whose update fails.
	 *
	 * @return array<string,array<string,mixed>> Case name to case.
	 */
	private static function cases(): array {
		return [
			'nothing anywhere' => ['new' => [], 'old' => []],
			'new id unreadable' => ['new' => 'fail', 'old' => ['character']],
			'old id unreadable' => ['new' => [], 'old' => 'fail'],
			'nothing on the old id' => ['new' => ['character'], 'old' => []],
			'all move' => ['new' => [], 'old' => ['character', 'skill', 'item']],
			'one twin' => ['new' => ['skill'], 'old' => ['character', 'skill']],
			'twin by case' => ['new' => ['skill'], 'old' => ['Skill', 'item']],
			'all twins' => ['new' => ['a', 'b'], 'old' => ['a', 'b']],
			'an update fails' => ['new' => [], 'old' => ['a', 'b', 'c'], 'failing' => ['b']],
			'twin and failing update' => ['new' => ['a'], 'old' => ['a', 'b'], 'failing' => ['b']],
		];
	}//end cases()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach (self::cases() as $name => $case) {
			$actual[$name] = json_encode(value: $this->outcome(case: $case + ['failing' => []]), flags: JSON_PRETTY_PRINT);
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
	 * Run one case.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return array<string,mixed> The output, the log lines and the updates.
	 */
	private function outcome(array $case): array {
		$trace = new \ArrayObject(['output' => [], 'log' => [], 'updates' => []]);
		$reads = [$case['new'], $case['old']];
		$db = $this->createMock(originalClassName: IDBConnection::class);
		$db->method('executeQuery')->willReturnCallback(
			function () use (&$reads): IResult {
				$slugs = array_shift($reads);
				if ($slugs === 'fail') {
					throw new \OCP\DB\Exception('read failed');
				}

				$result = $this->createMock(originalClassName: IResult::class);
				$result->method('fetchAll')->willReturn(array_map(static fn (string $s): array => ['slug' => $s], $slugs));
				return $result;
			}
		);
		$db->method('executeStatement')->willReturnCallback(
			function (string $sql, array $params) use ($case, $trace): int {
				$this->append(trace: $trace, entry: ['updates', $params]);
				if (in_array($params[2], $case['failing'], true) === true) {
					throw new \OCP\DB\Exception('update failed');
				}

				return 1;
			}
		);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['warning', 'error', 'info'] as $level) {
			$logger->method($level)->willReturnCallback(
				function (string $message, array $context = []) use ($level, $trace): void {
					unset($context['exception']);
					$this->append(trace: $trace, entry: ['log', [$level, $message, $context]]);
				}
			);
		}

		$output = $this->createMock(originalClassName: IOutput::class);
		$output->method('info')->willReturnCallback(
			function (string $message) use ($trace): void {
				$this->append(trace: $trace, entry: ['output', $message]);
			}
		);

		(new MigrateSchemaApplicationId(db: $db, logger: $logger))->run(output: $output);

		return $trace->getArrayCopy();
	}//end outcome()

	/**
	 * Append an entry to one list of the trace.
	 *
	 * @param \ArrayObject       $trace The trace.
	 * @param array{0:string,1:mixed} $entry The list name and the entry.
	 *
	 * @return void
	 */
	private function append(\ArrayObject $trace, array $entry): void {
		$list = $trace[$entry[0]];
		$list[] = $entry[1];
		$trace[$entry[0]] = $list;
	}//end append()
}//end class
