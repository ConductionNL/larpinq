<?php

/**
 * Larpinq Request Credit Share Test
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
 * @spec openspec/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Service\RequestCreditShare;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use PHPUnit\Framework\TestCase;

/**
 * The part of a payment request shillinq paid from the player's credit
 * (REQ-RCT-005): the share itself, and the registration that shows it when
 * a game master accepts, when shillinq changes the request and in the daily pass.
 */
class RequestCreditShareTest extends TestCase {

	private const REG = 'e0000000-0000-4000-8000-000000000001';

	/** @var PaymentWorld The world. */
	private PaymentWorld $world;

	/**
	 * Build the world every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->world = new PaymentWorld(test: $this);
	}//end setUp()

	/**
	 * Only credit settlements count, capped at the amount, in cents.
	 *
	 * @return void
	 */
	public function testTheShareCountsOnlyCreditUpToTheAmount(): void {
		$share = new RequestCreditShare();
		$request = [
			'amount' => 95,
			'settlements' => [
				['method' => 'credit', 'amount' => 50],
				['method' => 'bank-transfer', 'amount' => 40],
				['method' => 'credit', 'amount' => '35.00'],
				'not a settlement',
			],
		];
		$this->assertSame(expected: ['paidFromCredit' => 8500, 'amountOpen' => 1000], actual: $share->fields(registration: [], request: $request));

		$request['settlements'][] = ['method' => 'credit', 'amount' => 30];
		$this->assertSame(expected: ['paidFromCredit' => 9500, 'amountOpen' => 0], actual: $share->fields(registration: [], request: $request));
	}//end testTheShareCountsOnlyCreditUpToTheAmount()

	/**
	 * No credit, unreadable settlements, or numbers already shown: nothing to write.
	 *
	 * @return void
	 */
	public function testNothingToWriteWithoutNewCredit(): void {
		$share = new RequestCreditShare();
		$this->assertSame(expected: [], actual: $share->fields(registration: [], request: ['amount' => 95]));
		$this->assertSame(expected: [], actual: $share->fields(registration: [], request: ['amount' => 95, 'settlements' => 'x']));
		$unreadable = ['amount' => 95, 'settlements' => [['method' => 'credit', 'amount' => 'abc']]];
		$this->assertSame(expected: [], actual: $share->fields(registration: [], request: $unreadable));

		$request = ['amount' => 95, 'settlements' => [['method' => 'credit', 'amount' => 85]]];
		$shown = ['paidFromCredit' => 8500, 'amountOpen' => 1000];
		$this->assertSame(expected: [], actual: $share->fields(registration: $shown, request: $request));
	}//end testNothingToWriteWithoutNewCredit()

	/**
	 * A request counts as paid by credit only when shillinq stamped it settled by credit.
	 *
	 * @return void
	 */
	public function testCoversAllNeedsTheCreditStamp(): void {
		$share = new RequestCreditShare();
		$this->assertTrue(condition: $share->coversAll(request: ['settledVia' => 'credit', 'settledAt' => '2026-10-15T10:00:00Z']));
		$this->assertFalse(condition: $share->coversAll(request: ['settledVia' => 'credit', 'settledAt' => '']));
		$this->assertFalse(condition: $share->coversAll(request: ['settledVia' => 'bank-transfer', 'settledAt' => '2026-10-15T10:00:00Z']));
		$this->assertFalse(condition: $share->coversAll(request: []));
	}//end testCoversAllNeedsTheCreditStamp()

	/**
	 * Scenario "Mila's credit pays for the spring event": EUR 85 credit on a
	 * line of EUR 95 shows EUR 85 paid from credit and EUR 10 still open.
	 *
	 * @return void
	 */
	public function testCreditPaysPartOfTheNextRegistration(): void {
		$this->world->leaf->openCredit = 85.0;
		$this->seedPending(cents: 9500);

		$this->world->update(id: self::REG, data: ['status' => 'accepted']);

		$row = $this->world->registration(id: self::REG);
		$this->assertSame(expected: 8500, actual: $row['paidFromCredit']);
		$this->assertSame(expected: 1000, actual: $row['amountOpen']);
		$this->assertSame(expected: 'open', actual: $row['paymentState'], message: 'EUR 10 is still to pay');
	}//end testCreditPaysPartOfTheNextRegistration()

	/**
	 * Credit that covers the whole price pays the registration at once.
	 *
	 * @return void
	 */
	public function testCreditCoveringTheWholePricePaysAtOnce(): void {
		$this->world->leaf->openCredit = 150.0;
		$this->seedPending(cents: 9500);

		$this->world->update(id: self::REG, data: ['status' => 'accepted']);

		$row = $this->world->registration(id: self::REG);
		$this->assertSame(expected: 'paid', actual: $row['paymentState']);
		$this->assertSame(expected: 9500, actual: $row['paidFromCredit']);
		$this->assertSame(expected: 0, actual: $row['amountOpen']);
	}//end testCreditCoveringTheWholePricePaysAtOnce()

