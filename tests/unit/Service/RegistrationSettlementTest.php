<?php

/**
 * Unit tests for RegistrationSettlement.
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
use OCA\Larpinq\Event\RegistrationCreditRequested;
use OCA\Larpinq\Event\RegistrationRefundRequested;
use OCA\Larpinq\Event\RegistrationSettlementRequested;
use OCA\Larpinq\Service\CancellationPolicy;
use OCA\Larpinq\Service\PaymentLeaf;
use OCA\Larpinq\Service\PaymentRequestBuilder;
use OCA\Larpinq\Service\PaymentSettlementCommand;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationSettlement;
use OCA\Shillinq\Event\PaymentCreditRequestedEvent;
use OCA\Shillinq\Event\PaymentRefundRequestedEvent;
use OCA\Shillinq\Event\PaymentSettlementCommandEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * A paid registration that is cancelled asks for a refund or credit once, under the event's policy.
 *
 * @covers \OCA\Larpinq\Service\RegistrationSettlement
 * @uses   \OCA\Larpinq\Service\CancellationPolicy
 * @uses   \OCA\Larpinq\Event\RegistrationSettlementRequested
 * @uses   \OCA\Larpinq\Event\RegistrationRefundRequested
 * @uses   \OCA\Larpinq\Event\RegistrationCreditRequested
 * @uses   \OCA\Larpinq\Service\PaymentSettlementCommand
 */
class RegistrationSettlementTest extends TestCase {

	/** @var RegisterObjectFetcher&MockObject */
	private RegisterObjectFetcher $fetcher;

	/** @var PaymentRequestBuilder&MockObject */
	private PaymentRequestBuilder $builder;

	/** @var PaymentLeaf&MockObject */
	private PaymentLeaf $leaf;

	/** @var IEventDispatcher&MockObject */
	private IEventDispatcher $dispatcher;

	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	/**
	 * The settlement.
	 *
	 * @var RegistrationSettlement
	 */
	private RegistrationSettlement $settlement;

	/**
	 * Set up the subject and its collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$this->builder = $this->createMock(originalClassName: PaymentRequestBuilder::class);
		$this->leaf = $this->createMock(originalClassName: PaymentLeaf::class);
		$this->dispatcher = $this->createMock(originalClassName: IEventDispatcher::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

		$this->builder->method('now')->willReturn(new DateTimeImmutable(datetime: '2026-03-04T05:06:07+00:00'));
		$this->builder->method('debtor')->willReturn(['name' => 'Alice']);
		$this->builder->method('currency')->willReturn('EUR');
		$this->builder->method('cents')->willReturn(4550);
		$this->leaf->method('subject')->willReturnCallback(static fn (string $registrationId): array => ['ref' => 'registration:' . $registrationId]);

		$this->settlement = new RegistrationSettlement(
			fetcher: $this->fetcher,
			policy: new CancellationPolicy(groups: $this->createMock(originalClassName: IGroupManager::class)),
			builder: $this->builder,
			leaf: $this->leaf,
			logger: $this->logger,
			command: new PaymentSettlementCommand(dispatcher: $this->dispatcher, logger: $this->logger, commands: [])
		);
	}

	/**
	 * The settlement with shillinq installed, answering every command with this state.
	 *
	 * @param string $state What shillinq answers.
	 * @param array<int, object> $sent Collects every dispatched event.
	 *
	 * @return RegistrationSettlement
	 */
	private function withShillinq(string $state, array &$sent): RegistrationSettlement {
		$this->dispatcher->method('dispatchTyped')->willReturnCallback(static function (object $event) use ($state, &$sent): void {
			$sent[] = $event;
			if ($event instanceof PaymentSettlementCommandEvent) {
				$event->accept(state: $state);
			}
		});

		return new RegistrationSettlement(
			fetcher: $this->fetcher,
			policy: new CancellationPolicy(groups: $this->createMock(originalClassName: IGroupManager::class)),
			builder: $this->builder,
			leaf: $this->leaf,
			logger: $this->logger,
			command: new PaymentSettlementCommand(dispatcher: $this->dispatcher, logger: $this->logger)
		);
	}

	/**
	 * A cancelled, paid, unsettled registration.
	 *
	 * @param array<string, mixed> $fields Overrides.
	 *
	 * @return array<string, mixed>
	 */
	private static function cancelledPaid(array $fields = []): array {
		return $fields + [
			'id' => 'r1',
			'event' => 'ev1',
			'player' => 'p1',
			'status' => 'cancelled',
			'paymentState' => 'paid',
			'paymentRequestId' => 'pr1',
		];
	}

	/**
	 * The fetcher answers this event.
	 *
	 * @param string $paidCancellation The policy.
	 *
	 * @return void
	 */
	private function eventWithPolicy(string $paidCancellation): void {
		$this->fetcher->method('getObject')->with('event', 'ev1')
			->willReturn(['id' => 'ev1', 'cancellationPolicy' => ['paidCancellation' => $paidCancellation]]);
	}

