<?php

/**
 * Golden test for the ticket and option choice of a registration.
 *
 * Every case runs TicketChoiceService::check() over the real TicketCatalog and
 * RegistrationPlaces and a fake register of one event (ticket types, options,
 * codes and accepted registrations). The outcome, the event lock calls and
 * every register read are compared per case, as JSON text so key order and
 * scalar types count, with fixtures/ticket-choice.golden.json. Sale windows
 * sit far in the past or the future, so the result does not depend on today.
 * Set RECORD_GOLDEN=1 to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationService;
use OCA\Larpinq\Service\TicketChoiceService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Pins the lines, codes and refusals of the ticket choice.
 *
 * @covers \OCA\Larpinq\Service\TicketChoiceService
 * @uses   \OCA\Larpinq\Service\TicketCatalog
 * @uses   \OCA\Larpinq\Service\RegistrationPlaces
 * @uses   \OCA\Larpinq\Service\TicketChoiceRefusedException
 */
class TicketChoiceGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/ticket-choice.golden.json';

	private const PAST = '2000-01-01T00:00:00+00:00';

	private const FUTURE = '2999-01-01T00:00:00+00:00';

	/**
	 * The register of event e1, by type.
	 *
	 * @return array<string,list<array<string,mixed>>>
	 */
	private function register(): array {
		return [
			'tickettype' => [
				['id' => 't-player', 'event' => 'e1', 'name' => 'Player', 'amount' => 9500, 'currency' => 'EUR', 'saleFrom' => self::PAST],
				['id' => 't-crew', 'event' => 'e1', 'name' => 'Crew', 'amount' => '2500', 'placeLimit' => 1],
				['id' => 't-early', 'event' => 'e1', 'name' => 'Early bird', 'amount' => 7000, 'saleUntil' => self::PAST],
				['id' => 't-later', 'event' => 'e1', 'name' => 'Later', 'amount' => 8000, 'saleFrom' => self::FUTURE],
				['id' => 't-secret', 'event' => 'e1', 'name' => 'Secret', 'amount' => 0, 'hidden' => true],
				['id' => 't-dollar', 'event' => 'e1', 'name' => 'Dollar', 'amount' => 100, 'currency' => 'USD'],
				['id' => 't-bad-date', 'event' => 'e1', 'name' => 'Bad date', 'saleFrom' => 'not a date'],
			],
			'registrationoption' => [
				['id' => 'o-meal', 'event' => 'e1', 'name' => 'Meals', 'amount' => 3000],
				['id' => 'o-bed', 'event' => 'e1', 'name' => 'Bed', 'amount' => 1500, 'placeLimit' => 1],
				['id' => 'o-tent', 'event' => 'e1', 'name' => 'Tent spot', 'placeLimit' => 5],
				['id' => 'o-usd', 'event' => 'e1', 'name' => 'USD extra', 'amount' => 5, 'currency' => 'USD'],
			],
			'accesscode' => [
				['id' => 'code-crew', 'event' => 'e1', 'code' => 'CREW', 'unlocks' => ['t-secret']],
				['id' => 'code-old', 'event' => 'e1', 'code' => 'OLD', 'validUntil' => self::PAST],
				['id' => 'code-soon', 'event' => 'e1', 'code' => 'SOON', 'validFrom' => self::FUTURE],
				['id' => 'code-once', 'event' => 'e1', 'code' => 'ONCE', 'maxUses' => 1, 'unlocks' => ['t-secret']],
			],
			'registration' => [
				['id' => 'r-other', 'event' => 'e1', 'status' => 'accepted', 'ticketType' => 't-crew', 'options' => ['o-bed'], 'accessCode' => 'code-once'],
			],
		];
	}//end register()

	/**
	 * The cases: name => [new registration, stored registration or null, whether the event lock is free].
	 *
	 * @return array<string,array{0:array<string,mixed>,1:array<string,mixed>|null,2:bool}>
	 */
	private function cases(): array {
		$base = ['id' => 'r1', 'event' => 'e1', 'status' => 'pending'];
		$stored = $base + ['ticketType' => 't-early', 'options' => ['o-meal'], 'lines' => [
			['kind' => 'ticket', 'ref' => 't-early', 'name' => 'Early bird', 'amount' => 6500, 'currency' => 'EUR'],
			['kind' => 'option', 'ref' => 'o-meal', 'name' => 'Meals', 'amount' => 2500, 'currency' => 'EUR'],
		], 'accessCode' => 'code-crew'];

		return [
			'nothing chosen' => [$base, null, true],
			'nothing chosen, client lines dropped' => [$base + ['lines' => [['kind' => 'ticket']]], null, true],
			'player ticket' => [$base + ['ticketType' => 't-player'], null, true],
			'player ticket with a client price' => [$base + ['ticketType' => 't-player', 'lines' => [['amount' => 1]]], null, true],
			'ticket and options, unsorted' => [$base + ['ticketType' => 't-player', 'options' => ['o-tent', 'o-meal', '']], null, true],
			'options only' => [$base + ['options' => ['o-meal']], null, true],
			'unknown ticket' => [$base + ['ticketType' => 't-nope'], null, true],
			'ticket of another event' => [['event' => 'e2'] + $base + ['ticketType' => 't-player'], null, true],
			'unknown option' => [$base + ['ticketType' => 't-player', 'options' => ['o-nope']], null, true],
			'sale over' => [$base + ['ticketType' => 't-early'], null, true],
			'sale not started' => [$base + ['ticketType' => 't-later'], null, true],
			'unreadable sale date' => [$base + ['ticketType' => 't-bad-date'], null, true],
			'hidden without a code' => [$base + ['ticketType' => 't-secret'], null, true],
			'hidden with the code' => [$base + ['ticketType' => 't-secret', 'code' => ' crew '], null, true],
			'hidden with a code that does not unlock it' => [$base + ['ticketType' => 't-crew', 'code' => 'CREW'], null, true],
			'unknown code' => [$base + ['ticketType' => 't-player', 'code' => 'NOPE'], null, true],
			'expired code' => [$base + ['ticketType' => 't-player', 'code' => 'OLD'], null, true],
			'code not valid yet' => [$base + ['ticketType' => 't-player', 'code' => 'SOON'], null, true],
			'code used up' => [$base + ['ticketType' => 't-secret', 'code' => 'ONCE'], null, true],
			'code used up, but the one it already uses' => [
				$base + ['ticketType' => 't-secret', 'code' => 'ONCE'],
				$base + ['accessCode' => 'code-once'],
				true,
			],
			'crew full when accepted' => [array_merge($base, ['status' => 'accepted', 'ticketType' => 't-crew']), null, true],
			'crew not counted while pending' => [$base + ['ticketType' => 't-crew'], null, true],
			'crew place is its own' => [array_merge($base, ['id' => 'r-other', 'status' => 'accepted', 'ticketType' => 't-crew']), null, true],
			'bed full' => [$base + ['ticketType' => 't-player', 'options' => ['o-bed']], null, true],
			'two currencies' => [$base + ['ticketType' => 't-player', 'options' => ['o-usd']], null, true],
			'one other currency' => [$base + ['ticketType' => 't-dollar', 'options' => ['o-usd']], null, true],
			'event busy' => [$base + ['ticketType' => 't-player'], null, false],
			'unchanged choices keep their lines and code' => [array_merge($stored, ['lines' => [['amount' => 1]], 'accessCode' => 'x']), $stored, true],
			'unchanged choices, options reordered' => [array_merge($stored, ['options' => ['o-meal', 'o-meal']]), $stored, true],
			'unchanged and already equal' => [$stored, $stored, true],
			'stored ticket kept off sale, new option' => [array_merge($stored, ['options' => ['o-meal', 'o-tent']]), $stored, true],
			'stored option kept, ticket changed' => [array_merge($stored, ['ticketType' => 't-player']), $stored, true],
			'stored line of another kind is not kept' => [
				array_merge($base, ['ticketType' => 'o-meal']),
				$base + ['ticketType' => 't-x', 'lines' => [['kind' => 'option', 'ref' => 'o-meal', 'amount' => 1]]],
				true,
			],
			'stored lines not a list' => [
				array_merge($stored, ['ticketType' => 't-early', 'options' => []]),
				array_merge($stored, ['lines' => ['junk', 5]]),
				true,
			],
			'choices cleared' => [$base, $stored, true],
			'option id that is not a string' => [$base + ['options' => [7]], null, true],
			'stored hidden ticket without a code' => [
				$base + ['ticketType' => 't-secret'],
				$base + ['ticketType' => 't-x', 'lines' => [['kind' => 'ticket', 'ref' => 't-secret', 'amount' => 0]]],
				true,
			],
		];
	}//end cases()

	/**
	 * Run one case.
	 *
	 * @param array<string,mixed> $new The registration as it will be.
	 * @param array<string,mixed>|null $old The stored registration.
	 * @param bool $free Whether the event lock is free.
	 *
	 * @return array<string,mixed> The outcome, the lock calls and the reads.
	 */
	private function outcome(array $new, ?array $old, bool $free): array {
		$trace = [];
		$register = $this->register();
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $filters = [], ?int $limit = null) use ($register, &$trace): array {
				$trace[] = ['read', $objectType, $filters, $limit];
				return array_values(
					array_filter(
						$register[$objectType] ?? [],
						static function (array $row) use ($filters): bool {
							foreach ($filters as $field => $value) {
								if (($row[$field] ?? null) !== $value) {
									return false;
								}
							}

							return true;
						}
					)
				);
			}
		);
		$registrations = $this->createMock(originalClassName: RegistrationService::class);
		$registrations->method('hold')->willReturnCallback(
			function (string $eventId) use ($free, &$trace): bool {
				$trace[] = ['hold', $eventId];
				return $free;
			}
		);
		$registrations->method('release')->willReturnCallback(
			function (string $eventId) use (&$trace): void {
				$trace[] = ['release', $eventId];
			}
		);

		$service = new TicketChoiceService(fetcher: $fetcher, registrations: $registrations, logger: new NullLogger());

		return ['outcome' => $service->check(new: $new, old: $old), 'trace' => $trace];
	}//end outcome()

	/**
	 * Every case gives the recorded outcome.
	 *
	 * @return void
	 */
	public function testChoicesMatchTheGolden(): void {
		$actual = [];
		foreach ($this->cases() as $name => [$new, $old, $free]) {
			$actual[$name] = json_encode(value: $this->outcome(new: $new, old: $old, free: $free), flags: JSON_THROW_ON_ERROR);
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
	}//end testChoicesMatchTheGolden()
}//end class
