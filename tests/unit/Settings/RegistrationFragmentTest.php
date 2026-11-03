<?php

/**
 * Tests for the registration schema and the event's sign-up settings
 * (registration-intake-and-capacity REQ-RIC-001 to REQ-RIC-006).
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
 * The registration in the register.
 */
class RegistrationFragmentTest extends TestCase {

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
	 * The registration is in the register with its fields and statuses.
	 *
	 * @return void
	 */
	public function testTheRegistrationSchemaIsInTheRegister(): void {
		$this->assertContains(needle: 'larping_registration', haystack: $this->mergedRegister()['components']['registers']['larpinq']['schemas']);
		$registration = $this->schema(key: 'registration');

		$this->assertSame(expected: 'larping_registration', actual: $registration['slug']);
		$this->assertSame(expected: ['event'], actual: $registration['required']);
		$this->assertSame(expected: 'larping_event', actual: $registration['properties']['event']['$ref']);
		$this->assertSame(expected: 'player', actual: $registration['properties']['player']['$ref']);
		$this->assertSame(expected: 'character', actual: $registration['properties']['character']['$ref']);
		$this->assertSame(expected: ['pending', 'accepted', 'waitlisted', 'declined', 'cancelled'], actual: $registration['properties']['status']['enum']);
		$this->assertSame(expected: 'pending', actual: $registration['properties']['status']['default']);
		$this->assertSame(expected: 'integer', actual: $registration['properties']['submissionId']['type']);
		foreach (['submittedAt', 'decidedAt'] as $moment) {
			$this->assertSame(expected: 'date-time', actual: $registration['properties'][$moment]['format']);
		}

		// Nextcloud user ids are plain strings: OpenRegister has no `user` format.
		foreach (['submitterUid', 'playerUid', 'decidedBy'] as $uid) {
			$this->assertArrayNotHasKey(key: 'format', array: $registration['properties'][$uid]);
		}
	}//end testTheRegistrationSchemaIsInTheRegister()

	/**
	 * The player account and the world are copied from the player and the event on every save.
	 *
	 * @return void
	 */
	public function testThePlayerAccountAndTheWorldAreMaterialised(): void {
		$registration = $this->schema(key: 'registration');

		$this->assertSame(expected: ['prop' => '@ref.player.userUid'], actual: $registration['properties']['playerUid']['calculation']['expression']);
		$this->assertSame(expected: ['prop' => '@ref.event.setting'], actual: $registration['properties']['eventSetting']['calculation']['expression']);
		$this->assertSame(expected: 'player', actual: $registration['configuration']['x-openregister-references']['player']['schema']);
		$this->assertSame(expected: 'larping_event', actual: $registration['configuration']['x-openregister-references']['event']['schema']);
		$this->assertSame(
			expected: ['ocName' => '@object.player', 'status' => 'active', 'setting' => '@object.eventSetting'],
			actual: $registration['properties']['character']['x-relation-filter']
		);
	}//end testThePlayerAccountAndTheWorldAreMaterialised()

	/**
	 * Game masters see and decide every registration; a player sees their own and changes only the character and their ticket choices.
	 *
	 * @return void
	 */
	public function testThePlayerSeesTheirOwnAndChangesOnlyTheCharacter(): void {
		$registration = $this->schema(key: 'registration');
		$own = ['group' => 'larpers', 'match' => ['playerUid' => '$userId']];

		// Later fragments add their own read rules (booker, transfer recipient); these three stay.
		foreach ([['group' => 'gamemasters'], $own, ['group' => 'larpers', 'match' => ['submitterUid' => '$userId']]] as $rule) {
			$this->assertContains(needle: $rule, haystack: $registration['authorization']['read']);
		}
		$this->assertSame(expected: ['gamemasters'], actual: $registration['authorization']['create']);
		$this->assertSame(expected: [['group' => 'gamemasters'], $own], actual: $registration['authorization']['update']);
		$this->assertSame(expected: ['gamemasters'], actual: $registration['authorization']['delete']);
		foreach (array_keys($registration['properties']) as $name) {
			// The player's own choices (registration-ticket-types-and-options adds the ticket type, options and code;
			// registration-payments-through-shillinq adds the invoice request).
			if (in_array($name, ['character', 'ticketType', 'options', 'code', 'invoiceRequested'], true) === true) {
				$this->assertArrayNotHasKey(key: 'authorization', array: $registration['properties'][$name]);
				continue;
			}

			$this->assertSame(expected: ['gamemasters'], actual: $registration['properties'][$name]['authorization']['update'] ?? null, message: "only game masters change {$name}");
		}
	}//end testThePlayerSeesTheirOwnAndChangesOnlyTheCharacter()

