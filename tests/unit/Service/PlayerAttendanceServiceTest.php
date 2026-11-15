<?php

/**
 * Unit tests for PlayerAttendanceService.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\PlayerAttendanceService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A player's attendance history over all their characters.
 *
 * @covers \OCA\Larpinq\Service\PlayerAttendanceService
 */
class PlayerAttendanceServiceTest extends TestCase {

	/**
	 * The fetcher double.
	 *
	 * @var RegisterObjectFetcher&MockObject
	 */
	private RegisterObjectFetcher $fetcher;

	/**
	 * Wire the fetcher over canned characters, attendance and events.
	 *
	 * @param list<array<string, mixed>>               $characters The player's characters
	 * @param array<string, list<array<string, mixed>>> $attendance Checked-in records per character id
	 * @param array<string, array<string, mixed>>      $events     Events by id; a missing id throws
	 *
	 * @return PlayerAttendanceService
	 */
	private function service(array $characters, array $attendance, array $events): PlayerAttendanceService {
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObjects')->willReturnCallback(
			static function (string $objectType, ?int $limit = null, ?int $offset = null, ?array $filters = []) use ($characters): array {
				self::assertSame('character', $objectType);
				self::assertSame(500, $limit);
				self::assertSame(['ocName' => 'player-1'], $filters);
				return $characters;
			}
		);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			static function (string $objectType, array $filters, ?int $limit = null) use ($attendance): array {
				self::assertSame('attendance', $objectType);
				self::assertSame('checked-in', $filters['status']);
				self::assertSame(500, $limit);
				return ($attendance[$filters['character']] ?? []);
			}
		);
		$this->fetcher->method('getObject')->willReturnCallback(
			static function (string $objectType, string $id) use ($events): array {
				self::assertSame('event', $objectType);
				if (isset($events[$id]) === false) {
					throw new RuntimeException('not found');
				}

				return $events[$id];
			}
		);

		return new PlayerAttendanceService(objectFetcher: $this->fetcher);

	}//end service()

	/**
	 * Attendance over every character comes back newest event first, counting distinct events.
	 *
	 * @return void
	 */
	public function testHistoryListsAttendanceNewestFirstAndCountsDistinctEvents(): void {
		$service = $this->service(
			[
				['id' => 'char-a', 'name' => 'Aldric'],
				['id' => ['id' => 'char-b'], 'name' => 'Brenna'],
			],
			[
				'char-a' => [
					['id' => 'att-1', 'event' => 'ev-old', 'checkedInAt' => '2025-03-01T10:00:00+00:00'],
					['id' => 'att-2', 'event' => ['uuid' => 'ev-new'], 'checkedInAt' => '2026-06-01T10:00:00+00:00'],
				],
				'char-b' => [
					['id' => 'att-3', 'event' => 'ev-new'],
				],
			],
			[
				'ev-old' => ['name' => 'Winter Gathering', 'startDate' => '2025-03-01'],
				'ev-new' => ['name' => 'Summer Siege', 'startDate' => '2026-06-01'],
			]
		);

		$history = $service->history(playerId: 'player-1');

		self::assertSame(2, $history['count']);
		self::assertSame(['att-2', 'att-3', 'att-1'], array_column($history['events'], 'id'));
		self::assertSame(
			[
				'id' => 'att-2',
				'event' => ['id' => 'ev-new', 'name' => 'Summer Siege'],
				'character' => ['id' => 'char-a', 'name' => 'Aldric'],
				'eventStartDate' => '2026-06-01',
				'checkedInAt' => '2026-06-01T10:00:00+00:00',
			],
			$history['events'][0]
		);
		self::assertSame(['id' => 'char-b', 'name' => 'Brenna'], $history['events'][1]['character']);
		self::assertSame('', $history['events'][1]['checkedInAt']);

	}//end testHistoryListsAttendanceNewestFirstAndCountsDistinctEvents()

	/**
	 * Each event is read once, however many records point at it.
	 *
	 * @return void
	 */
	public function testEachEventIsReadOnce(): void {
		$service = $this->service(
			[['id' => 'char-a', 'name' => 'Aldric']],
			['char-a' => [['id' => 'att-1', 'event' => 'ev-1'], ['id' => 'att-2', 'event' => 'ev-1'], ['id' => 'att-3', 'event' => 'ev-gone']]],
			['ev-1' => ['name' => 'Moot', 'startDate' => '2026-01-01']]
		);
		$this->fetcher->expects(self::exactly(2))->method('getObject');

		$history = $service->history(playerId: 'player-1');

		self::assertSame(2, $history['count']);

	}//end testEachEventIsReadOnce()

	/**
	 * An unreadable event still lists the record, without a name or date.
	 *
	 * @return void
	 */
	public function testAnUnreadableEventKeepsTheRecordWithoutDetails(): void {
		$service = $this->service(
			[['id' => 'char-a', 'name' => 'Aldric']],
			['char-a' => [['id' => 'att-1', 'event' => 'ev-gone', 'checkedInAt' => '2026-01-01T09:00:00+00:00']]],
			[]
		);

		$history = $service->history(playerId: 'player-1');

		self::assertSame(1, $history['count']);
		self::assertSame(['id' => 'ev-gone', 'name' => ''], $history['events'][0]['event']);
		self::assertSame('', $history['events'][0]['eventStartDate']);

	}//end testAnUnreadableEventKeepsTheRecordWithoutDetails()

	/**
	 * A character without a usable id is skipped without reading attendance.
	 *
	 * @return void
	 */
	public function testACharacterWithoutAnIdIsSkipped(): void {
		$service = $this->service([['name' => 'Nameless'], ['id' => 42, 'name' => 'Numbered']], [], []);
		$this->fetcher->expects(self::never())->method('getObjectsWithAppAuthority');

		self::assertSame(['count' => 0, 'events' => []], $service->history(playerId: 'player-1'));

	}//end testACharacterWithoutAnIdIsSkipped()
}//end class
