<?php

/**
 * XP for everyone checked in at an event: the amount has to be positive, the
 * event has to exist, only checked-in characters get an award, each once, and
 * one failed write does not stop the others.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Larpinq\Service\AttendeeXpAward;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(AttendeeXpAward::class)]
class AttendeeXpAwardTest extends TestCase {

	private const EVENT = 'event-1';

	/** @var RegisterObjectFetcher&MockObject */
	private RegisterObjectFetcher $objects;

	/** @var EventRosterService&MockObject */
	private EventRosterService $roster;

	/** @var list<array<string,mixed>> The award objects written. */
	private array $written = [];

	protected function setUp(): void {
		$this->objects = $this->createMock(RegisterObjectFetcher::class);
		$this->roster  = $this->createMock(EventRosterService::class);
		$this->roster->method('getEvent')->willReturnCallback(
			static fn (string $eventId): ?array => $eventId === self::EVENT ? ['id' => self::EVENT, 'name' => 'Winter feast'] : null
		);
	}

	public function testEveryCheckedInCharacterGetsOneAward(): void {
		$this->attendance(rows: [
			['character' => 'c-1', 'status' => 'checked-in'],
			['character' => 'c-2', 'status' => 'registered'],
			['character' => 'c-3', 'status' => 'checked-in'],
			['character' => 'c-1', 'status' => 'checked-in'],
			['character' => '', 'status' => 'checked-in'],
			['status' => 'checked-in'],
		]);
		$this->saves(saved: static fn (array $data): array => ['id' => 'award-' . $data['character']]);
		$before = time();

		$result = $this->service()->award(eventId: self::EVENT, grant: ['amount' => '3', 'reason' => 'Great roleplay', 'awardedBy' => 'joris']);

		self::assertSame(self::EVENT, $result['event']);
		self::assertSame(3, $result['amount']);
		self::assertSame('Great roleplay', $result['reason']);
		self::assertSame(
			[
				['character' => 'c-1', 'status' => 'created', 'award' => 'award-c-1'],
				['character' => 'c-3', 'status' => 'created', 'award' => 'award-c-3'],
			],
			$result['results']
		);
		self::assertSame(['c-1', 'c-3'], array_column($this->written, 'character'));
		$first = $this->written[0];
		self::assertSame([self::EVENT, 3, 'Great roleplay', 'joris', 'mcp'], [$first['event'], $first['amount'], $first['reason'], $first['awardedBy'], $first['awardedVia']]);
		self::assertGreaterThanOrEqual($before, strtotime($first['awardedAt']));
	}

	public function testTheAwardIdFallsBackToTheUuidAndThenToNothing(): void {
		$this->attendance(rows: [
			['character' => 'c-1', 'status' => 'checked-in'],
			['character' => 'c-2', 'status' => 'checked-in'],
		]);
		$this->saves(saved: static fn (array $data): array => $data['character'] === 'c-1' ? ['uuid' => 'uuid-1'] : []);

		$result = $this->service()->award(eventId: self::EVENT, grant: ['amount' => 1]);

		self::assertSame(['uuid-1', ''], array_column($result['results'], 'award'));
		self::assertSame('', $result['reason']);
		self::assertSame('', $this->written[0]['awardedBy']);
	}

	public function testOneFailedWriteDoesNotStopTheOthers(): void {
		$this->attendance(rows: [
			['character' => 'c-1', 'status' => 'checked-in'],
			['character' => 'c-2', 'status' => 'checked-in'],
		]);
		$this->saves(saved: static function (array $data): array {
			if ($data['character'] === 'c-1') {
				throw new RuntimeException('Schema validation failed');
			}

			return ['id' => 'award-2'];
		});

		$result = $this->service()->award(eventId: self::EVENT, grant: ['amount' => 2]);

		self::assertSame(
			[
				['character' => 'c-1', 'status' => 'failed', 'error' => 'Schema validation failed'],
				['character' => 'c-2', 'status' => 'created', 'award' => 'award-2'],
			],
			$result['results']
		);
	}

	/**
	 * Amounts below one XP.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function tooSmall(): array {
		return [
			'zero'     => [0],
			'negative' => [-5],
			'text'     => ['many'],
			'missing'  => [null],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('tooSmall')]
	public function testAnAmountBelowOneIsRefusedBeforeAnythingIsRead(mixed $amount): void {
		$this->roster->expects(self::never())->method('getEvent');
		$this->objects->expects(self::never())->method('getObjects');
		$this->objects->expects(self::never())->method('saveObject');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The amount has to be at least 1 XP.');

		$grant = $amount === null ? [] : ['amount' => $amount];
		$this->service()->award(eventId: self::EVENT, grant: $grant);
	}

	public function testAnUnknownEventIsRefused(): void {
		$this->objects->expects(self::never())->method('getObjects');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Event not found: event-9');

		$this->service()->award(eventId: 'event-9', grant: ['amount' => 1]);
	}

	public function testNobodyCheckedInIsRefusedAndNothingIsWritten(): void {
		$this->attendance(rows: [['character' => 'c-1', 'status' => 'registered']]);
		$this->objects->expects(self::never())->method('saveObject');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Nobody is checked in at event event-1, so no XP was awarded. Check characters in first.');

		$this->service()->award(eventId: self::EVENT, grant: ['amount' => 1]);
	}

	public function testAnUnreadableAttendanceListCountsAsNobodyCheckedIn(): void {
		$this->objects->method('getObjects')->willThrowException(new RuntimeException('register offline'));
		$this->objects->expects(self::never())->method('saveObject');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Nobody is checked in at event event-1');

		$this->service()->award(eventId: self::EVENT, grant: ['amount' => 1]);
	}

	/**
	 * The attendance rows the fetcher returns for the event.
	 *
	 * @param list<array<string,string>> $rows The rows.
	 *
	 * @return void
	 */
	private function attendance(array $rows): void {
		$this->objects->expects(self::once())
			->method('getObjects')
			->with('attendance', null, null, ['event' => self::EVENT])
			->willReturn($rows);
	}

	/**
	 * What a saved award returns, recording each write.
	 *
	 * @param callable(array<string,mixed>):array<string,mixed> $saved The saved object for the written data.
	 *
	 * @return void
	 */
	private function saves(callable $saved): void {
		$this->objects->method('saveObject')->willReturnCallback(
			function (string $objectType, array $data) use ($saved): array {
				self::assertSame('xpAward', $objectType);
				$this->written[] = $data;
				return $saved($data);
			}
		);
	}

	/**
	 * The service over the mocks.
	 *
	 * @return AttendeeXpAward
	 */
	private function service(): AttendeeXpAward {
		return new AttendeeXpAward(objectFetcher: $this->objects, roster: $this->roster);
	}
}
