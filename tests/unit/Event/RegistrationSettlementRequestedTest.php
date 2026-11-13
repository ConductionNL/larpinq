<?php

/**
 * Registration settlement events carry the payment request to settle, tagged with their kind.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Event
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Event;

use OCA\Larpinq\Event\RegistrationCreditRequested;
use OCA\Larpinq\Event\RegistrationRefundRequested;
use OCA\Larpinq\Event\RegistrationSettlementRequested;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Larpinq\Event\RegistrationSettlementRequested
 * @covers \OCA\Larpinq\Event\RegistrationCreditRequested
 * @covers \OCA\Larpinq\Event\RegistrationRefundRequested
 */
class RegistrationSettlementRequestedTest extends TestCase {

	/**
	 * The constructor arguments shared by both kinds.
	 *
	 * @return array<string,mixed>
	 */
	private static function args(): array {
		return [
			'registrationId' => 'reg-1',
			'paymentRequestId' => 'pay-2',
			'eventId' => 'ev-3',
			'playerId' => 'pl-4',
			'debtor' => ['name' => 'Aria', 'email' => 'aria@example.org'],
			'amount' => 42.5,
			'currency' => 'EUR',
			'subject' => ['type' => 'registration', 'id' => 'reg-1'],
		];
	}

	/**
	 * Each subclass and the kind it reports.
	 *
	 * @return array<string, array{class-string<RegistrationSettlementRequested>, string}>
	 */
	public static function kinds(): array {
		return [
			'credit' => [RegistrationCreditRequested::class, 'credit'],
			'refund' => [RegistrationRefundRequested::class, 'refund'],
		];
	}

	/**
	 * The getters return what the event was built with.
	 *
	 * @param class-string<RegistrationSettlementRequested> $class The event class.
	 * @param string                                         $kind  Its kind.
	 *
	 * @return void
	 *
	 * @dataProvider kinds
	 */
	public function testTheEventCarriesThePaymentRequest(string $class, string $kind): void {
		$event = new $class(...self::args());

		$this->assertInstanceOf(expected: Event::class, actual: $event);
		$this->assertSame(expected: $kind, actual: $event->getKind());
		$this->assertSame(expected: 'reg-1', actual: $event->getRegistrationId());
		$this->assertSame(expected: 'pay-2', actual: $event->getPaymentRequestId());
		$this->assertSame(expected: 'ev-3', actual: $event->getEventId());
		$this->assertSame(expected: 'pl-4', actual: $event->getPlayerId());
		$this->assertSame(expected: ['name' => 'Aria', 'email' => 'aria@example.org'], actual: $event->getDebtor());
		$this->assertSame(expected: 42.5, actual: $event->getAmount());
		$this->assertSame(expected: 'EUR', actual: $event->getCurrency());
	}

	/**
	 * toArray() leads with the kind and keeps every field, in a stable key order.
	 *
	 * @param class-string<RegistrationSettlementRequested> $class The event class.
	 * @param string                                         $kind  Its kind.
	 *
	 * @return void
	 *
	 * @dataProvider kinds
	 */
	public function testToArrayIsTheWirePayload(string $class, string $kind): void {
		$payload = (new $class(...self::args()))->toArray();

		$this->assertSame(expected: ['kind' => $kind] + self::args(), actual: $payload);
		$this->assertSame(
			expected: ['kind', 'registrationId', 'paymentRequestId', 'eventId', 'playerId', 'debtor', 'amount', 'currency', 'subject'],
			actual: array_keys($payload)
		);
	}
}