	/**
	 * Without credit the registration carries no credit fields.
	 *
	 * @return void
	 */
	public function testNoCreditLeavesNoCreditFields(): void {
		$this->seedPending(cents: 9500);

		$this->world->update(id: self::REG, data: ['status' => 'accepted']);

		$row = $this->world->registration(id: self::REG);
		$this->assertArrayNotHasKey(key: 'paidFromCredit', array: $row);
		$this->assertArrayNotHasKey(key: 'amountOpen', array: $row);
		$this->assertSame(expected: 'open', actual: $row['paymentState']);
	}//end testNoCreditLeavesNoCreditFields()

	/**
	 * A request shillinq settled from credit is paid in the daily pass, not
	 * expired as unpaid after its pay-by date.
	 *
	 * @return void
	 */
	public function testTheDailyPassPaysARequestSettledByCredit(): void {
		$request = $this->world->leaf->create(register: '3', schema: 'registration', objectId: self::REG, payload: ['amount' => 95.0]);
		$this->world->leaf->requests[$request['id']]['settlements'] = [['method' => 'credit', 'amount' => 95.0]];
		$this->world->leaf->requests[$request['id']]['settledVia'] = 'credit';
		$this->world->leaf->requests[$request['id']]['settledAt'] = '2026-10-15T10:00:00Z';
		$this->world->seedRegistration(
			id: self::REG,
			player: PaymentWorld::ANNA,
			status: 'accepted',
			more: ['paymentState' => 'open', 'paymentRequestId' => $request['id'], 'payBy' => '2026-10-01T00:00:00+00:00']
		);

		$counts = $this->world->followUp()->daily(now: new \DateTimeImmutable('2026-10-15T10:00:00+00:00'));

		$this->assertSame(expected: 1, actual: $counts['paid']);
		$this->assertSame(expected: 0, actual: $counts['expired']);
		$row = $this->world->registration(id: self::REG);
		$this->assertSame(expected: 'paid', actual: $row['paymentState']);
		$this->assertSame(expected: 9500, actual: $row['paidFromCredit']);
		$this->assertSame(expected: 0, actual: $row['amountOpen']);
	}//end testTheDailyPassPaysARequestSettledByCredit()

	/**
	 * Shillinq reports credit on a request that stays open: the registration
	 * shows the share once, and a request settled by credit marks it paid.
	 *
	 * @return void
	 */
	public function testARequestChangeCarriesTheCreditShare(): void {
		$requestId = 'f0000000-0000-4000-8000-000000000042';
		$this->world->seedRegistration(
			id: self::REG,
			player: PaymentWorld::ANNA,
			status: 'accepted',
			more: ['paymentState' => 'open', 'paymentRequestId' => $requestId]
		);
		$request = ['id' => $requestId, 'state' => 'pending', 'amount' => 95, 'settlements' => [['method' => 'credit', 'amount' => 85]]];

		$this->world->followUp()->requestChanged(request: $request);
		$row = $this->world->registration(id: self::REG);
		$this->assertSame(expected: [8500, 1000, 'open'], actual: [$row['paidFromCredit'], $row['amountOpen'], $row['paymentState']]);

		$this->world->followUp()->requestChanged(request: ['id' => $requestId, 'state' => 'pending', 'amount' => 95]);
		$kept = $this->world->registration(id: self::REG)['paidFromCredit'];
		$this->assertSame(expected: 8500, actual: $kept, message: 'a change without credit leaves the share');

		$request['settlements'][] = ['method' => 'credit', 'amount' => 10];
		$request['settledVia'] = 'credit';
		$request['settledAt'] = '2026-10-16T10:00:00Z';
		$this->world->followUp()->requestChanged(request: $request);
		$row = $this->world->registration(id: self::REG);
		$this->assertSame(expected: [9500, 0, 'paid'], actual: [$row['paidFromCredit'], $row['amountOpen'], $row['paymentState']]);
	}//end testARequestChangeCarriesTheCreditShare()

	/**
	 * A pending registration of Anna with one ticket line.
	 *
	 * @param int $cents The ticket price in cents.
	 *
	 * @return void
	 */
	private function seedPending(int $cents): void {
		$this->world->seedRegistration(
			id: self::REG,
			player: PaymentWorld::ANNA,
			status: 'pending',
			more: ['lines' => [['kind' => 'ticket', 'ref' => PaymentWorld::TICKET, 'name' => 'Player', 'amount' => $cents, 'currency' => 'EUR']]]
		);
	}//end seedPending()
}//end class