	/**
	 * Game masters accept, decline and cancel on the registration page.
	 *
	 * @return void
	 */
	public function testTheStatusHasItsTransitions(): void {
		$lifecycle = $this->schema(key: 'registration')['configuration']['x-openregister-lifecycle'];

		$this->assertSame(expected: 'status', actual: $lifecycle['field']);
		$this->assertSame(expected: 'pending', actual: $lifecycle['initial']);
		$this->assertSame(expected: 'accepted', actual: $lifecycle['transitions']['accept']['to']);
		$this->assertSame(expected: ['pending', 'waitlisted', 'declined'], actual: $lifecycle['transitions']['accept']['from']);
		$this->assertSame(expected: 'declined', actual: $lifecycle['transitions']['decline']['to']);
		$this->assertSame(expected: 'cancelled', actual: $lifecycle['transitions']['cancel']['to']);
		// The service turns an accept without a place into the waiting list.
		$this->assertSame(expected: 'waitlisted', actual: $lifecycle['transitions']['waitlist']['to']);
	}//end testTheStatusHasItsTransitions()

	/**
	 * An event has a capacity, an approval switch and the form it takes sign-ups from.
	 *
	 * @return void
	 */
	public function testAnEventHasItsSignUpSettings(): void {
		$event = $this->schema(key: 'event')['properties'];

		$this->assertSame(expected: 'integer', actual: $event['capacity']['type']);
		$this->assertSame(expected: 0, actual: $event['capacity']['minimum']);
		$this->assertSame(expected: 'boolean', actual: $event['approvalRequired']['type']);
		$this->assertFalse(condition: $event['approvalRequired']['default']);
		$this->assertSame(expected: 'integer', actual: $event['signupForm']['type']);
	}//end testAnEventHasItsSignUpSettings()

	/**
	 * The import maps the registration and the settings keep its keys.
	 *
	 * @return void
	 */
	public function testTheImportAndTheSettingsKnowTheRegistration(): void {
		$slugs = (new ReflectionClass(SettingsLoadService::class))->getConstant('OBJECT_TYPE_SCHEMA_SLUGS');
		$this->assertSame(expected: 'larping_registration', actual: $slugs['registration'] ?? null);

		$keys = (new ReflectionClass(SettingsService::class))->getConstant('CONFIG_KEYS');
		foreach (['registration_schema', 'registration_register', 'registration_source'] as $key) {
			$this->assertContains(needle: $key, haystack: $keys);
		}
	}//end testTheImportAndTheSettingsKnowTheRegistration()

	/**
	 * The seed registrations validate against the real schema.
	 *
	 * @return void
	 */
	public function testTheSeedRegistrationsValidate(): void {
		$mock = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/larpinq_mock_register.json'), true);
		$schema = json_decode((string)json_encode($this->forOpis(schema: $this->schema(key: 'registration'))));
		$validator = new Validator();
		$seen = 0;
		foreach ($mock['components']['objects'] as $object) {
			if (($object['@self']['schema'] ?? '') !== 'larping_registration') {
				continue;
			}

			$seen++;
			unset($object['@self']);
			$result = $validator->validate(json_decode((string)json_encode($object)), $schema);
			$this->assertTrue(condition: $result->isValid(), message: 'seed registration must validate');
		}

		// ADR-111 rule 1 (gate-101): at least three demo objects per schema.
		$this->assertGreaterThanOrEqual(expected: 3, actual: $seen);
	}//end testTheSeedRegistrationsValidate()

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
