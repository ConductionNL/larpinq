<?php

/**
 * Unit tests for PaymentSettlementCommand.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md#requirement-a-paid-cancellation-is-refunded-or-credited-through-shillinq-req-rct-003
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Event\RegistrationCreditRequested;
use OCA\Larpinq\Event\RegistrationRefundRequested;
use OCA\Larpinq\Event\RegistrationSettlementRequested;
use OCA\Larpinq\Service\PaymentSettlementCommand;
use OCA\Shillinq\Event\PaymentCreditRequestedEvent;
use OCA\Shillinq\Event\PaymentRefundRequestedEvent;
use OCA\Shillinq\Event\PaymentSettlementCommandEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Larpinq hands the money of a cancelled registration to shillinq with
 * shillinq's own typed commands, and fails closed without shillinq.
 *
 * @covers \OCA\Larpinq\Service\PaymentSettlementCommand
 * @uses   \OCA\Larpinq\Event\RegistrationSettlementRequested
 */
class PaymentSettlementCommandTest extends TestCase {

	/** @var IEventDispatcher&MockObject */
	private IEventDispatcher $dispatcher;

	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	/**
	 * Set up the collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->dispatcher = $this->createMock(originalClassName: IEventDispatcher::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
	}

	/**
	 * Larpinq's own command for registration r1.
	 *
	 * @param string $kind `refund` or `credit`.
	 * @param string $paymentRequestId The paid request.
	 *
	 * @return RegistrationSettlementRequested
	 */
	private static function request(string $kind, string $paymentRequestId): RegistrationSettlementRequested {
		$args = [
			'registrationId' => 'r1',
			'paymentRequestId' => $paymentRequestId,
			'eventId' => 'ev1',
			'playerId' => 'p1',
			'debtor' => ['name' => 'Alice'],
			'amount' => 85.0,
			'currency' => 'EUR',
			'subject' => [],
		];
		if ($kind === 'credit') {
			return new RegistrationCreditRequested(...$args);
		}

		return new RegistrationRefundRequested(...$args);
	}

	/**
	 * A refund goes as shillinq's refund command and returns its answered state.
	 *
	 * @return void
	 */
	public function testARefundIsShillinqsRefundCommand(): void {
		$sent = null;
		$this->dispatcher->expects($this->exactly(count: 2))->method('dispatchTyped')
			->willReturnCallback(static function (Event $event) use (&$sent): void {
				if ($event instanceof RegistrationSettlementRequested) {
					return;
				}

				$sent = $event;
				$event->accept(state: 'refund_requested');
			});

		$state = (new PaymentSettlementCommand(dispatcher: $this->dispatcher, logger: $this->logger))
			->send(event: self::request(kind: 'refund', paymentRequestId: 'pr1'), reason: 'Registration cancelled');

		$this->assertSame(expected: 'refund_requested', actual: $state);
		$this->assertInstanceOf(expected: PaymentRefundRequestedEvent::class, actual: $sent);
		$this->assertSame(expected: 'larpinq', actual: $sent->getSourceApp());
		$this->assertSame(expected: 'pr1', actual: $sent->getPaymentRequestId());
		$this->assertSame(expected: 'r1', actual: $sent->getCorrelationId());
		$this->assertSame(expected: 'Registration cancelled', actual: $sent->getReason());
	}

	/**
	 * A credit goes as shillinq's credit command.
	 *
	 * @return void
	 */
	public function testACreditIsShillinqsCreditCommand(): void {
		$sent = null;
		$this->dispatcher->expects($this->exactly(count: 2))->method('dispatchTyped')
			->willReturnCallback(static function (Event $event) use (&$sent): void {
				if ($event instanceof RegistrationSettlementRequested) {
					return;
				}

				$sent = $event;
				$event->accept(state: 'credited');
			});

		$state = (new PaymentSettlementCommand(dispatcher: $this->dispatcher, logger: $this->logger))
			->send(event: self::request(kind: 'credit', paymentRequestId: 'pr1'), reason: '');

		$this->assertSame(expected: 'credited', actual: $state);
		$this->assertInstanceOf(expected: PaymentCreditRequestedEvent::class, actual: $sent);
	}

	/**
	 * A refused command returns null and logs shillinq's error.
	 *
	 * @return void
	 */
	public function testARefusedCommandReturnsNull(): void {
		$this->dispatcher->method('dispatchTyped')->willReturnCallback(static function (Event $event): void {
			if ($event instanceof PaymentSettlementCommandEvent) {
				$event->refuse(error: 'not settled');
			}
		});
		$this->logger->expects($this->once())->method('warning')
			->with($this->anything(), $this->callback(callback: static fn (array $context): bool => $context['error'] === 'not settled'));

		$state = (new PaymentSettlementCommand(dispatcher: $this->dispatcher, logger: $this->logger))
			->send(event: self::request(kind: 'refund', paymentRequestId: 'pr1'), reason: '');

		$this->assertNull(actual: $state);
	}

	/**
	 * Nobody answering the command is the same as a refusal.
	 *
	 * @return void
	 */
	public function testAnUnansweredCommandReturnsNull(): void {
		$this->dispatcher->expects($this->exactly(count: 2))->method('dispatchTyped');

		$state = (new PaymentSettlementCommand(dispatcher: $this->dispatcher, logger: $this->logger))
			->send(event: self::request(kind: 'credit', paymentRequestId: 'pr1'), reason: '');

		$this->assertNull(actual: $state);
	}

	/**
	 * Without shillinq nothing is dispatched and the ask fails closed.
	 *
	 * @return void
	 */
	public function testWithoutShillinqNothingIsSent(): void {
		$this->dispatcher->expects($this->once())->method('dispatchTyped')
			->with($this->isInstanceOf(className: RegistrationRefundRequested::class));
		$this->logger->expects($this->once())->method('warning');

		$command = new PaymentSettlementCommand(
			dispatcher: $this->dispatcher,
			logger: $this->logger,
			commands: ['refund' => 'OCA\\Absent\\RefundCommand', 'credit' => 'OCA\\Absent\\CreditCommand']
		);

		$this->assertNull(actual: $command->send(event: self::request(kind: 'refund', paymentRequestId: 'pr1'), reason: ''));
	}

	/**
	 * A registration without a paid request sends nothing.
	 *
	 * @return void
	 */
	public function testWithoutARequestNothingIsSent(): void {
		$this->dispatcher->expects($this->once())->method('dispatchTyped')
			->with($this->isInstanceOf(className: RegistrationRefundRequested::class));

		$state = (new PaymentSettlementCommand(dispatcher: $this->dispatcher, logger: $this->logger))
			->send(event: self::request(kind: 'refund', paymentRequestId: ''), reason: '');

		$this->assertNull(actual: $state);
	}

	/**
	 * A listener that throws does not break the cancellation.
	 *
	 * @return void
	 */
	public function testAThrowingListenerReturnsNull(): void {
		$this->dispatcher->method('dispatchTyped')->willReturnCallback(static function (Event $event): void {
			if ($event instanceof PaymentSettlementCommandEvent) {
				throw new RuntimeException(message: 'boom');
			}
		});
		$this->logger->expects($this->once())->method('error');

		$state = (new PaymentSettlementCommand(dispatcher: $this->dispatcher, logger: $this->logger))
			->send(event: self::request(kind: 'refund', paymentRequestId: 'pr1'), reason: '');

		$this->assertNull(actual: $state);
	}
}
