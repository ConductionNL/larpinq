<?php

/**
 * Larpinq Registration Change Service Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use DateTimeImmutable;
use OCA\Larpinq\Event\RegistrationCreditRequested;
use OCA\Larpinq\Event\RegistrationRefundRequested;
use OCA\Larpinq\Service\RegistrationChangeRefusedException;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use PHPUnit\Framework\TestCase;

/**
 * Cancelling one registration, also one of a group booking, and the money of a
 * paid cancellation (REQ-RCT-001, REQ-RCT-002, REQ-RCT-003), with the real
 * registration and payment listeners in the write path.
 */
class RegistrationChangeServiceTest extends TestCase {

	private const ANNA_REG = 'e0000000-0000-4000-8000-000000000001';

	private const PIETER_REG = 'e0000000-0000-4000-8000-000000000004';

	private const JORIS_REG = 'e0000000-0000-4000-8000-000000000005';

	private const JORIS = 'c0000000-0000-4000-8000-000000000005';

	private PaymentWorld $world;

	protected function setUp(): void {
		$this->world = new PaymentWorld($this);
		$this->world->store->objects['event'][PaymentWorld::EVENT]['capacity'] = 2;
		$this->world->store->objects['event'][PaymentWorld::EVENT]['cancellationPolicy'] = [
			'cancelBy' => '2026-11-25T00:00:00+00:00',
			'paidCancellation' => 'player-chooses',
		];
		$this->world->store->seed('player', ['id' => self::JORIS, 'name' => 'Joris', 'userUid' => 'joris']);
		$this->world->now = new DateTimeImmutable('2026-11-10T10:00:00+00:00');
	}//end setUp()

	/**
	 * Scenario "Anna cancels in time": her registration is cancelled and the
	 * first waitlisted registration gets the place.
	 *
	 * @return void
	 */
	public function testAnnaCancelsInTimeAndTheWaitingListMovesUp(): void {
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'accepted', more: ['paymentState' => 'open']);
		$this->world->seedRegistration(id: 'e0000000-0000-4000-8000-000000000003', player: PaymentWorld::SANNE, status: 'accepted');
		$this->world->seedRegistration(id: self::PIETER_REG, player: PaymentWorld::PIETER, status: 'waitlisted');
		$this->world->signedIn = 'anna';

		$this->world->changes()->cancel(registrationId: self::ANNA_REG, actingUid: 'anna');

