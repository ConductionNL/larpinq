<?php

/**
 * Larpinq Registration Payments Fragment Test
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
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The payment fields on the event and the registration, read from the merged
 * register exactly as the import sees it.
 */
class RegistrationPaymentsFragmentTest extends TestCase {

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
	 * One schema of the merged register.
	 *
	 * @param string $key The schema key.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function schema(string $key): array {
		$schemas = $this->mergedRegister()['components']['schemas'];
		$this->assertArrayHasKey($key, $schemas);
		return $schemas[$key];
	}//end schema()

	/**
	 * An event asks for payment by a date, with a code its references start with.
	 *
	 * @return void
	 */
	public function testAnEventAsksForPaymentByADate(): void {
		$event = $this->schema(key: 'event')['properties'];
		$this->assertSame('boolean', $event['paymentRequired']['type']);
		$this->assertFalse($event['paymentRequired']['default']);
		$this->assertSame('date-time', $event['payBy']['format']);
		$this->assertSame('^[A-Z0-9]{2,8}$', $event['paymentCode']['pattern']);
	}//end testAnEventAsksForPaymentByADate()

	/**
	 * The registration carries the payment state and what the player needs to pay;
	 * only game masters and larpinq change it.
	 *
	 * @return void
	 */
	public function testTheRegistrationCarriesThePaymentState(): void {
		$properties = $this->schema(key: 'registration')['properties'];
		$this->assertSame(['not-needed', 'to-request', 'open', 'paid', 'expired'], $properties['paymentState']['enum']);
		$this->assertSame('uuid', $properties['paymentRequestId']['format']);
		foreach (['paymentState', 'paymentRequestId', 'paymentLink', 'paymentReference', 'payBy', 'paymentReminderAt', 'cancelReason'] as $field) {
			$this->assertSame(['gamemasters'], $properties[$field]['authorization']['update'], "{$field} is set by larpinq and game masters only");
		}

		$this->assertSame('boolean', $properties['invoiceRequested']['type']);
		$this->assertArrayNotHasKey('authorization', $properties['invoiceRequested'], 'the player asks for an invoice');
		$this->assertContains('unpaid', $properties['cancelReason']['enum']);
	}//end testTheRegistrationCarriesThePaymentState()

	/**
	 * What larpinq writes after a payment request validates against the real schema.
	 *
	 * @return void
	 */
	public function testThePaymentFieldsLarpinqWritesValidate(): void {
		$schema = $this->schema(key: 'registration');
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation']);
		}

		unset($schema['authorization'], $schema['configuration']);
		$written = [
			'event' => 'e0000000-0000-4000-8000-000000000001',
			'status' => 'accepted',
			'paymentState' => 'open',
			'paymentRequestId' => 'f0000000-0000-4000-8000-000000000001',
			'paymentLink' => 'https://pay.example.org/r/f0000000',
			'paymentReference' => 'WC26-0001',
			'payBy' => '2026-11-20T00:00:00+00:00',
			'invoiceRequested' => false,
		];

		$result = (new Validator())->validate(json_decode((string)json_encode($written)), json_decode((string)json_encode($schema)));
		$this->assertTrue($result->isValid(), 'the payment fields larpinq writes must validate');

		$written['paymentState'] = 'pending';
		$result = (new Validator())->validate(json_decode((string)json_encode($written)), json_decode((string)json_encode($schema)));
		$this->assertFalse($result->isValid(), 'shillinq states are not registration payment states');
	}//end testThePaymentFieldsLarpinqWritesValidate()

	/**
	 * The demo event takes payment, and its open and paid registrations validate.
	 *
	 * @return void
	 */
	public function testThePaymentSeedsValidate(): void {
		$mock = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/larpinq_mock_register.json'), true);
		$bySlug = ['larping_event' => 'event', 'larping_registration' => 'registration'];
		$states = [];
		foreach ($mock['components']['objects'] as $object) {
			$slug = (string)($object['@self']['schema'] ?? '');
			if (isset($bySlug[$slug]) === false) {
				continue;
			}

			unset($object['@self']);
			$schema = $this->schema(key: $bySlug[$slug]);
			foreach (array_keys($schema['properties']) as $name) {
				unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation']);
			}

			$schema = array_intersect_key($schema, array_flip(['type', 'properties']));
			$result = (new Validator())->validate(json_decode((string)json_encode($object)), json_decode((string)json_encode($schema)));
			$this->assertTrue($result->isValid(), "seed {$slug} must validate");
			if (isset($object['paymentState']) === true) {
				$states[] = $object['paymentState'] . ' ' . $object['paymentReference'];
			}
		}

		$this->assertSame(['open WC26-0001', 'paid WC26-0002'], $states);
	}//end testThePaymentSeedsValidate()
}//end class
