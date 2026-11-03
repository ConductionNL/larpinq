<?php

/**
 * Tests for ticket types, registration options and access codes in the
 * register (registration-ticket-types-and-options REQ-RTO-001 to REQ-RTO-004).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\SettingsLoadService;
use OCA\Larpinq\Service\SettingsService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Ticket types, options and codes in the register.
 */
class TicketChoicesFragmentTest extends TestCase {

	/**
	 * The register as OpenRegister imports it: monolith plus every fragment.
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

		// @var array<string, mixed> $merged
		$merged = $merge->invoke($loader, $monolith, $appPath);
		return $merged;
	}//end mergedRegister()

	/**
	 * One merged schema.
	 *
	 * @param string $key The schema key.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function schema(string $key): array {
		$schemas = $this->mergedRegister()['components']['schemas'];
		$this->assertArrayHasKey(key: $key, array: $schemas, message: "the {$key} schema must be in the merged register");
		return $schemas[$key];
	}//end schema()

	/**
	 * The three schemas are in the register under their slugs.
	 *
	 * @return void
	 */
	public function testTheThreeSchemasAreInTheRegister(): void {
		$listed = $this->mergedRegister()['components']['registers']['larpinq']['schemas'];
		foreach (['ticketType' => 'larping_ticket_type', 'registrationOption' => 'larping_registration_option', 'accessCode' => 'larping_access_code'] as $key => $slug) {
			$this->assertContains(needle: $slug, haystack: $listed);
			$this->assertSame(expected: $slug, actual: $this->schema(key: $key)['slug']);
			$this->assertSame(expected: 'larping_event', actual: $this->schema(key: $key)['properties']['event']['$ref']);
		}
	}//end testTheThreeSchemasAreInTheRegister()

	/**
	 * A ticket type has a role, a listed price in cents, a sale window, a place limit and a hidden switch.
	 *
	 * @return void
	 */
	public function testATicketTypeHasRolePriceWindowLimitAndHidden(): void {
		$ticket = $this->schema(key: 'ticketType');
		$properties = $ticket['properties'];

		$this->assertSame(expected: ['event', 'name', 'role', 'amount'], actual: $ticket['required']);
		$this->assertSame(expected: ['player', 'crew', 'npc', 'other'], actual: $properties['role']['enum']);
		$this->assertSame(expected: 'integer', actual: $properties['amount']['type']);
		$this->assertSame(expected: 0, actual: $properties['amount']['minimum']);
		$this->assertSame(expected: 'EUR', actual: $properties['currency']['default']);
		$this->assertSame(expected: 'date-time', actual: $properties['saleFrom']['format']);
		$this->assertSame(expected: 'date-time', actual: $properties['saleUntil']['format']);
		$this->assertSame(expected: 'integer', actual: $properties['placeLimit']['type']);
		$this->assertSame(expected: 'boolean', actual: $properties['hidden']['type']);
		$this->assertFalse(condition: $properties['hidden']['default']);

		$option = $this->schema(key: 'registrationOption')['properties'];
		$this->assertSame(expected: ['meal', 'other'], actual: $option['category']['enum']);
		$this->assertSame(expected: 'integer', actual: $option['amount']['type']);
		$this->assertSame(expected: 'integer', actual: $option['placeLimit']['type']);

		$code = $this->schema(key: 'accessCode')['properties'];
		$this->assertSame(expected: 'uuid', actual: $code['unlocks']['items']['format']);
		$this->assertSame(expected: 'date-time', actual: $code['validUntil']['format']);
		$this->assertSame(expected: 1, actual: $code['maxUses']['minimum']);
	}//end testATicketTypeHasRolePriceWindowLimitAndHidden()

	/**
	 * Players read ticket types that are not hidden and every option; codes and hidden ticket types are for game masters.
	 *
	 * @return void
	 */
	public function testPlayersCannotReadHiddenTicketTypesOrCodes(): void {
		$this->assertSame(
			expected: [['group' => 'gamemasters'], ['group' => 'larpers', 'match' => ['hidden' => false]]],
			actual: $this->schema(key: 'ticketType')['authorization']['read']
		);
		$this->assertSame(expected: ['gamemasters', 'larpers'], actual: $this->schema(key: 'registrationOption')['authorization']['read']);
		$this->assertSame(expected: ['gamemasters'], actual: $this->schema(key: 'accessCode')['authorization']['read']);
		foreach (['ticketType', 'registrationOption', 'accessCode'] as $key) {
			foreach (['create', 'update', 'delete'] as $action) {
				$this->assertSame(
					expected: ['gamemasters'],
					actual: $this->schema(key: $key)['authorization'][$action],
					message: "only game masters {$action} a {$key}"
				);
			}
		}
	}//end testPlayersCannotReadHiddenTicketTypesOrCodes()

