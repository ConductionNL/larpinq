<?php

/**
 * Unit tests for PlayerAttendanceService: a player's checked-in events across their characters.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
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
 * @covers \OCA\Larpinq\Service\PlayerAttendanceService
 */
class PlayerAttendanceServiceBehaviourTest extends TestCase {

	/**
	 * The register reader.
	 *
	 * @var RegisterObjectFetcher&MockObject
	 */
	private RegisterObjectFetcher&MockObject $fetcher;

	/**
	 * Build the reader double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
	}//end setUp()

	/**
	 * Every checked-in attendance of every character of the player is listed,
	 * newest event first, and the count is of distinct events.
	 *
	 * @return void
	 */
	public function testHistoryListsCheckedInEventsNewestFirst(): void {
		$this->fetcher->expects($this->once())
			->method('getObjects')
			->with('character', 500, null, ['ocName' => 'alice'])
			->willReturn([
				['id' => 'char-1', 'name' => 'Aria'],
				['id' => ['id' => 'char-2'], 'name' => 'Bram'],
				['name' => 'No id'],
			]);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			static function (string $type, array $filters, ?int $limit): array {
				self::assertSame(expected: 'attendance', actual: $type);
				self::assertSame(expected: 'checked-in', actual: $filters['status']);
				self::assertSame(expected: 500, actual: $limit);

				return match ($filters['character']) {
					'char-1' => [
						['id' => 'att-1', 'event' => 'ev-old', 'checkedInAt' => '2025-05-01T10:00:00Z'],
						['id' => 'att-2', 'event' => ['id' => 'ev-new'], 'checkedInAt' => '2026-05-01T10:00:00Z'],
					],
					'char-2' => [['id' => 'att-3', 'event' => ['uuid' => 'ev-new']]],
				};
			}
		);
		$this->fetcher->method('getObject')->willReturnCallback(
			static fn (string $type, string $id): array => match ($id) {
				'ev-old' => ['name' => 'Spring Feast', 'startDate' => '2025-05-01'],
				'ev-new' => ['name' => 'Summer Siege', 'startDate' => '2026-05-01'],
			}
		);

		$history = (new PlayerAttendanceService(objectFetcher: $this->fetcher))->history(playerId: 'alice');

		$this->assertSame(expected: 2, actual: $history['count']);
		$this->assertSame(expected: ['att-2', 'att-3', 'att-1'], actual: array_column($history['events'], 'id'));
		$this->assertSame(
			expected: [
				'id' => 'att-1',
				'event' => ['id' => 'ev-old', 'name' => 'Spring Feast'],
				'character' => ['id' => 'char-1', 'name' => 'Aria'],
				'eventStartDate' => '2025-05-01',
				'checkedInAt' => '2025-05-01T10:00:00Z',
			],
			actual: $history['events'][2]
		);
		$this->assertSame(expected: ['id' => 'char-2', 'name' => 'Bram'], actual: $history['events'][1]['character']);
		$this->assertSame(expected: '', actual: $history['events'][1]['checkedInAt']);
	}//end testHistoryListsCheckedInEventsNewestFirst()

	/**
	 * Each event is read once, and an event that cannot be read leaves its name and date empty.
	 *
	 * @return void
	 */
	public function testAnEventIsReadOnceAndAnUnreadableOneStaysEmpty(): void {
		$this->fetcher->method('getObjects')->willReturn([['id' => 'char-1', 'name' => 'Aria']]);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturn([
			['id' => 'att-1', 'event' => 'ev-gone'],
			['id' => 'att-2', 'event' => 'ev-gone'],
		]);
		$this->fetcher->expects($this->once())
			->method('getObject')
			->with('event', 'ev-gone')
			->willThrowException(new RuntimeException('not found'));

		$history = (new PlayerAttendanceService(objectFetcher: $this->fetcher))->history(playerId: 'alice');

		$this->assertSame(expected: 1, actual: $history['count']);
		$this->assertSame(expected: ['id' => 'ev-gone', 'name' => ''], actual: $history['events'][0]['event']);
		$this->assertSame(expected: '', actual: $history['events'][0]['eventStartDate']);
	}//end testAnEventIsReadOnceAndAnUnreadableOneStaysEmpty()

	/**
	 * A player without characters has no history, and no attendance is read.
	 *
	 * @return void
	 */
	public function testAPlayerWithoutCharactersHasNoHistory(): void {
		$this->fetcher->method('getObjects')->willReturn([]);
		$this->fetcher->expects($this->never())->method('getObjectsWithAppAuthority');

		$this->assertSame(
			expected: ['count' => 0, 'events' => []],
			actual: (new PlayerAttendanceService(objectFetcher: $this->fetcher))->history(playerId: 'nobody')
		);
	}//end testAPlayerWithoutCharactersHasNoHistory()
}//end class
