<?php

/**
 * Larpinq Registration Changes Fragment Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
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

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Event\RegistrationCreditRequested;
use OCA\Larpinq\Event\RegistrationRefundRequested;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The cancellation policy, booking group, transfer and money-back fields, read
 * from the merged register exactly as the import sees it, and the event payload
 * checked against shillinq's real PaymentRequest schema.
 */
class RegistrationChangesFragmentTest extends TestCase {

	/**
	 * The larpinq register with every register.d fragment merged in.
	 *
	 * @return array<string, mixed> The merged register.
	 */
	private function mergedRegister(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$loader = $reflection->newInstanceWithoutConstructor();
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		/** @var array<string, mixed> $merged */
		$merged = $merge->invoke($loader, $monolith, $appPath);
		return $merged;
	}//end mergedRegister()

	/**
	 * One schema of the merged register, stripped to what Opis validates.
	 *
	 * @param string $key The schema key.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function validatable(string $key): array {
		$schema = $this->mergedRegister()['components']['schemas'][$key];
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation']);
		}

		return array_intersect_key($schema, array_flip(['type', 'properties']));
	}//end validatable()

	/**
	 * Whether data validates against a schema, as OpenRegister validates it.
	 *
	 * @param array<string, mixed> $data The data.
	 * @param array<string, mixed> $schema The schema.
	 *
	 * @return bool True when valid.
	 */
	private function valid(array $data, array $schema): bool {
		// OpenRegister's ValidateObject::validateObject() drops an empty string on a
		// non-required property before it validates; a cleared field is written as ''.
		$data = array_filter($data, static fn (mixed $value): bool => $value !== '');
		return (new Validator())->validate(json_decode((string)json_encode($data)), json_decode((string)json_encode($schema)))->isValid();
	}//end valid()

	/**
	 * An event has a cancel-by date and what a paid cancellation gets.
	 *
	 * @return void
	 */
	public function testAnEventHasACancellationPolicy(): void {
		$policy = $this->mergedRegister()['components']['schemas']['event']['properties']['cancellationPolicy'];
		$this->assertSame('date-time', $policy['properties']['cancelBy']['format']);
		$this->assertSame(['refund', 'credit', 'player-chooses', 'none'], $policy['properties']['paidCancellation']['enum']);
	}//end testAnEventHasACancellationPolicy()

	/**
	 * Only game masters and larpinq write the new registration fields, and the
	 * booker and the player a registration is offered to can read it.
	 *
	 * @return void
	 */
	public function testTheNewFieldsAreLarpinqsAndTheBookerReads(): void {
		$registration = $this->mergedRegister()['components']['schemas']['registration'];
		$fields = [
			'bookingGroup', 'bookedByUid', 'cancelledBy', 'settlementChoice', 'settlement', 'settlementRequestedAt',
			'transferTo', 'transferToUid', 'transferStatus', 'transferOfferedAt', 'transferredFrom', 'transferredAt',
		];
		foreach ($fields as $field) {
			$this->assertSame(['gamemasters'], $registration['properties'][$field]['authorization']['update'], "{$field} is written by larpinq");
		}

		$read = $registration['authorization']['read'];
		$this->assertContains(['group' => 'larpers', 'match' => ['playerUid' => '$userId']], $read, 'the player still reads their own');
		$this->assertContains(['group' => 'larpers', 'match' => ['bookedByUid' => '$userId']], $read);
		$this->assertContains(['group' => 'larpers', 'match' => ['transferToUid' => '$userId']], $read);
		$this->assertSame(['pending', 'accepted', 'waitlisted', 'declined', 'cancelled'], $registration['properties']['status']['enum']);
		$this->assertSame('transferToUid', $registration['x-openregister-notifications']['transfer-offered']['recipients'][0]['field']);
	}//end testTheNewFieldsAreLarpinqsAndTheBookerReads()

