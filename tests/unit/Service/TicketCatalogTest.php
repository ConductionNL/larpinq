<?php

/**
 * Unit tests for TicketCatalog.
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

use DateTimeImmutable;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\TicketCatalog;
use OCA\Larpinq\Service\TicketChoiceRefusedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Access codes, sale windows, hidden tickets and the counts per ticket and option.
 *
 * @covers \OCA\Larpinq\Service\TicketCatalog
 * @uses   \OCA\Larpinq\Service\TicketChoiceRefusedException
 */
class TicketCatalogTest extends TestCase {

	/** @var RegisterObjectFetcher&MockObject */
	private RegisterObjectFetcher $fetcher;

	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	/**
	 * The catalog.
	 *
	 * @var TicketCatalog
	 */
	private TicketCatalog $catalog;

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
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->catalog = new TicketCatalog(fetcher: $this->fetcher, logger: $this->logger);
	}

	/**
	 * A moment relative to now.
	 *
	 * @param string $modify The relative change, such as `+1 day`.
	 *
	 * @return string
	 */
	private static function moment(string $modify): string {
		return (new DateTimeImmutable())->modify(modifier: $modify)->format(DATE_ATOM);
	}

	/**
	 * An empty code matches nothing without reading.
	 *
	 * @return void
	 */
	public function testAnEmptyCodeMatchesNothingWithoutReading(): void {
		$this->assertNull(actual: $this->catalog->matchCode(eventId: 'ev1', code: '', registration: [], keptCode: ''));
		$this->assertSame(expected: [], actual: $this->reads);
	}

	/**
	 * An unknown code is refused.
	 *
	 * @return void
	 */
	public function testAnUnknownCodeIsRefused(): void {
		$this->register['accesscode'] = [['id' => 'c1', 'event' => 'ev1', 'code' => 'CREW']];

		$this->expectException(exception: TicketChoiceRefusedException::class);
		$this->expectExceptionMessage(message: 'This code is not known for this event.');
		$this->catalog->matchCode(eventId: 'ev1', code: 'NPC', registration: [], keptCode: '');
	}

	/**
	 * A code of another event is refused.
	 *
	 * @return void
	 */
	public function testACodeOfAnotherEventIsRefused(): void {
		$this->register['accesscode'] = [['id' => 'c1', 'event' => 'ev2', 'code' => 'CREW']];

		$this->expectException(exception: TicketChoiceRefusedException::class);
		$this->catalog->matchCode(eventId: 'ev1', code: 'CREW', registration: [], keptCode: '');
	}

	/**
	 * A code matches whatever its case.
	 *
	 * @return void
	 */
	public function testACodeMatchesWhateverItsCase(): void {
		$row = ['id' => 'c1', 'event' => 'ev1', 'code' => 'Crew2026'];
		$this->register['accesscode'] = [$row];

		$this->assertSame(expected: $row, actual: $this->catalog->matchCode(eventId: 'ev1', code: 'cREW2026', registration: ['id' => 'r1'], keptCode: ''));
		$this->assertSame(expected: ['accesscode', ['event' => 'ev1'], 2000], actual: $this->reads[0]);
	}

	/**
	 * A code not valid yet is refused.
	 *
	 * @return void
	 */
	public function testACodeNotValidYetIsRefused(): void {
		$this->register['accesscode'] = [['id' => 'c1', 'event' => 'ev1', 'code' => 'X', 'validFrom' => self::moment(modify: '+1 day')]];

		$this->expectException(exception: TicketChoiceRefusedException::class);
		$this->expectExceptionMessage(message: 'This code is not valid yet.');
		$this->catalog->matchCode(eventId: 'ev1', code: 'X', registration: [], keptCode: '');
	}

	/**
	 * An expired code is refused.
	 *
	 * @return void
	 */
	public function testAnExpiredCodeIsRefused(): void {
		$this->register['accesscode'] = [['id' => 'c1', 'event' => 'ev1', 'code' => 'X', 'validUntil' => self::moment(modify: '-1 day')]];

		$this->expectException(exception: TicketChoiceRefusedException::class);
		$this->expectExceptionMessage(message: 'This code has expired.');
		$this->catalog->matchCode(eventId: 'ev1', code: 'X', registration: [], keptCode: '');
	}

	/**
	 * The code the registration already keeps is not checked again.
	 *
	 * @return void
	 */
	public function testTheCodeTheRegistrationAlreadyKeepsIsNotCheckedAgain(): void {
		$row = ['id' => 'c1', 'event' => 'ev1', 'code' => 'X', 'validUntil' => self::moment(modify: '-1 day')];
		$this->register['accesscode'] = [$row];

		$this->assertSame(expected: $row, actual: $this->catalog->matchCode(eventId: 'ev1', code: 'X', registration: ['id' => 'r1'], keptCode: 'c1'));
	}

	/**
	 * A code without a maximum never counts uses.
	 *
	 * @return void
	 */
	public function testACodeWithoutAMaximumNeverCountsUses(): void {
		$row = [
			'id' => 'c1',
			'event' => 'ev1',
			'code' => 'X',
			'validFrom' => self::moment(modify: '-1 day'),
			'validUntil' => self::moment(modify: '+1 day'),
		];
		$this->register['accesscode'] = [$row];

		$this->assertSame(expected: $row, actual: $this->catalog->matchCode(eventId: 'ev1', code: 'X', registration: [], keptCode: ''));
		$this->assertCount(expectedCount: 1, haystack: $this->reads);
	}

	/**
	 * A used up code is refused.
	 *
	 * @return void
	 */
	public function testAUsedUpCodeIsRefused(): void {
		$this->register['accesscode'] = [['id' => 'c1', 'event' => 'ev1', 'code' => 'X', 'maxUses' => 2]];
		$this->register['registration'] = [
			['id' => 'r1', 'event' => 'ev1', 'accessCode' => 'c1', 'status' => 'accepted'],
			['id' => 'r2', 'event' => 'ev1', 'accessCode' => 'c1', 'status' => 'pending'],
		];

		try {
			$this->catalog->matchCode(eventId: 'ev1', code: 'X', registration: ['id' => 'r9'], keptCode: '');
			$this->fail(message: 'A used-up code was accepted.');
		} catch (TicketChoiceRefusedException $e) {
			$this->assertSame(expected: 'This code has been used up.', actual: $e->getMessage());
		}

		$this->assertSame(expected: ['registration', ['event' => 'ev1', 'accessCode' => 'c1'], 2000], actual: $this->reads[1]);
	}

	/**
	 * Cancelled uses and the own registration do not count.
	 *
	 * @return void
	 */
	public function testCancelledUsesAndTheOwnRegistrationDoNotCount(): void {
		$row = ['id' => 'c1', 'event' => 'ev1', 'code' => 'X', 'maxUses' => '2'];
		$this->register['accesscode'] = [$row];
		$this->register['registration'] = [
			['id' => 'r1', 'event' => 'ev1', 'accessCode' => 'c1', 'status' => 'accepted'],
			['id' => 'r2', 'event' => 'ev1', 'accessCode' => 'c1', 'status' => 'cancelled'],
			['id' => 'r9', 'event' => 'ev1', 'accessCode' => 'c1', 'status' => 'pending'],
		];

		$this->assertSame(expected: $row, actual: $this->catalog->matchCode(eventId: 'ev1', code: 'X', registration: ['id' => 'r9'], keptCode: ''));
	}

	/**
	 * An unreadable validity is logged and ignored.
	 *
	 * @return void
	 */
	public function testAnUnreadableValidityIsLoggedAndIgnored(): void {
		$row = ['id' => 'c1', 'event' => 'ev1', 'code' => 'X', 'validFrom' => 'whenever'];
		$this->register['accesscode'] = [$row];
		$this->logger->expects($this->once())->method('warning')
			->with('Larpinq: a ticket moment could not be read: {value}', ['value' => 'whenever']);

		$this->assertSame(expected: $row, actual: $this->catalog->matchCode(eventId: 'ev1', code: 'X', registration: [], keptCode: ''));
	}

	/**
	 * A visible ticket is offered while on sale.
	 *
	 * @return void
	 */
	public function testAVisibleTicketIsOfferedWhileOnSale(): void {
		$this->assertTrue(condition: $this->catalog->offered(ticket: ['id' => 't1'], codeRow: null));
		$window = ['id' => 't1', 'saleFrom' => self::moment(modify: '-1 day'), 'saleUntil' => self::moment(modify: '+1 day')];
		$this->assertTrue(condition: $this->catalog->offered(ticket: $window, codeRow: null));
		$this->assertFalse(condition: $this->catalog->offered(ticket: ['id' => 't1', 'saleFrom' => self::moment(modify: '+1 day')], codeRow: null));
		$this->assertFalse(condition: $this->catalog->offered(ticket: ['id' => 't1', 'saleUntil' => self::moment(modify: '-1 day')], codeRow: null));
	}

	/**
	 * A hidden ticket is offered only with a code that unlocks it.
	 *
	 * @return void
	 */
	public function testAHiddenTicketIsOfferedOnlyWithACodeThatUnlocksIt(): void {
		$ticket = ['id' => 't1', 'hidden' => true];

		$this->assertFalse(condition: $this->catalog->offered(ticket: $ticket, codeRow: null));
		$this->assertFalse(condition: $this->catalog->offered(ticket: $ticket, codeRow: ['unlocks' => ['t2']]));
		$this->assertTrue(condition: $this->catalog->offered(ticket: $ticket, codeRow: ['unlocks' => ['t2', 't1']]));
		$closed = $ticket + ['saleUntil' => self::moment(modify: '-1 day')];
		$this->assertFalse(condition: $this->catalog->offered(ticket: $closed, codeRow: ['unlocks' => ['t1']]));
	}

	/**
	 * Only the boolean true hides a ticket.
	 *
	 * @return void
	 */
	public function testOnlyTheBooleanTrueHidesATicket(): void {
		$this->assertTrue(condition: $this->catalog->offered(ticket: ['id' => 't1', 'hidden' => 'true'], codeRow: null));
	}

	/**
	 * Unlocks compares string ids.
	 *
	 * @return void
	 */
	public function testUnlocksComparesStringIds(): void {
		$this->assertFalse(condition: $this->catalog->unlocks(codeRow: null, ticketId: 't1'));
		$this->assertTrue(condition: $this->catalog->unlocks(codeRow: ['unlocks' => [5]], ticketId: '5'));
		$this->assertFalse(condition: $this->catalog->unlocks(codeRow: ['unlocks' => 'not a list'], ticketId: 'not a list'));
		$this->assertFalse(condition: $this->catalog->unlocks(codeRow: [], ticketId: 't1'));
	}

	/**
	 * Ids keeps non empty scalars as strings.
	 *
	 * @return void
	 */
	public function testIdsKeepsNonEmptyScalarsAsStrings(): void {
		$this->assertSame(expected: [], actual: $this->catalog->ids(value: null));
		$this->assertSame(expected: [], actual: $this->catalog->ids(value: 'a'));
		$this->assertSame(expected: ['a', '3', '1'], actual: $this->catalog->ids(value: ['k' => 'a', '', 3, ['x'], null, true]));
	}

	/**
	 * Find answers the row with that id or null.
	 *
	 * @return void
	 */
	public function testFindAnswersTheRowWithThatIdOrNull(): void {
		$this->register['tickettype'] = [
			['id' => 't1', 'event' => 'ev1', 'name' => 'Player'],
			['id' => 't2', 'event' => 'ev1', 'name' => 'Crew'],
		];

		$this->assertSame(expected: 'Crew', actual: $this->catalog->find(objectType: 'tickettype', eventId: 'ev1', id: 't2')['name'] ?? null);
		$this->assertNull(actual: $this->catalog->find(objectType: 'tickettype', eventId: 'ev1', id: 't3'));
		$this->assertNull(actual: $this->catalog->find(objectType: 'tickettype', eventId: 'ev2', id: 't1'));
	}

	/**
	 * Rows of no event read nothing.
	 *
	 * @return void
	 */
	public function testRowsOfNoEventReadNothing(): void {
		$this->assertSame(expected: [], actual: $this->catalog->rows(objectType: 'tickettype', eventId: ''));
		$this->assertSame(expected: [], actual: $this->reads);
	}

	/**
	 * Summary casts the fields and defaults the currency.
	 *
	 * @return void
	 */
	public function testSummaryCastsTheFieldsAndDefaultsTheCurrency(): void {
		$row = ['id' => 5, 'name' => 'Player', 'amount' => '4500', 'order' => '2', 'role' => 'player'];
		$summary = $this->catalog->summary(row: $row, fields: ['role', 'category']);

		$this->assertSame(
			expected: ['id' => '5', 'name' => 'Player', 'amount' => 4500, 'currency' => 'EUR', 'order' => 2, 'role' => 'player', 'category' => ''],
			actual: $summary
		);
		$this->assertSame(expected: 'GBP', actual: $this->catalog->summary(row: ['currency' => 'GBP'], fields: [])['currency']);
	}

	/**
	 * Counts tally accepted registrations per ticket and option.
	 *
	 * @return void
	 */
	public function testCountsTallyAcceptedRegistrationsPerTicketAndOption(): void {
		$this->register['registration'] = [
			['id' => 'r1', 'event' => 'ev1', 'status' => 'accepted', 'ticketType' => 't1', 'options' => ['o1', 'o2']],
			['id' => 'r2', 'event' => 'ev1', 'status' => 'accepted', 'ticketType' => 't1', 'options' => ['o1']],
			['id' => 'r3', 'event' => 'ev1', 'status' => 'accepted', 'ticketType' => 't2'],
			['id' => 'r4', 'event' => 'ev1', 'status' => 'pending', 'ticketType' => 't2', 'options' => ['o2']],
		];
		$this->register['tickettype'] = [
			['id' => 't1', 'event' => 'ev1', 'name' => 'Player', 'role' => 'player'],
			['id' => 't2', 'event' => 'ev1', 'name' => 'Crew', 'role' => 'crew'],
			['id' => 't3', 'event' => 'ev1', 'name' => 'Day', 'role' => 'player'],
		];
		$this->register['registrationoption'] = [
			['id' => 'o1', 'event' => 'ev1', 'name' => 'Meals', 'category' => 'food'],
			['id' => 'o2', 'event' => 'ev1', 'name' => 'Tent', 'category' => 'sleep'],
		];

		$this->assertSame(
			expected: [
				'ticketTypes' => [
					['id' => 't1', 'name' => 'Player', 'role' => 'player', 'count' => 2],
					['id' => 't2', 'name' => 'Crew', 'role' => 'crew', 'count' => 1],
					['id' => 't3', 'name' => 'Day', 'role' => 'player', 'count' => 0],
				],
				'options' => [
					['id' => 'o1', 'name' => 'Meals', 'category' => 'food', 'count' => 2],
					['id' => 'o2', 'name' => 'Tent', 'category' => 'sleep', 'count' => 1],
				],
			],
			actual: $this->catalog->counts(eventId: 'ev1')
		);
		$this->assertSame(expected: ['registration', ['event' => 'ev1', 'status' => 'accepted'], 2000], actual: $this->reads[0]);
	}

	/**
	 * Counts of no event are empty.
	 *
	 * @return void
	 */
	public function testCountsOfNoEventAreEmpty(): void {
		$this->assertSame(expected: ['ticketTypes' => [], 'options' => []], actual: $this->catalog->counts(eventId: ''));
		$this->assertSame(expected: [], actual: $this->reads);
	}
}