	/**
	 * The registration holds the choices; the price lines and the matched code are larpinq's to write.
	 *
	 * @return void
	 */
	public function testTheRegistrationHoldsTheChoicesAndLarpinqWritesThePrices(): void {
		$properties = $this->schema(key: 'registration')['properties'];

		$this->assertSame(expected: 'larping_ticket_type', actual: $properties['ticketType']['$ref']);
		$this->assertSame(expected: 'uuid', actual: $properties['options']['items']['format']);
		$this->assertSame(expected: 'string', actual: $properties['code']['type']);
		foreach (['ticketType', 'options', 'code'] as $choice) {
			$this->assertArrayNotHasKey(key: 'authorization', array: $properties[$choice], message: "a player chooses {$choice} on their own registration");
		}

		foreach (['lines', 'accessCode'] as $written) {
			$this->assertSame(expected: ['gamemasters'], actual: $properties[$written]['authorization']['update'], message: "a player cannot set {$written}");
		}

		$line = $properties['lines']['items'];
		$this->assertSame(expected: ['kind', 'ref', 'name', 'amount', 'currency'], actual: $line['required']);
		$this->assertSame(expected: ['ticket', 'option'], actual: $line['properties']['kind']['enum']);
		$this->assertSame(expected: 'integer', actual: $line['properties']['amount']['type']);
		$this->assertSame(expected: ['event'], actual: $this->schema(key: 'registration')['required'], message: 'no choice is required');
	}//end testTheRegistrationHoldsTheChoicesAndLarpinqWritesThePrices()

	/**
	 * The import maps the three schemas and the settings keep their keys.
	 *
	 * @return void
	 */
	public function testTheImportAndTheSettingsKnowTheThreeSchemas(): void {
		$slugs = (new ReflectionClass(SettingsLoadService::class))->getConstant('OBJECT_TYPE_SCHEMA_SLUGS');
		$keys = (new ReflectionClass(SettingsService::class))->getConstant('CONFIG_KEYS');
		foreach (['tickettype' => 'larping_ticket_type', 'registrationoption' => 'larping_registration_option', 'accesscode' => 'larping_access_code'] as $type => $slug) {
			$this->assertSame(expected: $slug, actual: $slugs[$type] ?? null);
			foreach (['_schema', '_register', '_source'] as $suffix) {
				$this->assertContains(needle: $type . $suffix, haystack: $keys);
			}
		}
	}//end testTheImportAndTheSettingsKnowTheThreeSchemas()

	/**
	 * The seed ticket types, options and codes validate against the real schemas, three or more each.
	 * (The registration larpinq writes is validated in TicketChoiceServiceTest.)
	 *
	 * @return void
	 */
	public function testTheSeedsValidate(): void {
		$mock = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/larpinq_mock_register.json'), true);
		$validator = new Validator();
		$bySlug = ['larping_ticket_type' => 'ticketType', 'larping_registration_option' => 'registrationOption', 'larping_access_code' => 'accessCode'];
		$seen = array_fill_keys(array_keys($bySlug), 0);
		foreach ($mock['components']['objects'] as $object) {
			$slug = (string)($object['@self']['schema'] ?? '');
			if (isset($bySlug[$slug]) === false) {
				continue;
			}

			$seen[$slug]++;
			unset($object['@self']);

			$schema = json_decode((string)json_encode($this->forOpis(schema: $this->schema(key: $bySlug[$slug]))));
			$result = $validator->validate(json_decode((string)json_encode($object)), $schema);
			$this->assertTrue(condition: $result->isValid(), message: "seed {$slug} must validate");
		}

		// ADR-111 rule 1 (gate-101): at least three demo objects per schema.
		foreach ($seen as $slug => $count) {
			$this->assertGreaterThanOrEqual(expected: 3, actual: $count, message: "three or more {$slug} seeds");
		}
	}//end testTheSeedsValidate()

	/**
	 * The schema as OpenRegister hands it to Opis.
	 *
	 * @param array<string, mixed> $schema The register schema.
	 *
	 * @return array<string, mixed> The JSON Schema.
	 */
	private function forOpis(array $schema): array {
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation'], $schema['properties'][$name]['x-relation-filter']);
		}

		unset($schema['authorization'], $schema['configuration']);
		return $schema;
	}//end forOpis()
}//end class
