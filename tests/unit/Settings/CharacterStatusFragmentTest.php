<?php

/**
 * The character status on the merged register (characters-status-and-bulk-edit).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, and validates the seed characters of
 * the mock register against the merged character schema with Opis.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-CSB-001 and REQ-CSB-002.
 */
class CharacterStatusFragmentTest extends TestCase {

	/**
	 * The merged schemas, as the import sees them.
	 *
	 * @return array<string, mixed> The schemas by key.
	 */
	private function schemas(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		return $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath)['components']['schemas'];
	}//end schemas()

	/**
	 * A character is active, retired or dead, active by default, filterable,
	 * read by everyone who reads the character, and set by game masters.
	 *
	 * @return void
	 */
	public function testACharacterHasAStatus(): void {
		$status = $this->schemas()['character']['properties']['status'];

		$this->assertSame(['active', 'retired', 'dead'], $status['enum']);
		$this->assertSame('active', $status['default']);
		$this->assertTrue($status['facetable']);
		$this->assertSame(['active' => 'Active', 'retired' => 'Retired', 'dead' => 'Dead'], $status['x-enum-labels']);
		$this->assertSame(['update' => ['gamemasters']], $status['authorization']);
	}//end testACharacterHasAStatus()

	/**
	 * Scenario "A retired captain is not offered for the next event": the
	 * participant picker asks for active characters of the event's world.
	 *
	 * @return void
	 */
	public function testThePickerOffersActiveCharactersOnly(): void {
		$this->assertSame(
			['setting' => '@object.setting', 'status' => 'active'],
			$this->schemas()['event']['properties']['players']['x-relation-filter']
		);
	}//end testThePickerOffersActiveCharactersOnly()

	/**
	 * The three demo characters carry the three statuses and validate.
	 *
	 * @return void
	 */
	public function testTheSeedCharactersValidate(): void {
		$appPath = dirname(__DIR__, 3);
		$mock = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_mock_register.json'), true);
		$schema = $this->schemas()['character'];
		unset($schema['authorization'], $schema['configuration']);
		$opis = json_decode((string)json_encode($this->forOpis(node: $schema)));
		$statuses = [];
		foreach ($mock['components']['objects'] as $object) {
			if (($object['@self']['schema'] ?? '') !== 'character') {
				continue;
			}

			unset($object['@self']);
			$statuses[] = $object['status'] ?? null;
			$result = (new Validator())->validate(json_decode((string)json_encode($object)), $opis);
			$this->assertTrue($result->isValid(), "seed character {$object['name']} must validate");
		}

		$this->assertSame(['active', 'retired', 'dead'], $statuses);
	}//end testTheSeedCharactersValidate()

	/**
	 * The schema as OpenRegister hands it to Opis: a `$ref` relation accepts a
	 * UUID string, and property rules and calculations are not JSON Schema.
	 *
	 * @param array<string, mixed> $node A schema node.
	 *
	 * @return array<string, mixed> The node without them, at every depth.
	 */
	private function forOpis(array $node): array {
		unset($node['$ref'], $node['authorization'], $node['calculation']);
		foreach ($node as $key => $value) {
			if (is_array($value) === true) {
				$node[$key] = $this->forOpis(node: $value);
			}
		}

		return $node;
	}//end forOpis()
}//end class
