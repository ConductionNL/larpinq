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
		$this->assertArrayHasKey($key, $schemas, "the {$key} schema must be in the merged register");
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
			$this->assertContains($slug, $listed);
			$this->assertSame($slug, $this->schema(key: $key)['slug']);
			$this->assertSame('larping_event', $this->schema(key: $key)['properties']['event']['$ref']);
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

		$this->assertSame(['event', 'name', 'role', 'amount'], $ticket['required']);
		$this->assertSame(['player', 'crew', 'npc', 'other'], $properties['role']['enum']);
		$this->assertSame('integer', $properties['amount']['type']);
		$this->assertSame(0, $properties['amount']['minimum']);
		$this->assertSame('EUR', $properties['currency']['default']);
		$this->assertSame('date-time', $properties['saleFrom']['format']);
		$this->assertSame('date-time', $properties['saleUntil']['format']);
		$this->assertSame('integer', $properties['placeLimit']['type']);
		$this->assertSame('boolean', $properties['hidden']['type']);
		$this->assertFalse($properties['hidden']['default']);

		$option = $this->schema(key: 'registrationOption')['properties'];
		$this->assertSame(['meal', 'other'], $option['category']['enum']);
		$this->assertSame('integer', $option['amount']['type']);
		$this->assertSame('integer', $option['placeLimit']['type']);

		$code = $this->schema(key: 'accessCode')['properties'];
		$this->assertSame('uuid', $code['unlocks']['items']['format']);
		$this->assertSame('date-time', $code['validUntil']['format']);
		$this->assertSame(1, $code['maxUses']['minimum']);
	}//end testATicketTypeHasRolePriceWindowLimitAndHidden()

	/**
	 * Players read ticket types that are not hidden and every option; codes and hidden ticket types are for game masters.
	 *
	 * @return void
	 */
	public function testPlayersCannotReadHiddenTicketTypesOrCodes(): void {
		$this->assertSame(
			[['group' => 'gamemasters'], ['group' => 'larpers', 'match' => ['hidden' => false]]],
			$this->schema(key: 'ticketType')['authorization']['read']
		);
		$this->assertSame(['gamemasters', 'larpers'], $this->schema(key: 'registrationOption')['authorization']['read']);
		$this->assertSame(['gamemasters'], $this->schema(key: 'accessCode')['authorization']['read']);
		foreach (['ticketType', 'registrationOption', 'accessCode'] as $key) {
			foreach (['create', 'update', 'delete'] as $action) {
				$this->assertSame(['gamemasters'], $this->schema(key: $key)['authorization'][$action], "only game masters {$action} a {$key}");
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

		$this->assertSame('larping_ticket_type', $properties['ticketType']['$ref']);
		$this->assertSame('uuid', $properties['options']['items']['format']);
		$this->assertSame('string', $properties['code']['type']);
		foreach (['ticketType', 'options', 'code'] as $choice) {
			$this->assertArrayNotHasKey('authorization', $properties[$choice], "a player chooses {$choice} on their own registration");
		}

		foreach (['lines', 'accessCode'] as $written) {
			$this->assertSame(['gamemasters'], $properties[$written]['authorization']['update'], "a player cannot set {$written}");
		}

		$line = $properties['lines']['items'];
		$this->assertSame(['kind', 'ref', 'name', 'amount', 'currency'], $line['required']);
		$this->assertSame(['ticket', 'option'], $line['properties']['kind']['enum']);
		$this->assertSame('integer', $line['properties']['amount']['type']);
		$this->assertSame(['event'], $this->schema(key: 'registration')['required'], 'no choice is required');
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
			$this->assertSame($slug, $slugs[$type] ?? null);
			foreach (['_schema', '_register', '_source'] as $suffix) {
				$this->assertContains($type . $suffix, $keys);
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
			$this->assertTrue($result->isValid(), "seed {$slug} must validate");
		}

		// ADR-111 rule 1 (gate-101): at least three demo objects per schema.
		foreach ($seen as $slug => $count) {
			$this->assertGreaterThanOrEqual(3, $count, "three or more {$slug} seeds");
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
