<?php

/**
 * Larpinq Transfer Offers Test
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
use OCA\Larpinq\Service\RegistrationChangeRefusedException;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use PHPUnit\Framework\TestCase;

/**
 * Handing a registration to another player (REQ-RCT-004): offered by the
 * participant, taken over only when the other player accepts, lapsing after 7
 * days or at the cancel-by date, with the real listeners in the write path.
 */
class TransferOffersTest extends TestCase {

	private const SANNE_REG = 'e0000000-0000-4000-8000-000000000003';

	private const SANNES_CHARACTER = 'd0000000-0000-4000-8000-000000000003';

	private PaymentWorld $world;

	protected function setUp(): void {
		$this->world = new PaymentWorld($this);
		$this->world->store->objects['event'][PaymentWorld::EVENT]['cancellationPolicy'] = ['cancelBy' => '2026-11-25T00:00:00+00:00'];
		$this->world->store->objects['event'][PaymentWorld::EVENT]['players'] = [self::SANNES_CHARACTER];
		$this->world->seedRegistration(
			id: self::SANNE_REG,
			player: PaymentWorld::SANNE,
			status: 'accepted',
			more: ['paymentState' => 'paid', 'paymentRequestId' => 'f0000000-0000-4000-8000-000000000002', 'character' => self::SANNES_CHARACTER]
		);
		$this->world->now = new DateTimeImmutable('2026-11-10T10:00:00+00:00');
	}//end setUp()

	/**
	 * Scenario "Sanne hands her place to Pieter": the registration is Pieter's,
	 * still accepted and paid, and waits for his character.
	 *
	 * @return void
	 */
	public function testSanneHandsHerPlaceToPieter(): void {
		$this->world->signedIn = 'sanne';
		$this->world->transfers()->offer(registrationId: self::SANNE_REG, actingUid: 'sanne', recipientUid: 'pieter');

		$offered = $this->world->registration(id: self::SANNE_REG);
		$this->assertSame('offered', $offered['transferStatus']);
		$this->assertSame(PaymentWorld::PIETER, $offered['transferTo']);
		$this->assertSame('pieter', $offered['transferToUid']);
		$this->assertSame('2026-11-10T10:00:00+00:00', $offered['transferOfferedAt']);
		$this->assertSame(PaymentWorld::SANNE, $offered['player'], 'an offer alone changes nothing');

		$this->world->signedIn = 'pieter';
		$this->world->transfers()->accept(registrationId: self::SANNE_REG, actingUid: 'pieter');

		$taken = $this->world->registration(id: self::SANNE_REG);
		$this->assertSame(PaymentWorld::PIETER, $taken['player']);
		$this->assertSame('pieter', $taken['playerUid']);
		$this->assertSame('', $taken['character'], 'Pieter picks his own character');
		$this->assertSame('accepted', $taken['status']);
		$this->assertSame('paid', $taken['paymentState']);
		$this->assertSame('f0000000-0000-4000-8000-000000000002', $taken['paymentRequestId']);
		$this->assertSame('accepted', $taken['transferStatus']);
		$this->assertSame(PaymentWorld::SANNE, $taken['transferredFrom']);
		$this->assertSame('2026-11-10T10:00:00+00:00', $taken['transferredAt']);
		$this->assertSame('', $taken['transferToUid']);
		$this->assertNotContains(self::SANNES_CHARACTER, $this->world->store->objects['event'][PaymentWorld::EVENT]['players'], "Sanne's character leaves the event");
	}//end testSanneHandsHerPlaceToPieter()

	/**
	 * Only the participant offers, only the named player accepts, and only an
	 * offered registration can be accepted or withdrawn.
	 *
	 * @return void
	 */
	public function testOnlyTheRightPeopleOfferAndAccept(): void {
		$refusals = [
			fn () => $this->world->transfers()->offer(registrationId: self::SANNE_REG, actingUid: 'anna', recipientUid: 'pieter'),
			fn () => $this->world->transfers()->offer(registrationId: self::SANNE_REG, actingUid: 'sanne', recipientUid: 'nobody'),
			fn () => $this->world->transfers()->offer(registrationId: self::SANNE_REG, actingUid: 'sanne', recipientUid: 'sanne'),
			fn () => $this->world->transfers()->accept(registrationId: self::SANNE_REG, actingUid: 'pieter'),
		];
		foreach ($refusals as $index => $refused) {
			try {
				$refused();
				$this->fail("step {$index} must be refused");
			} catch (RegistrationChangeRefusedException $e) {
				$this->assertContains($e->getStatus(), [403, 404, 409, 422], "step {$index}");
			}
		}

		$this->world->transfers()->offer(registrationId: self::SANNE_REG, actingUid: 'sanne', recipientUid: 'pieter');
		try {
			$this->world->transfers()->accept(registrationId: self::SANNE_REG, actingUid: 'anna');
			$this->fail('only Pieter accepts');
		} catch (RegistrationChangeRefusedException $e) {
			$this->assertSame(403, $e->getStatus());
		}

		$this->world->transfers()->withdraw(registrationId: self::SANNE_REG, actingUid: 'sanne');
		$withdrawn = $this->world->registration(id: self::SANNE_REG);
		$this->assertSame('withdrawn', $withdrawn['transferStatus']);
		$this->assertSame('', $withdrawn['transferToUid'], 'Pieter no longer reads it');
		$this->assertSame(PaymentWorld::SANNE, $withdrawn['player']);
	}//end testOnlyTheRightPeopleOfferAndAccept()

	/**
	 * An offer lapses after 7 days, or at the cancel-by date when that comes
	 * first; the daily job lapses it, and accepting a lapsed offer is refused.
	 *
	 * @return void
	 */
	public function testAnOfferLapses(): void {
		$this->world->transfers()->offer(registrationId: self::SANNE_REG, actingUid: 'sanne', recipientUid: 'pieter');
		$this->world->now = new DateTimeImmutable('2026-11-17T10:00:01+00:00');

		try {
			$this->world->transfers()->accept(registrationId: self::SANNE_REG, actingUid: 'pieter');
			$this->fail('a lapsed offer cannot be accepted');
		} catch (RegistrationChangeRefusedException $e) {
			$this->assertSame(409, $e->getStatus());
		}

		$this->assertSame(1, $this->world->transfers()->lapse(now: $this->world->now));
		$lapsed = $this->world->registration(id: self::SANNE_REG);
		$this->assertSame('lapsed', $lapsed['transferStatus']);
		$this->assertSame(PaymentWorld::SANNE, $lapsed['player']);
		$this->assertSame('', $lapsed['transferToUid']);
		$this->assertSame(0, $this->world->transfers()->lapse(now: $this->world->now), 'a lapsed offer is not read again');

		$this->world->now = new DateTimeImmutable('2026-11-22T10:00:00+00:00');
		$this->world->transfers()->offer(registrationId: self::SANNE_REG, actingUid: 'sanne', recipientUid: 'pieter');
		$this->assertSame(0, $this->world->transfers()->lapse(now: new DateTimeImmutable('2026-11-24T10:00:00+00:00')));
		$this->assertSame(1, $this->world->transfers()->lapse(now: new DateTimeImmutable('2026-11-25T00:00:00+00:00')), 'the cancel-by date comes first');
	}//end testAnOfferLapses()
}//end class