		$anna = $this->world->registration(id: self::ANNA_REG);
		$this->assertSame('cancelled', $anna['status']);
		$this->assertSame('player', $anna['cancelReason']);
		$this->assertSame('anna', $anna['cancelledBy']);
		$this->assertArrayNotHasKey('settlement', $anna, 'an unpaid registration has no money to give back');
		$this->assertSame('accepted', $this->world->registration(id: self::PIETER_REG)['status']);
		$this->assertSame([], $this->world->dispatched);
	}//end testAnnaCancelsInTimeAndTheWaitingListMovesUp()

	/**
	 * Scenario "Too late to cancel yourself": refused, with the organisers named.
	 *
	 * @return void
	 */
	public function testTooLateToCancelYourself(): void {
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'accepted');
		$this->world->now = new DateTimeImmutable('2026-11-28T10:00:00+00:00');

		try {
			$this->world->changes()->cancel(registrationId: self::ANNA_REG, actingUid: 'anna');
			$this->fail('a cancellation after the cancel-by date must be refused');
		} catch (RegistrationChangeRefusedException $e) {
			$this->assertSame(409, $e->getStatus());
			$this->assertStringContainsString('contact the organisers', $e->getMessage());
		}

		$this->assertSame('accepted', $this->world->registration(id: self::ANNA_REG)['status']);
	}//end testTooLateToCancelYourself()

	/**
	 * A game master can cancel at any time; the reason says the organisers did.
	 *
	 * @return void
	 */
	public function testAGameMasterCancelsAfterTheDate(): void {
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'accepted');
		$this->world->now = new DateTimeImmutable('2026-11-28T10:00:00+00:00');

		$this->world->changes()->cancel(registrationId: self::ANNA_REG, actingUid: 'gm');

		$anna = $this->world->registration(id: self::ANNA_REG);
		$this->assertSame('cancelled', $anna['status']);
		$this->assertSame('organiser', $anna['cancelReason']);
	}//end testAGameMasterCancelsAfterTheDate()

	/**
	 * Another player cannot cancel Anna's registration, nor cancel one twice.
	 *
	 * @return void
	 */
	public function testOnlyTheParticipantTheBookerOrAGameMasterCancels(): void {
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'accepted');

		try {
			$this->world->changes()->cancel(registrationId: self::ANNA_REG, actingUid: 'sanne');
			$this->fail('another player must not cancel');
		} catch (RegistrationChangeRefusedException $e) {
			$this->assertSame(403, $e->getStatus());
		}

		$this->world->changes()->cancel(registrationId: self::ANNA_REG, actingUid: 'anna');
		try {
			$this->world->changes()->cancel(registrationId: self::ANNA_REG, actingUid: 'anna');
			$this->fail('a cancelled registration cannot be cancelled again');
		} catch (RegistrationChangeRefusedException $e) {
			$this->assertSame(409, $e->getStatus());
		}
	}//end testOnlyTheParticipantTheBookerOrAGameMasterCancels()

	/**
	 * Scenario "Mila drops out": Joris books Mila into his booking, then cancels
	 * her registration; his own stays accepted.
	 *
	 * @return void
	 */
	public function testMilaDropsOutOfJorisBooking(): void {
		$this->world->seedRegistration(id: self::JORIS_REG, player: self::JORIS, status: 'accepted', more: ['submitterUid' => 'joris']);
		$this->world->signedIn = 'joris';

		$mila = $this->world->changes()->addParticipant(registrationId: self::JORIS_REG, actingUid: 'joris', name: 'Mila');

		$joris = $this->world->registration(id: self::JORIS_REG);
		$this->assertNotSame('', (string)($joris['bookingGroup'] ?? ''), 'the booker gets a booking group');
		$this->assertSame($joris['bookingGroup'], $mila['bookingGroup']);
		$this->assertSame('joris', $mila['bookedByUid']);
		$this->assertSame(PaymentWorld::EVENT, $mila['event']);
		$this->assertSame('accepted', $mila['status'], 'a participant is decided like any other sign-up');
		$this->assertSame('Mila', $this->world->store->objects['player'][$mila['player']]['name']);

		$this->world->changes()->cancel(registrationId: (string)$mila['id'], actingUid: 'joris');

		$this->assertSame('cancelled', $this->world->registration(id: (string)$mila['id'])['status']);
		$this->assertSame('accepted', $this->world->registration(id: self::JORIS_REG)['status']);
	}//end testMilaDropsOutOfJorisBooking()

	/**
	 * Only the person who signed up adds participants, and only with a name or
	 * a player they booked before.
	 *
	 * @return void
	 */
	public function testOnlyTheBookerAddsParticipants(): void {
		$this->world->seedRegistration(id: self::JORIS_REG, player: self::JORIS, status: 'accepted', more: ['submitterUid' => 'joris']);

		foreach ([['anna', 'Mila', '', 403], ['joris', ' ', '', 422], ['joris', '', PaymentWorld::ANNA, 403]] as [$uid, $name, $player, $status]) {
			try {
				$this->world->changes()->addParticipant(registrationId: self::JORIS_REG, actingUid: $uid, name: $name, playerId: $player);
				$this->fail("adding {$name}{$player} as {$uid} must be refused");
			} catch (RegistrationChangeRefusedException $e) {
				$this->assertSame($status, $e->getStatus());
			}
		}
	}//end testOnlyTheBookerAddsParticipants()

	/**
	 * Scenario "Mila's fee becomes credit": larpinq asks shillinq for a credit of
	 * the paid amount for Mila's player and records the request.
	 *
	 * @return void
	 */
	public function testMilasPaidFeeBecomesCredit(): void {
		$this->world->seedRegistration(id: self::JORIS_REG, player: self::JORIS, status: 'accepted', more: ['submitterUid' => 'joris']);
		$mila = $this->world->changes()->addParticipant(registrationId: self::JORIS_REG, actingUid: 'joris', name: 'Mila');
		$milaId = (string)$mila['id'];
		$this->world->store->objects['registration'][$milaId] = array_merge(
			$this->world->registration(id: $milaId),
			['paymentState' => 'paid', 'paymentRequestId' => 'f0000000-0000-4000-8000-000000000009', 'lines' => [['kind' => 'ticket', 'amount' => 8500, 'currency' => 'EUR']]]
		);

		$this->world->changes()->cancel(registrationId: $milaId, actingUid: 'joris', choice: 'credit');

		$this->assertCount(1, $this->world->dispatched);
		$event = $this->world->dispatched[0];
		$this->assertInstanceOf(RegistrationCreditRequested::class, $event);
		$this->assertSame($milaId, $event->getRegistrationId());
		$this->assertSame('f0000000-0000-4000-8000-000000000009', $event->getPaymentRequestId());
		$this->assertSame($mila['player'], $event->getPlayerId());
		$this->assertSame(85.0, $event->getAmount());
		$this->assertSame('EUR', $event->getCurrency());
		$this->assertSame('Mila', $event->getDebtor()['name']);

		$stored = $this->world->registration(id: $milaId);
		$this->assertSame('credit-requested', $stored['settlement']);
		$this->assertSame('credit', $stored['settlementChoice']);
		$this->assertSame('2026-11-10T10:00:00+00:00', $stored['settlementRequestedAt']);
	}//end testMilasPaidFeeBecomesCredit()

	/**
	 * The policy decides when the player does not choose: refund, nothing back,
	 * and a game master's cancellation through the object page settles as well.
	 *
	 * @return void
	 */
	public function testThePolicyDecidesTheMoneyBack(): void {
		$paid = ['paymentState' => 'paid', 'paymentRequestId' => 'f0000000-0000-4000-8000-000000000001'];
		$this->world->store->objects['event'][PaymentWorld::EVENT]['cancellationPolicy']['paidCancellation'] = 'refund';
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'accepted', more: $paid);
		$this->world->changes()->cancel(registrationId: self::ANNA_REG, actingUid: 'anna', choice: 'credit');
		$this->assertInstanceOf(RegistrationRefundRequested::class, $this->world->dispatched[0], 'the policy refunds; a choice is only read when the player may choose');
		$this->assertSame(120.0, $this->world->dispatched[0]->getAmount());
		$this->assertSame('refund-requested', $this->world->registration(id: self::ANNA_REG)['settlement']);

		$this->world->store->objects['event'][PaymentWorld::EVENT]['cancellationPolicy']['paidCancellation'] = 'none';
		$this->world->seedRegistration(id: self::PIETER_REG, player: PaymentWorld::PIETER, status: 'accepted', more: $paid);
		$this->world->update(id: self::PIETER_REG, data: ['status' => 'cancelled']);
		$this->assertCount(1, $this->world->dispatched, 'nothing back: no request to shillinq');
		$this->assertSame('none', $this->world->registration(id: self::PIETER_REG)['settlement']);

		$this->world->store->objects['event'][PaymentWorld::EVENT]['cancellationPolicy']['paidCancellation'] = 'player-chooses';
		$this->world->seedRegistration(id: self::JORIS_REG, player: self::JORIS, status: 'accepted', more: $paid);
		$this->world->update(id: self::JORIS_REG, data: ['status' => 'cancelled']);
		$this->assertInstanceOf(RegistrationRefundRequested::class, $this->world->dispatched[1], 'a game master cancelling on the object page without a choice: a refund');
	}//end testThePolicyDecidesTheMoneyBack()

	/**
	 * What the signed-in user may do on a registration, for the page.
	 *
	 * @return void
	 */
	public function testWhatMayChangeFollowsTheCaller(): void {
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'accepted', more: ['paymentState' => 'paid']);

		$anna = $this->world->changes()->whatMayChange(registrationId: self::ANNA_REG, actingUid: 'anna');
		$this->assertTrue($anna['canCancel']);
		$this->assertTrue($anna['choosesMoneyBack']);
		$this->assertTrue($anna['canOfferTransfer']);
		$this->assertFalse($anna['canAcceptTransfer']);
		$this->assertTrue($anna['canAddParticipant']);
		$this->assertSame('2026-11-25T00:00:00+00:00', $anna['cancelBy']);

		$sanne = $this->world->changes()->whatMayChange(registrationId: self::ANNA_REG, actingUid: 'sanne');
		$this->assertFalse($sanne['canCancel']);
		$this->assertFalse($sanne['canOfferTransfer']);
		$this->assertFalse($sanne['canAddParticipant']);

		$this->world->now = new DateTimeImmutable('2026-11-28T10:00:00+00:00');
		$late = $this->world->changes()->whatMayChange(registrationId: self::ANNA_REG, actingUid: 'anna');
		$this->assertFalse($late['canCancel']);
		$this->assertTrue($this->world->changes()->whatMayChange(registrationId: self::ANNA_REG, actingUid: 'gm')['canCancel']);
	}//end testWhatMayChangeFollowsTheCaller()
}//end class
