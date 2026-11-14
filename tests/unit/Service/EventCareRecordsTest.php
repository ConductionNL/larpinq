<?php

/**
 * Tests for the event copies of players' care details
 * (players-care-details-and-erasure REQ-PCE-002, REQ-PCE-003), through the
 * real RegisterObjectFetcher over an in-memory OpenRegister.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/players-care-details-and-erasure/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/RegisterAnswers.php';
require_once __DIR__ . '/../Support/InMemoryOpenRegister.php';

use OCA\Larpinq\Service\EventCareRecords;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Tests\Unit\Support\InMemoryOpenRegister;
use OCA\Larpinq\Tests\Unit\Support\RegisterAnswers;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Anna (player p-anna) has an accepted registration r-1 for Winter Court 2026.
 *
 * @covers \OCA\Larpinq\Service\EventCareRecords
 * @covers \OCA\Larpinq\Service\RegisterObjectFetcher
 */
class EventCareRecordsTest extends TestCase {

	private const ANNA = 'a0000000-0000-4000-8000-000000000001';
	private const WINTER_COURT = 'e0000000-0000-4000-8000-000000000001';
	private const REGISTRATION = 'r0000000-0000-4000-8000-000000000001';

	/**
	 * The in-memory register.
	 *
	 * @var InMemoryOpenRegister
	 */
	private InMemoryOpenRegister $store;

	/**
	 * The clock, in seconds.
	 *
	 * @var int
	 */
	private int $now;

