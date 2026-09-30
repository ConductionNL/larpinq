<?php

/**
 * Character field definitions and the two value properties on the merged
 * register (characters-custom-fields).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes, and
 * validates the seed objects of the mock register against the merged schemas
 * with Opis, the validator OpenRegister uses.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-CCF-001 and REQ-CCF-003 of characters-custom-fields.
 */
class CustomFieldsFragmentTest extends TestCase {

	/**
	 * The register as OpenRegister imports it: monolith plus every fragment.
	 *
	 * @return array<string, mixed> The merged register.
	 */
	private function mergedRegister(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$this->assertIsArray($monolith);

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
	 * The definition schema is in the register; game masters write it, and
	 * players read only the definitions meant for them.
	 *
	 * @return void
	 */
	public function testDefinitionsAreGameMasterObjects(): void {
		$register = $this->mergedRegister()['components']['registers']['larpinq'];
		$this->assertContains('larping_character_field', $register['schemas']);

		$field = $this->schema(key: 'characterField');
		$this->assertSame('larping_character_field', $field['slug']);
		$this->assertSame(['text', 'number', 'choice', 'yes-no'], $field['properties']['fieldType']['enum']);
		$this->assertSame(['gamemasters', 'owner'], $field['properties']['visibility']['enum']);
		$this->assertSame('setting', $field['properties']['setting']['$ref']);
		$this->assertSame(
			['gamemasters', ['group' => 'larpers', 'match' => ['visibility' => 'owner']]],
			$field['authorization']['read']
		);
		foreach (['create', 'update', 'delete'] as $action) {
			$this->assertSame(['gamemasters'], $field['authorization'][$action]);
		}
	}//end testDefinitionsAreGameMasterObjects()

	/**
	 * Private values are for game masters on every path; the player's own
	 * values are for game masters and the owner.
	 *
	 * @return void
	 */
	public function testPrivateValuesStayWithGameMasters(): void {
		$character = $this->schema(key: 'character');

		$this->assertSame('object', $character['properties']['customFieldsPrivate']['type']);
		$this->assertSame(['gamemasters'], $character['properties']['customFieldsPrivate']['authorization']['read']);
		$this->assertSame(['gamemasters'], $character['properties']['customFieldsPrivate']['authorization']['update']);
		$owner = ['gamemasters', ['group' => 'larpers', 'match' => ['ownerUid' => '$userId']]];
		$this->assertSame($owner, $character['properties']['customFields']['authorization']['read']);
		$this->assertSame($owner, $character['properties']['customFields']['authorization']['update']);
		// The fragment adds properties; the character keeps its own.
		$this->assertArrayHasKey('writingStep', $character['properties']);
	}//end testPrivateValuesStayWithGameMasters()

	/**
	 * The seed definitions and the seeded character validate against the real
	 * schemas, with at least three definitions (gate-101).
	 *
	 * @return void
	 */
	public function testTheSeedObjectsValidate(): void {
		$appPath = dirname(__DIR__, 3);
		$mock = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_mock_register.json'), true);
		$bySlug = ['larping_character_field' => 'characterField', 'character' => 'character'];
		$validator = new Validator();
		$seen = [];
		foreach ($mock['components']['objects'] as $object) {
			$slug = $object['@self']['schema'] ?? '';
			if (isset($bySlug[$slug]) === false) {
				continue;
			}
			$seen[$slug] = ($seen[$slug] ?? 0) + 1;
			$schema = json_decode((string)json_encode($this->forOpis(schema: $this->schema(key: $bySlug[$slug]))));
			unset($object['@self']);
			$result = $validator->validate(json_decode((string)json_encode($object)), $schema);
			$this->assertTrue($result->isValid(), "seed {$slug} must validate");
		}
		$this->assertSame(4, $seen['larping_character_field'] ?? 0);
	}//end testTheSeedObjectsValidate()

	/**
	 * The schema as OpenRegister hands it to Opis: a `$ref` relation accepts a
	 * UUID string, and the OpenRegister-only keys are not JSON Schema.
	 *
	 * @param array<string, mixed> $schema The register schema.
	 *
	 * @return array<string, mixed> The JSON Schema.
	 */
	private function forOpis(array $schema): array {
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation']);
		}

		unset($schema['authorization'], $schema['configuration']);
		// The character's relation arrays carry `$ref` inside `items` too.
		return $this->withoutRefs(node: $schema);
	}//end forOpis()

	/**
	 * The node with every `$ref` removed, at any depth.
	 *
	 * @param array<mixed> $node The node.
	 *
	 * @return array<mixed> The node without references.
	 */
	private function withoutRefs(array $node): array {
		unset($node['$ref']);
		foreach ($node as $key => $value) {
			if (is_array($value) === true) {
				$node[$key] = $this->withoutRefs(node: $value);
			}
		}

		return $node;
	}//end withoutRefs()
}//end class
