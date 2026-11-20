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
			->with('character', 500, null, ['ownerUid' => 'alice-uid'])
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
				'alice' => ['userUid' => 'alice-uid'],
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
		$eventReads = 0;
		$this->fetcher->expects($this->exactly(count: 2))
			->method('getObject')
			->willReturnCallback(
				static function (string $type, string $id) use (&$eventReads): array {
					if ($type === 'event') {
						self::assertSame(expected: 'ev-gone', actual: $id);
						$eventReads++;
					}

					throw new RuntimeException('not found');
				}
			);

		$history = (new PlayerAttendanceService(objectFetcher: $this->fetcher))->history(playerId: 'alice');

		$this->assertSame(expected: 1, actual: $history['count']);
		$this->assertSame(expected: ['id' => 'ev-gone', 'name' => ''], actual: $history['events'][0]['event']);
		$this->assertSame(expected: '', actual: $history['events'][0]['eventStartDate']);
		$this->assertSame(expected: 1, actual: $eventReads);
	}//end testAnEventIsReadOnceAndAnUnreadableOneStaysEmpty()

	/**
	 * A player reading their own history gets their characters by `ownerUid`, never
	 * by an `ocName` filter OpenRegister refuses them, and only their own: another
	 * player's characters are not in the answer.
	 *
	 * @return void
	 */
	public function testAPlayerSeesTheirOwnCharactersByOwnerUidOnly(): void {
		$characters = [
			['id' => 'char-anna', 'name' => 'Mirela', 'ownerUid' => 'anna'],
			['id' => 'char-karel', 'name' => 'Oswin', 'ownerUid' => 'karel'],
		];
		$this->fetcher->method('getObject')->willReturnCallback(
			static fn (string $type, string $id): array => match ($type) {
				'player' => ['userUid' => 'anna'],
				'event' => ['name' => 'Summer Siege', 'startDate' => '2026-05-01'],
			}
		);
		$this->fetcher->expects($this->once())->method('getObjects')->willReturnCallback(
			static function (string $type, ?int $limit, ?int $offset, ?array $filters) use ($characters): array {
				self::assertSame(expected: ['ownerUid' => 'anna'], actual: $filters);

				return array_values(array_filter($characters, static fn (array $c): bool => $c['ownerUid'] === $filters['ownerUid']));
			}
		);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			static fn (string $type, array $filters): array => [['id' => 'att-'.$filters['character'], 'event' => 'ev-1']]
		);

		$history = (new PlayerAttendanceService(objectFetcher: $this->fetcher))->history(playerId: 'player-anna');

		$this->assertSame(expected: ['char-anna'], actual: array_map(static fn (array $row): string => $row['character']['id'], $history['events']));
	}//end testAPlayerSeesTheirOwnCharactersByOwnerUidOnly()

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
