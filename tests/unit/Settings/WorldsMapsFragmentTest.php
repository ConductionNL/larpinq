<?php
/**
 * The worldMap and mapPin schemas on the merged register, and the Aldmoor
 * seed of the mock register (worlds-maps).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes, and
 * validates the seed map and pins against the merged schemas with Opis, the
 * validator OpenRegister uses.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/worlds-maps/specs
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-WMP-001 and REQ-WMP-003 of worlds-maps, and the seed of design.md.
 */
class WorldsMapsFragmentTest extends TestCase {
	/**
	 * The register as OpenRegister imports it: monolith plus every fragment.
	 *
	 * @return array<string, mixed> The merged register.
	 */
	private function mergedRegister(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$this->assertIsArray(actual: $monolith);

		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$loader = $reflection->newInstanceWithoutConstructor();
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		// @var array<string, mixed> $merged
		$merged = $merge->invoke($loader, $monolith, $appPath);
		return $merged;
	}//end mergedRegister()

	/**
	 * The mock register's objects of one schema.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return list<array<string, mixed>> The seed objects.
	 */
	private function seed(string $schema): array {
		$appPath = dirname(__DIR__, 3);
		$mock = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_mock_register.json'), true);
		return array_values(
			array_filter(
				$mock['components']['objects'],
				static fn (array $object): bool => ($object['@self']['schema'] ?? '') === $schema
			)
		);
	}//end seed()

	/**
	 * Both schemas are in the register and the register lists them.
	 *
	 * @return void
	 */
	public function testTheSchemasAreInTheRegister(): void {
		$merged = $this->mergedRegister();
		$schemas = $merged['components']['schemas'];

		$this->assertSame(expected: 'larping_world_map', actual: $schemas['worldMap']['slug']);
		$this->assertSame(expected: 'larping_map_pin', actual: $schemas['mapPin']['slug']);
		$this->assertContains(needle: 'larping_world_map', haystack: $merged['components']['registers']['larpinq']['schemas']);
		$this->assertContains(needle: 'larping_map_pin', haystack: $merged['components']['registers']['larpinq']['schemas']);
		$this->assertContains(needle: 'larping_lore_page', haystack: $merged['components']['registers']['larpinq']['schemas'], message: 'the fragment must add to the list, not replace it');
	}//end testTheSchemasAreInTheRegister()

	/**
	 * The seed is the valley of Aldmoor with its three pins, as design.md has it.
	 *
	 * @return void
	 */
	public function testTheSeedIsTheValleyOfAldmoor(): void {
		$maps = $this->seed(schema: 'larping_world_map');
		$this->assertCount(expectedCount: 1, haystack: $maps);
		$this->assertSame(
			expected: ['The valley of Aldmoor', 2400, 1600, 'players'],
			actual: [$maps[0]['title'], $maps[0]['width'], $maps[0]['height'], $maps[0]['visibility']]
		);

		$pins = [];
		foreach ($this->seed(schema: 'larping_map_pin') as $pin) {
			$pins[$pin['label']] = [$pin['kind'], $pin['visibility'], array_key_exists('lorePage', $pin)];
			$this->assertLessThanOrEqual(expected: 2400, actual: $pin['x'], message: $pin['label'] . ' must lie on the map');
			$this->assertLessThanOrEqual(expected: 1600, actual: $pin['y'], message: $pin['label'] . ' must lie on the map');
		}

		$this->assertSame(
			expected: [
				'Aldmoor city' => ['town', 'players', true],
				'North gate' => ['ruin', 'players', true],
				'Ash grove' => ['danger', 'gamemasters', false],
			],
			actual: $pins
		);
	}//end testTheSeedIsTheValleyOfAldmoor()

	/**
	 * The pins link to lore pages the seed carries.
	 *
	 * @return void
	 */
	public function testThePinnedLorePagesAreSeeded(): void {
		$titles = array_column($this->seed(schema: 'larping_lore_page'), 'title');

		$this->assertContains(needle: 'The city of Aldmoor', haystack: $titles);
		$this->assertContains(needle: 'The fall of the north gate', haystack: $titles);
	}//end testThePinnedLorePagesAreSeeded()

	/**
	 * Every seed map and pin validates against the real schema, and a pin
	 * without a position does not.
	 *
	 * @return void
	 */
	public function testTheSeedValidates(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];
		$validator = new Validator();

		foreach (['worldMap' => 'larping_world_map', 'mapPin' => 'larping_map_pin'] as $name => $slug) {
			$schema = json_decode((string)json_encode($this->forOpis(schema: $schemas[$name])));
			foreach ($this->seed(schema: $slug) as $object) {
				$label = (string)($object['@self']['slug'] ?? $slug);
				unset($object['@self']);
				$result = $validator->validate(json_decode((string)json_encode($object)), $schema);
				$this->assertTrue(condition: $result->isValid(), message: 'seed object ' . $label . ' must validate');
			}
		}

		$pin = json_decode((string)json_encode($this->forOpis(schema: $schemas['mapPin'])));
		$bad = json_decode((string)json_encode(['map' => '00000000-0000-4000-8000-000000000000', 'label' => 'Nowhere', 'visibility' => 'players']));
		$this->assertFalse(condition: $validator->validate($bad, $pin)->isValid(), message: 'a pin without a position is refused');
	}//end testTheSeedValidates()

	/**
	 * The schema as OpenRegister hands it to Opis: a `$ref` relation accepts a
	 * UUID string, a file property is not JSON Schema, and the
	 * OpenRegister-only keys are left out.
	 *
	 * @param array<string, mixed> $schema The register schema.
	 *
	 * @return array<string, mixed> The JSON Schema.
	 */
	private function forOpis(array $schema): array {
		foreach ($schema['properties'] as $name => $property) {
			unset($schema['properties'][$name]['$ref']);
			if (($property['type'] ?? null) === 'file') {
				unset($schema['properties'][$name]);
			}
		}

		unset($schema['authorization'], $schema['configuration']);
		return $schema;
	}//end forOpis()
}//end class