	/**
	 * Nothing is read, sent or saved.
	 *
	 * @return void
	 */
	private function expectNothing(): void {
		$this->fetcher->expects($this->never())->method('getObject');
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
	}

	/**
	 * A registration that is not cancelled is left alone.
	 *
	 * @return void
	 */
	public function testARegistrationThatIsNotCancelledIsLeftAlone(): void {
		$this->expectNothing();

		$this->settlement->afterWrite(new: self::cancelledPaid(fields: ['status' => 'accepted']), old: null);
	}

	/**
	 * A registration that was already cancelled is left alone.
	 *
	 * @return void
	 */
	public function testARegistrationThatWasAlreadyCancelledIsLeftAlone(): void {
		$this->expectNothing();

		$this->settlement->afterWrite(new: self::cancelledPaid(), old: ['status' => 'cancelled']);
	}

	/**
	 * An unpaid cancellation is left alone.
	 *
	 * @return void
	 */
	public function testAnUnpaidCancellationIsLeftAlone(): void {
		$this->expectNothing();

		$this->settlement->afterWrite(new: self::cancelledPaid(fields: ['paymentState' => 'open']), old: ['status' => 'accepted']);
	}

	/**
	 * An already settled cancellation is left alone.
	 *
	 * @return void
	 */
	public function testAnAlreadySettledCancellationIsLeftAlone(): void {
		$this->expectNothing();

		$this->settlement->afterWrite(new: self::cancelledPaid(fields: ['settlement' => 'refund-requested']), old: ['status' => 'accepted']);
	}

	/**
	 * An unreadable event is logged and nothing is saved.
	 *
	 * @return void
	 */
	public function testAnUnreadableEventIsLoggedAndNothingIsSaved(): void {
		$failure = new RuntimeException(message: 'gone');
		$this->fetcher->method('getObject')->willThrowException($failure);
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->logger->expects($this->once())->method('error')->with(
			'Larpinq: the cancelled, paid registration {id} was not settled: its event could not be read.',
			['id' => 'r1', 'exception' => $failure]
		);

		$this->settlement->afterWrite(new: self::cancelledPaid(), old: ['status' => 'accepted']);
	}

	/**
	 * A policy of none saves none and sends nothing.
	 *
	 * @return void
	 */
	public function testAPolicyOfNoneSavesNoneAndSendsNothing(): void {
		$this->eventWithPolicy(paidCancellation: 'none');
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')
			->with('registration', ['settlement' => 'none'], 'r1');

		$this->settlement->afterWrite(new: self::cancelledPaid(), old: ['status' => 'accepted']);
	}

	/**
	 * A refund policy asks for a refund of the requested amount.
	 *
	 * @return void
	 */
	public function testARefundPolicyAsksForARefundOfTheRequestedAmount(): void {
		$this->eventWithPolicy(paidCancellation: 'refund');
		$this->leaf->method('requests')->with('r1')->willReturn([
			['id' => 'pr0', 'amount' => '10'],
			['id' => 'pr1', 'amount' => '37.505'],
		]);
		$sent = null;
		$this->dispatcher->expects($this->once())->method('dispatchTyped')
			->willReturnCallback(static function (object $event) use (&$sent): void {
				$sent = $event;
			});
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')->with(
			'registration',
			['settlement' => 'refund-requested', 'settlementRequestedAt' => '2026-03-04T05:06:07+00:00'],
			'r1'
		);

		$this->settlement->afterWrite(new: self::cancelledPaid(), old: ['status' => 'accepted']);

