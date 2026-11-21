<?php

/**
 * Unit tests for RegistrationPlaces.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationPlaces;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Place limits per ticket type, per option and for the event as a whole.
 *
 * @covers \OCA\Larpinq\Service\RegistrationPlaces
 */
class RegistrationPlacesTest extends TestCase {

	/** @var RegisterObjectFetcher&MockObject */
	private RegisterObjectFetcher $fetcher;

	/**
	 * The places.
	 *
	 * @var RegistrationPlaces
	 */
	private RegistrationPlaces $places;

	/**
	 * Rows the fake register answers, by object type.
	 *
	 * @var array<string, list<array<string, mixed>>>
	 */
	private array $register = [];

	/**
	 * Every read: [objectType, filters, limit].
	 *
	 * @var list<array{0: string, 1: array<string, mixed>, 2: int|null}>
	 */
	private array $reads = [];

	/**
	 * Set up the subject and its collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $filters, ?int $limit = null): array {
				$this->reads[] = [$objectType, $filters, $limit];
				return array_values(array_filter(
					$this->register[$objectType] ?? [],
					static function (array $row) use ($filters): bool {
						foreach ($filters as $field => $value) {
							if ((string)($row[$field] ?? '') !== (string)$value) {
								return false;
							}
						}

						return true;
					}
				));
			}
		);
		$this->places = new RegistrationPlaces(fetcher: $this->fetcher);
	}

	/**
	 * An accepted registration of event ev1.
	 *
	 * @param string               $id     The registration id.
	 * @param array<string, mixed> $fields Further fields.
	 *
	 * @return array<string, mixed>
	 */
	private static function accepted(string $id, array $fields = []): array {
		return ['id' => $id, 'event' => 'ev1', 'status' => 'accepted'] + $fields;
	}

	/**
	 * A ticket without ticket type or event has no limit and reads nothing.
	 *
	 * @return void
	 */
	public function testATicketWithoutTicketTypeOrEventHasNoLimitAndReadsNothing(): void {
		$this->assertNull(actual: $this->places->ticketLimit(registration: ['event' => 'ev1']));
		$this->assertNull(actual: $this->places->ticketLimit(registration: ['ticketType' => 't1']));
		$this->assertFalse(condition: $this->places->ticketTypeFull(registration: ['ticketType' => 't1']));
		$this->assertSame(expected: [], actual: $this->reads);
	}

	/**
	 * The limit is the ticket types place limit.
	 *
	 * @return void
	 */
	public function testTheLimitIsTheTicketTypesPlaceLimit(): void {
		$this->register['tickettype'] = [
			['id' => 't1', 'event' => 'ev1', 'placeLimit' => '12'],
			['id' => 't2', 'event' => 'ev1', 'placeLimit' => 3],
		];

		$this->assertSame(expected: 12, actual: $this->places->ticketLimit(registration: ['ticketType' => 't1', 'event' => 'ev1']));
		$this->assertSame(expected: 3, actual: $this->places->ticketLimit(registration: ['ticketType' => 't2', 'event' => 'ev1']));
		$this->assertSame(expected: ['tickettype', ['event' => 'ev1'], 2000], actual: $this->reads[0]);
	}

	/**
	 * A non numeric or missing place limit is no limit.
	 *
	 * @return void
	 */
	public function testANonNumericOrMissingPlaceLimitIsNoLimit(): void {
		$this->register['tickettype'] = [
			['id' => 't1', 'event' => 'ev1', 'placeLimit' => 'many'],
			['id' => 't2', 'event' => 'ev1'],
		];

		$this->assertNull(actual: $this->places->ticketLimit(registration: ['ticketType' => 't1', 'event' => 'ev1']));
		$this->assertNull(actual: $this->places->ticketLimit(registration: ['ticketType' => 't2', 'event' => 'ev1']));
		$this->assertNull(actual: $this->places->ticketLimit(registration: ['ticketType' => 't9', 'event' => 'ev1']));
		$this->assertFalse(condition: $this->places->ticketTypeFull(registration: ['ticketType' => 't2', 'event' => 'ev1']));
	}

	/**
	 * A ticket type is full when others take every place.
	 *
	 * @return void
	 */
	public function testATicketTypeIsFullWhenOthersTakeEveryPlace(): void {
		$this->register['tickettype'] = [['id' => 't1', 'event' => 'ev1', 'placeLimit' => 2]];
		$this->register['registration'] = [
			self::accepted(id: 'r1', fields: ['ticketType' => 't1']),
			self::accepted(id: 'r2', fields: ['ticketType' => 't1']),
			self::accepted(id: 'r3', fields: ['ticketType' => 't2']),
			['id' => 'r4', 'event' => 'ev1', 'status' => 'pending', 'ticketType' => 't1'],
		];

		$this->assertTrue(condition: $this->places->ticketTypeFull(registration: ['id' => 'r9', 'ticketType' => 't1', 'event' => 'ev1']));
		$this->assertSame(expected: ['registration', ['event' => 'ev1', 'status' => 'accepted'], 2000], actual: $this->reads[1]);
	}