	/**
	 * Seed Anna, the event and her registration; the clock stands a month before the event.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = new InMemoryOpenRegister();
		$this->store->seed('player', ['id' => self::ANNA, 'name' => 'Anna de Vries', 'userUid' => 'anna', 'allergies' => 'hazelnuts', 'emergencyContactName' => 'Joost de Vries', 'emergencyContactPhone' => '+31 6 00000000']);
		$this->store->seed('event', ['id' => self::WINTER_COURT, 'name' => 'Winter Court 2026', 'startDate' => '2026-12-11T18:00:00+00:00', 'endDate' => '2026-12-13T16:00:00+00:00']);
		$this->store->seed('registration', ['id' => self::REGISTRATION, 'event' => self::WINTER_COURT, 'player' => self::ANNA, 'status' => 'accepted']);
		$this->now = strtotime('2026-11-14T12:00:00+00:00');
	}//end setUp()

	/**
	 * The service over the real fetcher and the in-memory register.
	 *
	 * @param LoggerInterface|null $logger The logger.
	 *
	 * @return EventCareRecords
	 */
	private function care(?LoggerInterface $logger = null): EventCareRecords {
		$store = $this->store;
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => RegisterAnswers::onlyService(id: $id, bound: 'OCA\OpenRegister\Service\ObjectService', service: $store)
		);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => RegisterAnswers::registerConfig(key: $key, default: $default)
		);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn (): int => $this->now);

		return new EventCareRecords(
			new RegisterObjectFetcher(container: $container, appManager: $apps, config: $config, logger: new NullLogger()),
			$time,
			$logger ?? new NullLogger()
		);
	}//end care()

	/**
	 * The stored copies.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function records(): array {
		return array_values($this->store->objects['eventcarerecord'] ?? []);
	}//end records()

	/**
	 * Accepting Anna's registration copies her care details for the event, kept until 30 days after it ends.
	 *
	 * @return void
	 */
	public function testAcceptingCopiesTheCareDetails(): void {
		$this->assertTrue($this->care()->copyForRegistration(registration: $this->store->objects['registration'][self::REGISTRATION]));

		$records = $this->records();
		$this->assertCount(1, $records);
		unset($records[0]['id']);
		$this->assertSame(
			[
				'event' => self::WINTER_COURT,
				'registration' => self::REGISTRATION,
				'player' => self::ANNA,
				'allergies' => 'hazelnuts',
				'medicalNotes' => null,
				'dietaryNeeds' => null,
				'emergencyContactName' => 'Joost de Vries',
				'emergencyContactPhone' => '+31 6 00000000',
				'eventEndDate' => '2026-12-13T16:00:00+00:00',
				'eraseAfter' => '2027-01-12T16:00:00+00:00',
			],
			$records[0]
		);

		// Read and written with the app's authority, whoever accepted the registration.
		$this->assertSame([false], array_unique(array_column($this->store->finds, 'rbac')));
		$this->assertSame(['event', 'player'], array_column($this->store->finds, 'schema'));
		$this->assertSame(['eventcarerecord'], array_column($this->store->saves, 'schema'));
	}//end testAcceptingCopiesTheCareDetails()

	/**
	 * The event's own retention days set the erase date.
	 *
	 * @return void
	 */
	public function testTheEventsRetentionDaysSetTheEraseDate(): void {
		$event = ['id' => self::WINTER_COURT, 'endDate' => '2026-12-13T16:00:00+00:00', 'careRetentionDays' => 7];
		$record = EventCareRecords::recordFor(event: $event, registration: ['id' => 'r', 'player' => 'p'], player: []);
		$this->assertSame('2026-12-20T16:00:00+00:00', $record['eraseAfter']);

		$noEnd = ['id' => self::WINTER_COURT, 'startDate' => '2026-12-11T18:00:00+00:00', 'careRetentionDays' => -3];
		$this->assertSame('2027-01-10T18:00:00+00:00', EventCareRecords::recordFor(event: $noEnd, registration: ['id' => 'r', 'player' => 'p'], player: [])['eraseAfter']);

		$this->assertNull(EventCareRecords::recordFor(event: ['id' => 'x'], registration: ['id' => 'r', 'player' => 'p'], player: []));
	}//end testTheEventsRetentionDaysSetTheEraseDate()

	/**
	 * A change before the event updates the one copy; a change after the start writes nothing.
	 *
	 * @return void
	 */
	public function testTheCopyFollowsUntilTheEventStarts(): void {
		$care = $this->care();
		$care->copyForRegistration(registration: $this->store->objects['registration'][self::REGISTRATION]);

		$this->store->objects['player'][self::ANNA]['allergies'] = 'hazelnuts, peanuts';
		$this->assertSame(1, $care->refreshForPlayer(playerId: self::ANNA));
		$this->assertCount(1, $this->records());
		$this->assertSame('hazelnuts, peanuts', $this->records()[0]['allergies']);

		$this->now = strtotime('2026-12-12T09:00:00+00:00');
		$this->store->objects['player'][self::ANNA]['allergies'] = 'none';
		$this->assertSame(0, $care->refreshForPlayer(playerId: self::ANNA));
		$this->assertSame('hazelnuts, peanuts', $this->records()[0]['allergies']);
	}//end testTheCopyFollowsUntilTheEventStarts()

	/**
	 * Only accepted registrations are refreshed.
	 *
	 * @return void
	 */
	public function testOnlyAcceptedRegistrationsAreRefreshed(): void {
		$this->store->objects['registration'][self::REGISTRATION]['status'] = 'waitlisted';

		$this->assertSame(0, $this->care()->refreshForPlayer(playerId: self::ANNA));
		$this->assertSame([], $this->records());
		$this->assertSame(0, $this->care()->refreshForPlayer(playerId: ''));
	}//end testOnlyAcceptedRegistrationsAreRefreshed()

	/**
	 * A player without care details gets no copy, but a cleared detail empties an existing copy.
	 *
	 * @return void
	 */
	public function testNoDetailsNoCopyButAClearedDetailFollows(): void {
		$care = $this->care();
		$care->copyForRegistration(registration: $this->store->objects['registration'][self::REGISTRATION]);

		foreach (EventCareRecords::FIELDS as $field) {
			unset($this->store->objects['player'][self::ANNA][$field]);
		}

		$this->assertSame(1, $care->refreshForPlayer(playerId: self::ANNA));
		$this->assertNull($this->records()[0]['allergies']);
		$this->assertNull($this->records()[0]['emergencyContactName']);

		$this->store->objects['eventcarerecord'] = [];
		$this->assertFalse($care->copyForRegistration(registration: $this->store->objects['registration'][self::REGISTRATION]));
		$this->assertSame([], $this->records());
	}//end testNoDetailsNoCopyButAClearedDetailFollows()

	/**
	 * Incomplete registrations, a missing player and a failing read write nothing; the failure is logged without details.
	 *
	 * @return void
	 */
	public function testNothingIsWrittenWithoutWhatTheCopyNeeds(): void {
		$this->assertFalse($this->care()->copyForRegistration(registration: ['id' => self::REGISTRATION, 'event' => self::WINTER_COURT]));

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with(
			$this->stringContains('could not be copied'),
			$this->callback(static fn (array $context): bool => ($context['registration'] ?? null) === self::REGISTRATION && str_contains(json_encode($context) ?: '', 'hazelnuts') === false)
		);
		$missingPlayer = ['id' => self::REGISTRATION, 'event' => self::WINTER_COURT, 'player' => 'b0000000-0000-4000-8000-000000000009'];
		$this->assertFalse($this->care(logger: $logger)->copyForRegistration(registration: $missingPlayer));
		$this->assertSame([], $this->records());
	}//end testNothingIsWrittenWithoutWhatTheCopyNeeds()
}//end class
