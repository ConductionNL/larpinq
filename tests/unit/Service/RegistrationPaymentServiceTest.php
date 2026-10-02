<?php

/**
 * Larpinq Registration Payment Service Test
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
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\PaymentRequestRefusedException;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * An accepted, priced registration asks shillinq for its payment through the
 * payment requests leaf (REQ-RPS-001, REQ-RPS-006), with the real
 * registration listener in the write path and the payload checked against
 * shillinq's PaymentRequest schema and larpinq's registration schema.
 */
class RegistrationPaymentServiceTest extends TestCase {

	private const ANNA_REG = 'e0000000-0000-4000-8000-000000000001';

	private PaymentWorld $world;

	protected function setUp(): void {
		$this->world = new PaymentWorld($this);
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'pending');
	}//end setUp()

	/**
	 * Scenario "Anna gets her payment link": a game master accepts, shillinq
	 * holds one pending request of EUR 120, Anna's registration shows the link
	 * and WC26-0001.
	 *
	 * @return void
	 */
	public function testAGameMasterAcceptingRaisesOneRequest(): void {
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);

		$this->assertCount(1, $this->world->leaf->creates);
		$create = $this->world->leaf->creates[0];
		$this->assertSame(['3', 'registration', self::ANNA_REG], [$create['register'], $create['schema'], $create['objectId']]);
		$this->assertSame(120.0, $create['payload']['amount'], 'the sum of the lines, in currency units');
		$this->assertSame('EUR', $create['payload']['currency']);
		$this->assertSame('other', $create['payload']['requestType']);
		$this->assertSame('2026-11-20T00:00:00+00:00', $create['payload']['dueAt']);
		$this->assertSame(['name' => 'Anna', 'email' => 'anna@example.org'], $create['payload']['debtor']);
		$this->assertStringContainsString('WC26-0001', $create['payload']['description']);
		$this->assertFalse($create['payload']['invoiceRequested']);

		$row = $this->world->registration(self::ANNA_REG);
		$request = array_values($this->world->leaf->requests)[0];
		$this->assertSame('open', $row['paymentState']);
		$this->assertSame($request['id'], $row['paymentRequestId']);
		$this->assertSame($request['paymentLink'], $row['paymentLink']);
		$this->assertSame('WC26-0001', $row['paymentReference']);
		$this->assertSame('2026-11-20T00:00:00+00:00', $row['payBy']);

		$this->assertValid(data: $this->shillinqSaves($request), schema: $this->shillinqSchema(), what: 'the request shillinq saves');
		$this->assertValid(data: $this->paymentFieldsOf($row), schema: $this->registrationSchema(), what: 'the registration larpinq stores');
	}//end testAGameMasterAcceptingRaisesOneRequest()

	/**
	 * The second registration of the event gets the next reference.
	 *
	 * @return void
	 */
	public function testTheReferenceCountsUpPerEvent(): void {
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);
		$sanne = 'e0000000-0000-4000-8000-000000000002';
		$this->world->seedRegistration(id: $sanne, player: PaymentWorld::SANNE, status: 'pending', more: ['invoiceRequested' => true]);

		$this->world->update(id: $sanne, data: ['status' => 'accepted']);

		$this->assertSame('WC26-0002', $this->world->registration($sanne)['paymentReference']);
		$this->assertTrue($this->world->leaf->creates[1]['payload']['invoiceRequested'], 'Joris\'s invoice request travels on the payment request');
	}//end testTheReferenceCountsUpPerEvent()

	/**
	 * A registration whose lines add up to nothing needs no payment.
	 *
	 * @return void
	 */
	public function testZeroLinesNeedNoPayment(): void {
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'pending', more: ['lines' => [['kind' => 'ticket', 'ref' => PaymentWorld::TICKET, 'name' => 'NPC', 'amount' => 0, 'currency' => 'EUR']]]);

		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);

		$this->assertSame([], $this->world->leaf->creates);
		$this->assertSame('not-needed', $this->world->registration(self::ANNA_REG)['paymentState']);
	}//end testZeroLinesNeedNoPayment()

	/**
	 * Saving an accepted registration again raises nothing twice.
	 *
	 * @return void
	 */
	public function testNoRequestTwice(): void {
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);
		$this->world->update(id: self::ANNA_REG, data: ['invoiceRequested' => true]);
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'waitlisted']);
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);

		$this->assertCount(1, $this->world->leaf->creates);
	}//end testNoRequestTwice()

	/**
	 * An event that takes no payment gets no payment fields.
	 *
	 * @return void
	 */
	public function testAnEventWithoutPaymentAsksNothing(): void {
		$this->world->store->objects['event'][PaymentWorld::EVENT]['paymentRequired'] = false;

		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);

		$this->assertSame([], $this->world->leaf->creates);
		$this->assertArrayNotHasKey('paymentState', $this->world->registration(self::ANNA_REG));
	}//end testAnEventWithoutPaymentAsksNothing()

	/**
	 * Accepted without a game master (a free place, a move up the waiting
	 * list, the daily job): shillinq would refuse the request, so it waits
	 * for a game master as to-request.
	 *
	 * @return void
	 */
	public function testAcceptedWithoutAGameMasterWaitsAsToRequest(): void {
		$this->world->signedIn = 'anna';

		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);

		$this->assertSame([], $this->world->leaf->creates);
		$row = $this->world->registration(self::ANNA_REG);
		$this->assertSame('to-request', $row['paymentState']);
		$this->assertSame('2026-11-20T00:00:00+00:00', $row['payBy']);
		$this->assertValid(data: $this->paymentFieldsOf($row), schema: $this->registrationSchema(), what: 'a to-request registration');
	}//end testAcceptedWithoutAGameMasterWaitsAsToRequest()

	/**
	 * Shillinq refuses a game master without payment.request: the acceptance
	 * stands and the payment waits as to-request.
	 *
	 * @return void
	 */
	public function testARefusedRequestLeavesToRequest(): void {
		$this->world->leaf->mayRequest = false;

		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);

		$this->assertSame('accepted', $this->world->registration(self::ANNA_REG)['status']);
		$this->assertSame('to-request', $this->world->registration(self::ANNA_REG)['paymentState']);
	}//end testARefusedRequestLeavesToRequest()

	/**
	 * A game master's "Request payment" raises the request of a to-request registration.
	 *
	 * @return void
	 */
	public function testAGameMasterRequestsAWaitingPayment(): void {
		$this->world->signedIn = 'anna';
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);
		$this->world->signedIn = 'gm';

		$row = $this->world->payments()->request(registrationId: self::ANNA_REG, actingUid: 'gm');

		$this->assertCount(1, $this->world->leaf->creates);
		$this->assertSame('open', $row['paymentState']);
		$this->assertSame('WC26-0001', $row['paymentReference']);
		$this->assertSame('open', $this->world->registration(self::ANNA_REG)['paymentState']);
	}//end testAGameMasterRequestsAWaitingPayment()

	/**
	 * Only a game master requests a payment.
	 *
	 * @return void
	 */
	public function testAPlayerCannotRequestAPayment(): void {
		$this->world->signedIn = 'anna';
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);

		try {
			$this->world->payments()->request(registrationId: self::ANNA_REG, actingUid: 'anna');
			$this->fail('a player requested a payment');
		} catch (PaymentRequestRefusedException $e) {
			$this->assertSame(403, $e->getStatus());
		}

		$this->assertSame([], $this->world->leaf->creates);
	}//end testAPlayerCannotRequestAPayment()

	/**
	 * Only a payment that waits is requested; an open one is not raised again.
	 *
	 * @return void
	 */
	public function testAnOpenPaymentIsNotRequestedAgain(): void {
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);

		try {
			$this->world->payments()->request(registrationId: self::ANNA_REG, actingUid: 'gm');
			$this->fail('an open payment was requested again');
		} catch (PaymentRequestRefusedException $e) {
			$this->assertSame(409, $e->getStatus());
		}

		$this->assertCount(1, $this->world->leaf->creates);
	}//end testAnOpenPaymentIsNotRequestedAgain()

	/**
	 * Without shillinq the payment waits and a game master sets it by hand
	 * (REQ-RPS-006); the request action says why it cannot.
	 *
	 * @return void
	 */
	public function testWithoutShillinqTheGameMasterSetsTheStateByHand(): void {
		$this->world->shillinq = false;

		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);
		$this->assertSame('to-request', $this->world->registration(self::ANNA_REG)['paymentState']);

		try {
			$this->world->payments()->request(registrationId: self::ANNA_REG, actingUid: 'gm');
			$this->fail('a request without shillinq');
		} catch (PaymentRequestRefusedException $e) {
			$this->assertSame(409, $e->getStatus());
			$this->assertStringContainsString('shillinq', strtolower($e->getMessage()));
		}

		$this->world->update(id: self::ANNA_REG, data: ['paymentState' => 'paid']);
		$this->assertSame('paid', $this->world->registration(self::ANNA_REG)['paymentState']);
	}//end testWithoutShillinqTheGameMasterSetsTheStateByHand()

	/**
	 * The request shillinq's create() saves from larpinq's payload, as its leaf builds it.
	 *
	 * @param array<string, mixed> $request The fake leaf's saved request.
	 *
	 * @return array<string, mixed> The request without the id the store adds.
	 */
	private function shillinqSaves(array $request): array {
		unset($request['id'], $request['paymentLink'], $request['requestedBy']);
		return $request;
	}//end shillinqSaves()

	/**
	 * Shillinq's PaymentRequest schema (fixture copied from shillinq development).
	 *
	 * @return array<string, mixed> The schema, validation keywords only.
	 */
	private function shillinqSchema(): array {
		$fixture = json_decode((string)file_get_contents(__DIR__ . '/fixtures/shillinq-payment-request.schema.json'), true);
		return $this->validationOnly(schema: $fixture['schema']);
	}//end shillinqSchema()

	/**
	 * Larpinq's merged registration schema.
	 *
	 * @return array<string, mixed> The schema, validation keywords only.
	 */
	private function registrationSchema(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);
		$merged = $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath);

		return $this->validationOnly(schema: $merged['components']['schemas']['registration']);
	}//end registrationSchema()

	/**
	 * The schema without the register's own annotations Opis does not read.
	 *
	 * @param array<string, mixed> $schema The schema.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function validationOnly(array $schema): array {
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation']);
		}

		return array_intersect_key($schema, array_flip(['type', 'required', 'properties']));
	}//end validationOnly()

	/**
	 * The stored row as the register validates it (the test store's own id dropped).
	 *
	 * @param array<string, mixed> $row The row.
	 *
	 * @return array<string, mixed> The row.
	 */
	private function paymentFieldsOf(array $row): array {
		unset($row['id']);
		return $row;
	}//end paymentFieldsOf()

	/**
	 * Assert data validates against a schema with Opis.
	 *
	 * @param array<string, mixed> $data The data.
	 * @param array<string, mixed> $schema The schema.
	 * @param string $what What is validated.
	 *
	 * @return void
	 */
	private function assertValid(array $data, array $schema, string $what): void {
		$result = (new Validator())->validate(json_decode((string)json_encode($data)), json_decode((string)json_encode($schema)));
		$error = $result->error();
		$message = '';
		if ($error !== null) {
			$message = $error->keyword() . ' at ' . implode('/', $error->data()->fullPath()) . ': ' . json_encode($error->args());
		}

		$this->assertTrue($result->isValid(), $what . ' must validate: ' . $message);
	}//end assertValid()
}//end class
