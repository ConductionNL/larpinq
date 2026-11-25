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
 * @spec openspec/changes/archive/2026-11-25-worlds-maps/specs
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
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
	 * Players read a map or pin only when it is meant for players; only game
	 * masters read the rest and write anything (REQ-WMP-003).
	 *
	 * @return void
	 */
	public function testPlayersReadOnlyWhatIsMeantForPlayers(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		foreach (['worldMap', 'mapPin'] as $name) {
			$authorization = $schemas[$name]['authorization'];
			$this->assertSame(
				expected: [
					['group' => 'gamemasters'],
					['group' => 'larpers', 'match' => ['visibility' => 'players']],
				],
				actual: $authorization['read'],
				message: $name . ' must let players read only what is meant for players'
			);
			foreach (['create', 'update', 'delete'] as $action) {
				$this->assertSame(expected: ['gamemasters'], actual: $authorization[$action], message: $name . ' ' . $action . ' is for game masters only');
			}
		}
	}//end testPlayersReadOnlyWhatIsMeantForPlayers()

	/**
	 * A new map or pin is hidden from players until a game master says otherwise.
	 *
	 * @return void
	 */
	public function testANewMapOrPinStartsHiddenFromPlayers(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		foreach (['worldMap', 'mapPin'] as $name) {
			$visibility = $schemas[$name]['properties']['visibility'];
			$this->assertSame(expected: 'gamemasters', actual: $visibility['default'], message: $name);
			$this->assertSame(expected: ['gamemasters', 'players'], actual: $visibility['enum'], message: $name);
			$this->assertContains(needle: 'visibility', haystack: $schemas[$name]['required'], message: $name);
		}
	}//end testANewMapOrPinStartsHiddenFromPlayers()

	/**
	 * The relations point at the world, the map and the lore page.
	 *
	 * @return void
	 */
	public function testTheRelationsPointAtTheirSchemas(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		$this->assertSame(expected: 'setting', actual: $schemas['worldMap']['properties']['setting']['$ref']);
		$this->assertSame(expected: 'larping_world_map', actual: $schemas['mapPin']['properties']['map']['$ref']);
		$this->assertSame(expected: 'larping_lore_page', actual: $schemas['mapPin']['properties']['lorePage']['$ref']);
		$this->assertSame(expected: 'file', actual: $schemas['worldMap']['properties']['image']['type']);
		$this->assertNotContains(needle: 'lorePage', haystack: $schemas['mapPin']['required'], message: 'a pin may have no lore page');
	}//end testTheRelationsPointAtTheirSchemas()

	/**
	 * Pins the schema refuses.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function refusedPins(): array {
		$pin = [
			'map'        => '00000000-0000-4000-8000-000000000000',
			'label'      => 'Aldmoor city',
			'x'          => 10,
			'y'          => 20,
			'kind'       => 'town',
			'visibility' => 'players',
		];
		return [
			'left of the map'     => [array_merge($pin, ['x' => -1])],
			'above the map'       => [array_merge($pin, ['y' => -1])],
			'a fractional x'      => [array_merge($pin, ['x' => 1.5])],
			'an unknown kind'     => [array_merge($pin, ['kind' => 'castle'])],
			'an unknown audience' => [array_merge($pin, ['visibility' => 'everyone'])],
			'no label'            => [array_diff_key($pin, ['label' => true])],
			'no map'              => [array_diff_key($pin, ['map' => true])],
			'a label too long'    => [array_merge($pin, ['label' => str_repeat('a', 256)])],
		];
	}//end refusedPins()

	/**
	 * The pin schema refuses a pin off the map, of an unknown kind or audience,
	 * or without its required fields.
	 *
	 * @param array<string, mixed> $pin The pin.
	 *
	 * @return void
	 */
	#[DataProvider('refusedPins')]
	public function testThePinSchemaRefuses(array $pin): void {
		$this->assertFalse(condition: $this->validates(schema: 'mapPin', object: $pin));
	}//end testThePinSchemaRefuses()

	/**
	 * A pin at the corner of the map, without a lore page, is accepted.
	 *
	 * @return void
	 */
	public function testThePinSchemaAcceptsAPinAtTheCorner(): void {
		$pin = ['map' => '00000000-0000-4000-8000-000000000000', 'label' => 'Corner', 'x' => 0, 'y' => 0, 'visibility' => 'gamemasters'];

		$this->assertTrue(condition: $this->validates(schema: 'mapPin', object: $pin));
	}//end testThePinSchemaAcceptsAPinAtTheCorner()

	/**
	 * The map schema refuses a map without a title, with no size or for an
	 * unknown audience.
	 *
	 * @return void
	 */
	public function testTheMapSchemaRefusesAMapWithoutTitleOrSize(): void {
		$map = ['title' => 'The valley of Aldmoor', 'width' => 2400, 'height' => 1600, 'visibility' => 'players'];

		$this->assertTrue(condition: $this->validates(schema: 'worldMap', object: $map));
		$this->assertFalse(condition: $this->validates(schema: 'worldMap', object: array_diff_key($map, ['title' => true])), message: 'no title');
		$this->assertFalse(condition: $this->validates(schema: 'worldMap', object: array_merge($map, ['width' => 0])), message: 'zero width');
		$this->assertFalse(condition: $this->validates(schema: 'worldMap', object: array_merge($map, ['height' => 0])), message: 'zero height');
		$this->assertFalse(
			condition: $this->validates(schema: 'worldMap', object: array_merge($map, ['visibility' => 'public'])),
			message: 'unknown audience'
		);
	}//end testTheMapSchemaRefusesAMapWithoutTitleOrSize()

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

	/**
	 * Whether an object validates against one merged register schema, as
	 * OpenRegister hands it to Opis.
	 *
	 * @param string               $schema The schema key in the register.
	 * @param array<string, mixed> $object The object.
	 *
	 * @return bool True when it validates.
	 */
	private function validates(string $schema, array $object): bool {
		$jsonSchema = json_decode((string)json_encode($this->forOpis(schema: $this->mergedRegister()['components']['schemas'][$schema])));

		return (new Validator())->validate(json_decode((string)json_encode($object)), $jsonSchema)->isValid();
	}//end validates()
}//end class
