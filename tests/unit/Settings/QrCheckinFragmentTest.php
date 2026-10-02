<?php

/**
 * Larpinq QR Check-in Fragment Test
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
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The check-in code is a registration property only game masters and the
 * registration's player read, and only the server writes.
 */
class QrCheckinFragmentTest extends TestCase {

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
	 * Game masters and the player read the code; the booker, other players and every client but the server cannot set it.
	 *
	 * @return void
	 */
	public function testOnlyGameMastersAndThePlayerReadTheCode(): void {
		$property = $this->mergedRegister()['components']['schemas']['registration']['properties']['checkinCode'] ?? null;
		$this->assertIsArray($property, 'registration.checkinCode must be in the merged register');
		$this->assertSame(['gamemasters', ['group' => 'larpers', 'match' => ['playerUid' => '$userId']]], $property['authorization']['read']);
		$this->assertSame(['gamemasters'], $property['authorization']['update']);
		$this->assertSame('^([A-Z2-7]{26})?$', $property['pattern'], 'empty until accepted, else 26 base32 characters');
	}//end testOnlyGameMastersAndThePlayerReadTheCode()

	/**
	 * The registration the real listener stores with its code validates against the merged schema; a malformed code does not.
	 *
	 * @return void
	 */
	public function testTheStoredRegistrationValidates(): void {
		$world = new PaymentWorld($this);
		$world->seedRegistration(id: 'e0000000-0000-4000-8000-000000000001', player: PaymentWorld::ANNA, status: 'pending');
		$stored = $world->update(id: 'e0000000-0000-4000-8000-000000000001', data: ['status' => 'accepted']);
		unset($stored['@self']);

		$schema = json_decode((string)json_encode($this->forOpis(schema: $this->mergedRegister()['components']['schemas']['registration'])));
		$validator = new Validator();
		$result = $validator->validate(json_decode((string)json_encode($stored)), $schema);
		$this->assertTrue($result->isValid(), 'the stored registration must validate: ' . json_encode($result->error()?->args()));

		$stored['checkinCode'] = 'lowercase-is-not-a-code';
		$this->assertFalse($validator->validate(json_decode((string)json_encode($stored)), $schema)->isValid());
	}//end testTheStoredRegistrationValidates()

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
