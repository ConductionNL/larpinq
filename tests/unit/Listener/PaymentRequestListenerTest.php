<?php

/**
 * Larpinq Payment Request Listener Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Tests\Unit\Support\InMemoryObjectEntity;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use PHPUnit\Framework\TestCase;

/**
 * Shillinq's PaymentRequest object events set the registration's payment
 * state (REQ-RPS-002), read from the real OpenRegister ObjectUpdatedEvent
 * through getNewObject().
 */
class PaymentRequestListenerTest extends TestCase {

	private const SANNE_REG = 'e0000000-0000-4000-8000-000000000002';

	private PaymentWorld $world;

	/**
	 * The request shillinq holds for Sanne.
	 *
	 * @var array<string, mixed>
	 */
	private array $request;

	protected function setUp(): void {
		$this->world = new PaymentWorld($this);
		$this->world->seedRegistration(id: self::SANNE_REG, player: PaymentWorld::SANNE, status: 'pending');
		$this->world->update(id: self::SANNE_REG, data: ['status' => 'accepted']);
		$this->request = array_values($this->world->leaf->requests)[0];
	}//end setUp()

	/**
	 * Scenario "A bank transfer is matched": shillinq captures the request, the registration is paid.
	 *
	 * @return void
	 */
	public function testACapturedRequestMarksTheRegistrationPaid(): void {
		$this->shillinqUpdates(state: 'captured');

		$this->assertSame('paid', $this->world->registration(self::SANNE_REG)['paymentState']);
	}//end testACapturedRequestMarksTheRegistrationPaid()

	/**
	 * A capture shillinq could not apply to an invoice is still money received.
	 *
	 * @return void
	 */
	public function testACaptureNotAppliedIsPaidToo(): void {
		$this->shillinqUpdates(state: 'captured_unapplied');

		$this->assertSame('paid', $this->world->registration(self::SANNE_REG)['paymentState']);
	}//end testACaptureNotAppliedIsPaidToo()

	/**
	 * A failed payment leaves the registration open.
	 *
	 * @return void
	 */
	public function testAFailedPaymentStaysOpen(): void {
		$this->shillinqUpdates(state: 'failed');

		$this->assertSame('open', $this->world->registration(self::SANNE_REG)['paymentState']);
	}//end testAFailedPaymentStaysOpen()

	/**
	 * A request on another app's object, or one this registration no longer
	 * points at, changes nothing.
	 *
	 * @return void
	 */
	public function testAnotherObjectsRequestIsIgnored(): void {
		$this->request['subject'] = ['type' => 'case', 'register' => 'dossiq', 'schema' => 'Zaak', 'id' => self::SANNE_REG];
		$this->shillinqUpdates(state: 'captured');
		$this->assertSame('open', $this->world->registration(self::SANNE_REG)['paymentState']);

		$this->request['subject'] = ['type' => 'registration', 'register' => '3', 'schema' => 'registration', 'id' => self::SANNE_REG];
		$this->request['id'] = 'f0000000-0000-4000-8000-000000000099';
		$this->shillinqUpdates(state: 'captured');
		$this->assertSame('open', $this->world->registration(self::SANNE_REG)['paymentState']);
	}//end testAnotherObjectsRequestIsIgnored()

	/**
	 * Dispatch the real OpenRegister ObjectUpdatedEvent for shillinq's request.
	 *
	 * @param string $state The new state.
	 *
	 * @return void
	 */
	private function shillinqUpdates(string $state): void {
		$old = new InMemoryObjectEntity(schema: '41', data: $this->request, uuid: (string)$this->request['id']);
		$new = new InMemoryObjectEntity(schema: '41', data: array_merge($this->request, ['state' => $state]), uuid: (string)$this->request['id']);

		$this->world->paymentListener()->handle(new ObjectUpdatedEvent($new, $old));
	}//end shillinqUpdates()
}//end class