	/**
	 * What larpinq writes on cancel, settle, transfer and add validates.
	 *
	 * @return void
	 */
	public function testTheFieldsLarpinqWritesValidate(): void {
		$schema = $this->validatable(key: 'registration');
		$writes = [
			['status' => 'cancelled', 'cancelReason' => 'player', 'cancelledBy' => 'joris', 'settlementChoice' => 'credit'],
			['settlement' => 'credit-requested', 'settlementRequestedAt' => '2026-11-10T10:00:00+00:00'],
			['transferTo' => 'c0000000-0000-4000-8000-000000000004', 'transferToUid' => 'pieter', 'transferStatus' => 'offered', 'transferOfferedAt' => '2026-11-10T10:00:00+00:00'],
			[
				'player' => 'c0000000-0000-4000-8000-000000000004', 'playerUid' => 'pieter', 'character' => '', 'transferStatus' => 'accepted', 'transferToUid' => '',
				'transferredFrom' => 'c0000000-0000-4000-8000-000000000003', 'transferredAt' => '2026-11-10T10:00:00+00:00', 'bookedByUid' => '',
			],
			['event' => 'a0000000-0000-4000-8000-000000000001', 'player' => 'c0000000-0000-4000-8000-000000000009', 'bookingGroup' => 'd0000000-0000-4000-8000-000000000001', 'bookedByUid' => 'joris', 'submitterUid' => 'joris'],
		];
		foreach ($writes as $index => $written) {
			$this->assertTrue($this->valid(data: $written, schema: $schema), "write {$index} must validate");
		}

		$this->assertFalse($this->valid(data: ['settlement' => 'refund'], schema: $schema), 'a policy value is not a settlement');
		$this->assertTrue($this->valid(data: ['cancellationPolicy' => ['cancelBy' => '2026-11-25T00:00:00+00:00', 'paidCancellation' => 'player-chooses']], schema: $this->validatable(key: 'event')));
		$this->assertFalse($this->valid(data: ['cancellationPolicy' => ['paidCancellation' => 'half']], schema: $this->validatable(key: 'event')));
	}//end testTheFieldsLarpinqWritesValidate()

	/**
	 * The refund and credit events carry the debtor, amount and currency in
	 * shillinq's own PaymentRequest shape, so its listener can raise from them.
	 *
	 * @return void
	 */
	public function testTheEventsSpeakShillinqsPaymentRequestShape(): void {
		$fixture = json_decode((string)file_get_contents(dirname(__DIR__) . '/Service/fixtures/shillinq-payment-request.schema.json'), true);
		$properties = $fixture['schema']['properties'];
		$shape = ['type' => 'object', 'properties' => array_intersect_key($properties, array_flip(['debtor', 'amount', 'currency']))];
		foreach ($shape['properties'] as $name => $property) {
			unset($shape['properties'][$name]['$ref'], $shape['properties'][$name]['example']);
		}

		$args = [
			'registrationId' => 'e0000000-0000-4000-8000-000000000005',
			'paymentRequestId' => 'f0000000-0000-4000-8000-000000000003',
			'eventId' => 'a0000000-0000-4000-8000-000000000001',
			'playerId' => 'c0000000-0000-4000-8000-000000000009',
			'debtor' => ['name' => 'Mila', 'email' => 'joris@example.org'],
			'amount' => 85.0,
			'currency' => 'EUR',
			'subject' => ['register' => '3', 'schema' => '12', 'id' => 'e0000000-0000-4000-8000-000000000005'],
		];
		foreach ([new RegistrationCreditRequested(...$args), new RegistrationRefundRequested(...$args)] as $event) {
			$payload = $event->toArray();
			$this->assertTrue($this->valid(data: array_intersect_key($payload, $shape['properties']), schema: $shape), get_class($event) . ' must speak shillinq\'s shape');
			$this->assertSame('e0000000-0000-4000-8000-000000000005', $payload['registrationId']);
			$this->assertSame(['register' => '3', 'schema' => '12', 'id' => 'e0000000-0000-4000-8000-000000000005'], $payload['subject'], 'the subject of the paid request');
		}

		$this->assertSame('credit', (new RegistrationCreditRequested(...$args))->toArray()['kind']);
		$this->assertSame('refund', (new RegistrationRefundRequested(...$args))->toArray()['kind']);
	}//end testTheEventsSpeakShillinqsPaymentRequestShape()
}//end class