		$this->assertInstanceOf(expected: RegistrationRefundRequested::class, actual: $sent);
		$this->assertSame(
			expected: [
				'kind' => 'refund',
				'registrationId' => 'r1',
				'paymentRequestId' => 'pr1',
				'eventId' => 'ev1',
				'playerId' => 'p1',
				'debtor' => ['name' => 'Alice'],
				'amount' => 37.51,
				'currency' => 'EUR',
				'subject' => ['ref' => 'registration:r1'],
			],
			actual: $sent->toArray()
		);
	}

	/**
	 * Without the matching request the amount is the registrations price.
	 *
	 * @return void
	 */
	public function testWithoutTheMatchingRequestTheAmountIsTheRegistrationsPrice(): void {
		$this->eventWithPolicy(paidCancellation: 'credit');
		$this->leaf->method('requests')->willReturn([['id' => 'pr1', 'amount' => 'unknown'], ['id' => 'other', 'amount' => 99]]);
		$sent = null;
		$this->dispatcher->method('dispatchTyped')->willReturnCallback(static function (object $event) use (&$sent): void {
			$sent = $event;
		});

		$this->settlement->afterWrite(new: self::cancelledPaid(), old: null);

		$this->assertInstanceOf(expected: RegistrationCreditRequested::class, actual: $sent);
		$this->assertInstanceOf(expected: RegistrationSettlementRequested::class, actual: $sent);
		$this->assertSame(expected: 45.5, actual: $sent->getAmount());
		$this->assertSame(expected: 'credit', actual: $sent->getKind());
	}

	/**
	 * Under player chooses the players credit choice is sent.
	 *
	 * @return void
	 */
	public function testUnderPlayerChoosesThePlayersCreditChoiceIsSent(): void {
		$this->eventWithPolicy(paidCancellation: 'player-chooses');
		$this->leaf->method('requests')->willReturn([]);
		$sent = null;
		$this->dispatcher->method('dispatchTyped')->willReturnCallback(static function (object $event) use (&$sent): void {
			$sent = $event;
		});
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')->with(
			'registration',
			['settlement' => 'credit-requested', 'settlementRequestedAt' => '2026-03-04T05:06:07+00:00'],
			'r1'
		);

		$this->settlement->afterWrite(new: self::cancelledPaid(fields: ['settlementChoice' => 'credit']), old: ['status' => 'accepted']);

		$this->assertInstanceOf(expected: RegistrationCreditRequested::class, actual: $sent);
	}

	/**
	 * Under player chooses no choice is a refund.
	 *
	 * @return void
	 */
	public function testUnderPlayerChoosesNoChoiceIsARefund(): void {
		$this->eventWithPolicy(paidCancellation: 'player-chooses');
		$this->leaf->method('requests')->willReturn([]);
		$sent = null;
		$this->dispatcher->method('dispatchTyped')->willReturnCallback(static function (object $event) use (&$sent): void {
			$sent = $event;
		});

		$this->settlement->afterWrite(new: self::cancelledPaid(), old: ['status' => 'accepted']);

		$this->assertInstanceOf(expected: RegistrationRefundRequested::class, actual: $sent);
		$this->assertSame(expected: 'pr1', actual: $sent->getPaymentRequestId());
		$this->assertSame(expected: 'ev1', actual: $sent->getEventId());
		$this->assertSame(expected: 'p1', actual: $sent->getPlayerId());
	}
	/**
	 * Shillinq books a credit at once: the registration shows it credited.
	 *
	 * @return void
	 */
	public function testACreditShillinqBookedShowsCredited(): void {
		$this->eventWithPolicy(paidCancellation: 'credit');
		$this->leaf->method('requests')->willReturn([]);
		$sent = [];
		$settlement = $this->withShillinq(state: 'credited', sent: $sent);
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')->with(
			'registration',
			['settlement' => 'credited', 'settlementRequestedAt' => '2026-03-04T05:06:07+00:00'],
			'r1'
		);

		$settlement->afterWrite(new: self::cancelledPaid(fields: ['cancelReason' => 'illness']), old: ['status' => 'accepted']);

		$this->assertCount(expectedCount: 2, haystack: $sent);
		$this->assertInstanceOf(expected: RegistrationCreditRequested::class, actual: $sent[0]);
		$this->assertInstanceOf(expected: PaymentCreditRequestedEvent::class, actual: $sent[1]);
		$this->assertSame(expected: 'Registration cancelled: illness', actual: $sent[1]->getReason());
		$this->assertSame(expected: 'r1', actual: $sent[1]->getCorrelationId());
	}

	/**
	 * A refund waits for finance: shillinq's refund_requested shows as requested.
	 *
	 * @return void
	 */
	public function testARefundShillinqTookStaysRequested(): void {
		$this->eventWithPolicy(paidCancellation: 'refund');
		$this->leaf->method('requests')->willReturn([]);
		$sent = [];
		$settlement = $this->withShillinq(state: 'refund_requested', sent: $sent);
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')->with(
			'registration',
			['settlement' => 'refund-requested', 'settlementRequestedAt' => '2026-03-04T05:06:07+00:00'],
			'r1'
		);

		$settlement->afterWrite(new: self::cancelledPaid(), old: ['status' => 'accepted']);

		$this->assertInstanceOf(expected: PaymentRefundRequestedEvent::class, actual: $sent[1]);
		$this->assertSame(expected: 'Registration cancelled', actual: $sent[1]->getReason());
	}

	/**
	 * Nothing back asks shillinq for nothing.
	 *
	 * @return void
	 */
	public function testNothingBackAsksShillinqForNothing(): void {
		$this->eventWithPolicy(paidCancellation: 'none');
		$sent = [];
		$settlement = $this->withShillinq(state: 'credited', sent: $sent);

		$settlement->afterWrite(new: self::cancelledPaid(), old: ['status' => 'accepted']);

		$this->assertSame(expected: [], actual: $sent);
	}
}
