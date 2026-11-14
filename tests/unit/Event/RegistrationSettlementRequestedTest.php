<?php

/**
 * Unit tests for the registration settlement commands.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Event
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Event;

use OCA\Larpinq\Event\RegistrationCreditRequested;
use OCA\Larpinq\Event\RegistrationRefundRequested;
use OCA\Larpinq\Event\RegistrationSettlementRequested;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;

/**
 * The refund and credit commands larpinq sends to shillinq, as a listener reads them.
 *
 * @covers \OCA\Larpinq\Event\RegistrationSettlementRequested
 * @covers \OCA\Larpinq\Event\RegistrationRefundRequested
 * @covers \OCA\Larpinq\Event\RegistrationCreditRequested
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationSettlementRequestedTest extends TestCase {

	private const SUBJECT = ['register' => 'larpinq', 'schema' => 'registration', 'id' => 'reg-1'];

	/**
	 * Build a command of the given class.
	 *
	 * @param class-string<RegistrationSettlementRequested> $class The command class
	 *
	 * @return RegistrationSettlementRequested
	 */
	private function command(string $class): RegistrationSettlementRequested {
		return new $class(
			'reg-1',
			'pay-9',
			'event-3',
			'player-5',
			['name' => 'Anna de Vries', 'email' => 'anna@example.org'],
			42.5,
			'EUR',
			self::SUBJECT,
		);

	}//end command()

	/**
	 * A refund carries kind `refund` and the full payload, in the order a listener reads it.
	 *
	 * @return void
	 */
	public function testARefundSerialisesTheWholeCommand(): void {
		$command = $this->command(RegistrationRefundRequested::class);

		self::assertSame('refund', $command->getKind());
		self::assertSame(
			[
				'kind' => 'refund',
				'registrationId' => 'reg-1',
				'paymentRequestId' => 'pay-9',
				'eventId' => 'event-3',
				'playerId' => 'player-5',
				'debtor' => ['name' => 'Anna de Vries', 'email' => 'anna@example.org'],
				'amount' => 42.5,
				'currency' => 'EUR',
				'subject' => self::SUBJECT,
			],
			$command->toArray()
		);

	}//end testARefundSerialisesTheWholeCommand()

	/**
	 * A credit differs from a refund only in its kind.
	 *
	 * @return void
	 */
	public function testACreditDiffersFromARefundOnlyInKind(): void {
		$credit = $this->command(RegistrationCreditRequested::class)->toArray();
		$refund = $this->command(RegistrationRefundRequested::class)->toArray();

		self::assertSame('credit', $credit['kind']);
		unset($credit['kind'], $refund['kind']);
		self::assertSame($refund, $credit);

	}//end testACreditDiffersFromARefundOnlyInKind()

	/**
	 * Both commands are dispatchable events, and the accessors agree with the payload.
	 *
	 * @return void
	 */
	public function testTheAccessorsAgreeWithThePayload(): void {
		foreach ([RegistrationRefundRequested::class, RegistrationCreditRequested::class] as $class) {
			$command = $this->command($class);
			$payload = $command->toArray();

			self::assertInstanceOf(Event::class, $command);
			self::assertFalse($command->isPropagationStopped());
			self::assertSame($payload['registrationId'], $command->getRegistrationId());
			self::assertSame($payload['paymentRequestId'], $command->getPaymentRequestId());
			self::assertSame($payload['eventId'], $command->getEventId());
			self::assertSame($payload['playerId'], $command->getPlayerId());
			self::assertSame($payload['debtor'], $command->getDebtor());
			self::assertSame($payload['amount'], $command->getAmount());
			self::assertSame($payload['currency'], $command->getCurrency());
		}

	}//end testTheAccessorsAgreeWithThePayload()

	/**
	 * A debtor without an email is passed on as given.
	 *
	 * @return void
	 */
	public function testADebtorWithoutEmailIsPassedOnAsGiven(): void {
		$command = new RegistrationRefundRequested('reg-2', 'pay-2', 'event-2', 'player-2', ['name' => 'Bob'], 0.0, 'USD', []);

		self::assertSame(['name' => 'Bob'], $command->toArray()['debtor']);
		self::assertSame(0.0, $command->toArray()['amount']);
		self::assertSame('USD', $command->toArray()['currency']);
		self::assertSame([], $command->toArray()['subject']);

	}//end testADebtorWithoutEmailIsPassedOnAsGiven()
}//end class
