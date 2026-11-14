<?php

/**
 * Golden test for the check-in roster and the run-sheet context of an event.
 *
 * Every case runs EventRosterService::buildRoster() and
 * EventRosterService::buildRunsheetContext() over a fake register (characters,
 * players, attendance records), some of whose reads throw. The result and the
 * ordered register reads are compared, as JSON text so key order and scalar
 * types count, with fixtures/event-roster.golden.json. Set RECORD_GOLDEN=1 to
 * rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Pins the roster rows, the cast list, the unique items and the read order.
 *
 * @covers \OCA\Larpinq\Service\EventRosterService
 */
class EventRosterGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/event-roster.golden.json';

	private const EVENT = [
		'id' => 'e1',
		'name' => 'Winter Court',
		'description' => 'A cold night',
		'startDate' => '2026-12-01',
		'location' => 'The hall',
		'effects' => [['stat' => 'xp', 'amount' => 2]],
	];

	/**
	 * The full fake register.
	 *
	 * @return array<string,list<mixed>>
	 */
	private function register(): array {
		return [
			'character' => [
				['id' => 'c-zed', 'name' => 'zed', 'type' => 'player', 'events' => ['e1'], 'player' => 'p-1', 'items' => ['i-1', 'i-2'], 'stats' => ['str' => 3]],
				['id' => 'c-amy', 'name' => 'Amy', 'type' => 'npc', 'events' => ['e1', 'e2'], 'ocName' => 'p-2', 'items' => 'not a list', 'approved' => 'yes'],
				['id' => 'c-bob', 'name' => 'Bob', 'events' => ['e2']],
				['id' => 'c-cat', 'name' => 'cat', 'events' => 'e1', 'slNotesPublic' => 'pub', 'slNotesPrivate' => 'priv'],
				['id' => 'c-dan', 'name' => 'Dan', 'events' => ['e1'], 'ocName' => 'Dan the player', 'items' => ['i-2', 3], 'conditions' => ['k-1']],
				'not a row',
			],
			'player' => [
				['id' => 'p-1', 'name' => 'Player One'],
				['id' => 'p-2'],
				['name' => 'no id'],
				'not a row',
			],
			'attendance' => [
				['id' => 'a-1', 'character' => 'c-zed', 'status' => 'checked-in', 'checkedInAt' => '2026-12-01T10:00:00+00:00', 'checkedInBy' => 'gm'],
				['id' => 'a-2', 'character' => '', 'status' => 'no-show'],
				['id' => 'a-3', 'character' => 'c-dan', 'status' => 'no-show'],
				'not a row',
			],
		];
	}//end register()

	/**
	 * The cases: name => object types whose read throws.
	 *
	 * @return array<string,list<string>>
	 */
	private function cases(): array {
		return [
			'everything readable' => [],
			'no attendance schema' => ['attendance'],
			'no players' => ['player'],
			'no characters' => ['character'],
			'nothing readable' => ['attendance', 'player', 'character'],
		];
	}//end cases()

	/**
	 * Run both builders for one case.
	 *
	 * @param list<string> $failing The object types whose read throws.
	 *
	 * @return array<string,mixed> The roster, the run-sheet and the reads.
	 */
	private function outcome(array $failing): array {
		$trace = [];
		$register = $this->register();
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $objectType, ?int $limit = null, ?int $offset = null, ?array $filters = []) use ($register, $failing, &$trace): array {
				$trace[] = [$objectType, $filters];
				if (in_array(needle: $objectType, haystack: $failing, strict: true) === true) {
					throw new RuntimeException(message: 'unavailable');
				}

				return $register[$objectType];
			}
		);
		$fetcher->method('getObjectOfType')->willReturnCallback(
			function (string $objectType, string $id) use ($register, $failing, &$trace): array {
				$trace[] = [$objectType, $id];
				if (in_array(needle: $objectType, haystack: $failing, strict: true) === true) {
					throw new RuntimeException(message: 'unavailable');
				}

				foreach ($register[$objectType] as $row) {
					if (($row['id'] ?? null) === $id) {
						return $row;
					}
				}

				throw new RuntimeException(message: 'not found');
			}
		);
		$service = new EventRosterService(objectFetcher: $fetcher);

		$roster = $service->buildRoster(eventId: 'e1');
		$rosterReads = $trace;
		$trace = [];
		$runsheet = $service->buildRunsheetContext(event: self::EVENT, eventId: 'e1');

		return ['roster' => $roster, 'rosterReads' => $rosterReads, 'runsheet' => $runsheet, 'runsheetReads' => $trace];
	}//end outcome()

	/**
	 * Every case gives the recorded roster and run-sheet.
	 *
	 * @return void
	 */
	public function testRosterAndRunsheetMatchTheGolden(): void {
		$actual = [];
		foreach ($this->cases() as $name => $failing) {
			$actual[$name] = json_encode(value: $this->outcome(failing: $failing), flags: JSON_THROW_ON_ERROR);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: $flags) . "\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true, flags: JSON_THROW_ON_ERROR);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($actual as $name => $json) {
			self::assertSame(expected: $expected[$name], actual: $json, message: $name);
		}
	}//end testRosterAndRunsheetMatchTheGolden()
}//end class