	/**
	 * The registration itself does not take its own place.
	 *
	 * @return void
	 */
	public function testTheRegistrationItselfDoesNotTakeItsOwnPlace(): void {
		$this->register['tickettype'] = [['id' => 't1', 'event' => 'ev1', 'placeLimit' => 2]];
		$this->register['registration'] = [
			self::accepted(id: 'r1', fields: ['ticketType' => 't1']),
			self::accepted(id: 'r2', fields: ['ticketType' => 't1']),
		];

		$this->assertFalse(condition: $this->places->ticketTypeFull(registration: ['id' => 'r2', 'ticketType' => 't1', 'event' => 'ev1']));
	}

	/**
	 * A limit of zero is always full.
	 *
	 * @return void
	 */
	public function testALimitOfZeroIsAlwaysFull(): void {
		$this->register['tickettype'] = [['id' => 't1', 'event' => 'ev1', 'placeLimit' => 0]];

		$this->assertTrue(condition: $this->places->ticketTypeFull(registration: ['id' => 'r1', 'ticketType' => 't1', 'event' => 'ev1']));
	}

	/**
	 * Places taken counts accepted registrations and players not yet registered.
	 *
	 * @return void
	 */
	public function testPlacesTakenCountsAcceptedRegistrationsAndPlayersNotYetRegistered(): void {
		$this->register['registration'] = [
			self::accepted(id: 'r1', fields: ['character' => 'c1']),
			self::accepted(id: 'r2', fields: ['character' => 'c2']),
		];
		$event = ['id' => 'ev1', 'players' => ['c1', 'c3', 'c4']];

		$this->assertSame(expected: 4, actual: $this->places->placesTaken(event: $event, excludeId: ''));
	}

	/**
	 * Places taken leaves out the excluded registration.
	 *
	 * @return void
	 */
	public function testPlacesTakenLeavesOutTheExcludedRegistration(): void {
		$this->register['registration'] = [
			self::accepted(id: 'r1', fields: ['character' => 'c1']),
			self::accepted(id: 'r2', fields: ['character' => 'c2']),
		];

		$this->assertSame(expected: 1, actual: $this->places->placesTaken(event: ['id' => 'ev1'], excludeId: 'r2'));
		$this->assertSame(expected: 2, actual: $this->places->placesTaken(event: ['id' => 'ev1', 'players' => ['c2']], excludeId: 'r2'));
	}

	/**
	 * Places taken of an empty event is zero.
	 *
	 * @return void
	 */
	public function testPlacesTakenOfAnEmptyEventIsZero(): void {
		$this->assertSame(expected: 0, actual: $this->places->placesTaken(event: ['id' => 'ev1'], excludeId: ''));
	}

	/**
	 * An option without a numeric limit is never full and reads nothing.
	 *
	 * @return void
	 */
	public function testAnOptionWithoutANumericLimitIsNeverFullAndReadsNothing(): void {
		$this->assertFalse(condition: $this->places->optionFull(option: ['id' => 'o1', 'event' => 'ev1'], registration: []));
		$this->assertFalse(condition: $this->places->optionFull(option: ['id' => 'o1', 'event' => 'ev1', 'placeLimit' => 'lots'], registration: []));
		$this->assertSame(expected: [], actual: $this->reads);
	}

	/**
	 * An option is full when others chose it up to the limit.
	 *
	 * @return void
	 */
	public function testAnOptionIsFullWhenOthersChoseItUpToTheLimit(): void {
		$this->register['registration'] = [
			self::accepted(id: 'r1', fields: ['options' => ['o1', 'o2']]),
			self::accepted(id: 'r2', fields: ['options' => ['o2']]),
			self::accepted(id: 'r3', fields: ['options' => 'o1']),
		];
		$option = ['id' => 'o1', 'event' => 'ev1', 'placeLimit' => '2'];

		$this->assertTrue(condition: $this->places->optionFull(option: $option, registration: ['id' => 'r9']));
		$this->assertFalse(condition: $this->places->optionFull(option: $option, registration: ['id' => 'r1']));
	}

	/**
	 * Option ids are compared as strings.
	 *
	 * @return void
	 */
	public function testOptionIdsAreComparedAsStrings(): void {
		$this->register['registration'] = [self::accepted(id: 'r1', fields: ['options' => [7]])];

		$this->assertTrue(condition: $this->places->optionFull(option: ['id' => 7, 'event' => 'ev1', 'placeLimit' => 1], registration: ['id' => 'r9']));
	}

	/**
	 * The players after a change keep their order, drop the leaver and add a newcomer once.
	 *
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 *
	 * @return void
	 */
	public function testPlayersAfterAChange(): void {
		$event = ['players' => ['c1', 'c2', 3]];

		$this->assertSame(expected: ['c1', '3', 'c4'], actual: $this->places->playersAfter(event: $event, move: ['remove' => 'c2', 'add' => 'c4']));
		$this->assertSame(expected: ['c1', 'c2', '3'], actual: $this->places->playersAfter(event: $event, move: ['remove' => '', 'add' => 'c1']));
		$this->assertSame(expected: ['c1', 'c2', '3'], actual: $this->places->playersAfter(event: $event, move: ['remove' => '', 'add' => '']));
		$this->assertSame(expected: ['c9'], actual: $this->places->playersAfter(event: [], move: ['remove' => 'c1', 'add' => 'c9']));
	}//end testPlayersAfterAChange()
}
