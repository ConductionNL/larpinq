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
		$this->world = new PaymentWorld(test: $this);
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
		$this->assertSame(expected: 'offered', actual: $offered['transferStatus']);
		$this->assertSame(expected: PaymentWorld::PIETER, actual: $offered['transferTo']);
		$this->assertSame(expected: 'pieter', actual: $offered['transferToUid']);
		$this->assertSame(expected: '2026-11-10T10:00:00+00:00', actual: $offered['transferOfferedAt']);
		$this->assertSame(expected: PaymentWorld::SANNE, actual: $offered['player'], message: 'an offer alone changes nothing');

		$this->world->signedIn = 'pieter';
		$this->world->transfers()->accept(registrationId: self::SANNE_REG, actingUid: 'pieter');

		$taken = $this->world->registration(id: self::SANNE_REG);
		$this->assertSame(expected: PaymentWorld::PIETER, actual: $taken['player']);
		$this->assertSame(expected: 'pieter', actual: $taken['playerUid']);
		$this->assertSame(expected: '', actual: $taken['character'], message: 'Pieter picks his own character');
		$this->assertSame(expected: 'accepted', actual: $taken['status']);
		$this->assertSame(expected: 'paid', actual: $taken['paymentState']);
		$this->assertSame(expected: 'f0000000-0000-4000-8000-000000000002', actual: $taken['paymentRequestId']);
		$this->assertSame(expected: 'accepted', actual: $taken['transferStatus']);
		$this->assertSame(expected: PaymentWorld::SANNE, actual: $taken['transferredFrom']);
		$this->assertSame(expected: '2026-11-10T10:00:00+00:00', actual: $taken['transferredAt']);
		$this->assertSame(expected: '', actual: $taken['transferToUid']);
		$this->assertNotContains(
			needle: self::SANNES_CHARACTER,
			haystack: $this->world->store->objects['event'][PaymentWorld::EVENT]['players'],
			message: "Sanne's character leaves the event"
		);
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
				$this->fail(message: "step {$index} must be refused");
			} catch (RegistrationChangeRefusedException $e) {
				$this->assertContains(needle: $e->getStatus(), haystack: [403, 404, 409, 422], message: "step {$index}");
			}
		}

		$this->world->transfers()->offer(registrationId: self::SANNE_REG, actingUid: 'sanne', recipientUid: 'pieter');
		try {
			$this->world->transfers()->accept(registrationId: self::SANNE_REG, actingUid: 'anna');
			$this->fail(message: 'only Pieter accepts');
		} catch (RegistrationChangeRefusedException $e) {
			$this->assertSame(expected: 403, actual: $e->getStatus());
		}

		$this->world->transfers()->withdraw(registrationId: self::SANNE_REG, actingUid: 'sanne');
		$withdrawn = $this->world->registration(id: self::SANNE_REG);
		$this->assertSame(expected: 'withdrawn', actual: $withdrawn['transferStatus']);
		$this->assertSame(expected: '', actual: $withdrawn['transferToUid'], message: 'Pieter no longer reads it');
		$this->assertSame(expected: PaymentWorld::SANNE, actual: $withdrawn['player']);
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
			$this->fail(message: 'a lapsed offer cannot be accepted');
		} catch (RegistrationChangeRefusedException $e) {
			$this->assertSame(expected: 409, actual: $e->getStatus());
		}

		$this->assertSame(expected: 1, actual: $this->world->transfers()->lapse(now: $this->world->now));
		$lapsed = $this->world->registration(id: self::SANNE_REG);
		$this->assertSame(expected: 'lapsed', actual: $lapsed['transferStatus']);
		$this->assertSame(expected: PaymentWorld::SANNE, actual: $lapsed['player']);
		$this->assertSame(expected: '', actual: $lapsed['transferToUid']);
		$this->assertSame(expected: 0, actual: $this->world->transfers()->lapse(now: $this->world->now), message: 'a lapsed offer is not read again');

		$this->world->now = new DateTimeImmutable('2026-11-22T10:00:00+00:00');
		$this->world->transfers()->offer(registrationId: self::SANNE_REG, actingUid: 'sanne', recipientUid: 'pieter');
		$this->assertSame(expected: 0, actual: $this->world->transfers()->lapse(now: new DateTimeImmutable('2026-11-24T10:00:00+00:00')));
		$this->assertSame(
			expected: 1,
			actual: $this->world->transfers()->lapse(now: new DateTimeImmutable('2026-11-25T00:00:00+00:00')),
			message: 'the cancel-by date comes first'
		);
	}//end testAnOfferLapses()
}//end class
