<?php

/**
 * Larpinq payment by bank transfer
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
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#requirement-a-game-master-uploads-a-bank-statement-to-match-transfers-req-rps-007
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use PHPUnit\Framework\TestCase;

/**
 * A request shillinq settled from a bank statement line marks the
 * registration paid by bank transfer (REQ-RPS-007).
 *
 * @covers \OCA\Larpinq\Service\PaymentFollowUp
 */
class PaymentBankTransferTest extends TestCase {

	private const REG = 'e0000000-0000-4000-8000-000000000002';

	/** @var PaymentWorld The world. */
	private PaymentWorld $world;

	/**
	 * A registration whose payment is open on request req-1.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->world = new PaymentWorld(test: $this);
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::ANNA, status: 'accepted', more: ['paymentState' => 'open', 'paymentRequestId' => 'req-1', 'paymentReference' => 'WC26-0002']);
	}//end setUp()

	/**
	 * A bank-settled request marks the registration paid by bank transfer.
	 *
	 * @return void
	 */
	public function testARequestSettledFromABankLineMarksPaidByBankTransfer(): void {
		$this->world->followUp()->requestChanged(request: ['id' => 'req-1', 'state' => 'captured', 'settledVia' => 'bank-transfer']);

		$registration = $this->world->registration(id: self::REG);
		$this->assertSame('paid', $registration['paymentState']);
		$this->assertSame('bank-transfer', $registration['paymentMethod']);
	}//end testARequestSettledFromABankLineMarksPaidByBankTransfer()

	/**
	 * A request captured another way marks paid and names no method.
	 *
	 * @return void
	 */
	public function testACaptureOtherwiseNamesNoMethod(): void {
		$this->world->followUp()->requestChanged(request: ['id' => 'req-1', 'state' => 'captured', 'settledVia' => 'psp']);

		$registration = $this->world->registration(id: self::REG);
		$this->assertSame('paid', $registration['paymentState']);
		$this->assertArrayNotHasKey('paymentMethod', $registration);
	}//end testACaptureOtherwiseNamesNoMethod()

	/**
	 * A bank line that has not settled the request yet changes nothing.
	 *
	 * @return void
	 */
	public function testAnUnsettledRequestChangesNothing(): void {
		$this->world->followUp()->requestChanged(request: ['id' => 'req-1', 'state' => 'pending', 'settledVia' => 'bank-transfer']);

		$registration = $this->world->registration(id: self::REG);
		$this->assertSame('open', $registration['paymentState']);
		$this->assertArrayNotHasKey('paymentMethod', $registration);
	}//end testAnUnsettledRequestChangesNothing()
}//end class
