<?php

/**
 * Larpinq credit towards the next registration
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
 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Service\RegistrationCreditShare;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use PHPUnit\Framework\TestCase;

/**
 * A credit shillinq booked for a player pays towards their next registration,
 * and the registration shows how much was paid from credit and what is still
 * open (REQ-RCT-005). Larpinq reads it from shillinq's request.
 *
 * @covers \OCA\Larpinq\Service\RegistrationCreditShare
 * @covers \OCA\Larpinq\Service\RegistrationPaymentService
 * @covers \OCA\Larpinq\Service\PaymentFollowUp
 */
class RegistrationCreditShareTest extends TestCase {

	private const REG = 'e0000000-0000-4000-8000-000000000001';

	/** @var PaymentWorld The world. */
	private PaymentWorld $world;

	/**
	 * Anna's pending registration of EUR 120.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->world = new PaymentWorld(test: $this);
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::ANNA, status: 'pending');
	}//end setUp()

	/**
	 * Scenario "Mila's credit pays for the spring event", with Anna's EUR 120:
	 * EUR 85 credit shows as paid from credit and EUR 35 still open.
	 *
	 * @return void
	 */
	public function testCreditPaysPartAndTheRestStaysOpen(): void {
		$this->world->leaf->openCredit = 85.0;

		$this->world->update(id: self::REG, data: ['status' => 'accepted']);

		$row = $this->world->registration(self::REG);
		$this->assertSame(expected: 'open', actual: $row['paymentState']);
		$this->assertSame(expected: 85.0, actual: $row['paidFromCredit']);
		$this->assertSame(expected: 35.0, actual: $row['amountOpen']);
		$this->assertArrayNotHasKey(key: 'paymentMethod', array: $row);
	}//end testCreditPaysPartAndTheRestStaysOpen()

	/**
	 * Credit that covers the whole request leaves nothing to pay: the
	 * registration is paid, from credit.
	 *
	 * @return void
	 */
	public function testCreditThatCoversItWholeMarksPaid(): void {
		$this->world->leaf->openCredit = 200.0;

		$this->world->update(id: self::REG, data: ['status' => 'accepted']);

		$row = $this->world->registration(self::REG);
		$this->assertSame(expected: 'paid', actual: $row['paymentState']);
		$this->assertSame(expected: 'credit', actual: $row['paymentMethod']);
		$this->assertSame(expected: 120.0, actual: $row['paidFromCredit']);
		$this->assertSame(expected: 0.0, actual: $row['amountOpen']);
		$this->assertSame(expected: 80.0, actual: $this->world->leaf->openCredit, message: 'shillinq keeps the rest; larpinq holds no balance');
	}//end testCreditThatCoversItWholeMarksPaid()

	/**
	 * Without credit the registration carries no credit fields.
	 *
	 * @return void
	 */
	public function testNoCreditStoresNoCreditFields(): void {
		$this->world->update(id: self::REG, data: ['status' => 'accepted']);

		$row = $this->world->registration(self::REG);
		$this->assertSame(expected: 'open', actual: $row['paymentState']);
		$this->assertArrayNotHasKey(key: 'paidFromCredit', array: $row);
		$this->assertArrayNotHasKey(key: 'amountOpen', array: $row);
	}//end testNoCreditStoresNoCreditFields()

	/**
	 * When the player pays what credit left open, nothing is open any more and
	 * the part paid from credit stays.
	 *
	 * @return void
	 */
	public function testPayingTheRestClosesWhatIsOpen(): void {
		$this->world->leaf->openCredit = 85.0;
		$this->world->update(id: self::REG, data: ['status' => 'accepted']);
		$request = array_values($this->world->leaf->requests)[0];

		$this->world->followUp()->requestChanged(request: array_merge($request, ['state' => 'captured']));

		$row = $this->world->registration(self::REG);
		$this->assertSame(expected: 'paid', actual: $row['paymentState']);
		$this->assertSame(expected: 85.0, actual: $row['paidFromCredit']);
		$this->assertSame(expected: 0.0, actual: $row['amountOpen']);
	}//end testPayingTheRestClosesWhatIsOpen()

	/**
	 * The share counts only settlements by credit, and what is open is the
	 * amount less every settlement, never below zero.
	 *
	 * @return void
	 */
	public function testTheShareReadsOnlyCreditSettlements(): void {
		$share = new RegistrationCreditShare();
		$request = [
			'amount' => 95.0,
			'settlements' => [
				['method' => 'credit', 'amount' => 50.0],
				['method' => 'credit', 'amount' => '35.00'],
				['method' => 'bank-transfer', 'amount' => 5.0],
				['method' => 'credit'],
				'not a settlement',
			],
		];

		$this->assertSame(expected: ['paidFromCredit' => 85.0, 'amountOpen' => 5.0], actual: $share->fields(request: $request));
		$this->assertFalse(condition: $share->paidInFull(request: $request));
		$this->assertSame(expected: [], actual: $share->fields(request: ['amount' => 95.0, 'settlements' => [['method' => 'bank-transfer', 'amount' => 95.0]]]));
		$this->assertSame(expected: [], actual: $share->fields(request: ['amount' => 95.0, 'settlements' => 'broken']));
		$this->assertSame(expected: [], actual: $share->fields(request: ['amount' => 95.0]));
		$this->assertTrue(condition: $share->paidInFull(request: ['amount' => 40.0, 'settlements' => [['method' => 'credit', 'amount' => 60.0]]]));
	}//end testTheShareReadsOnlyCreditSettlements()
}//end class
