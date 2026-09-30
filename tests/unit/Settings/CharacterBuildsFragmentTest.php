<?php

/**
 * Character builds on the merged register (characters-multiple-builds).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes, and
 * validates the seed builds of the mock register against the merged schema
 * with Opis, the validator OpenRegister uses.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-builds/spec.md
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
 * REQ-CMB-001 and REQ-CMB-004 of characters-multiple-builds.
 */
class CharacterBuildsFragmentTest extends TestCase {

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
	 * The merged build schema.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function build(): array {
		$schemas = $this->mergedRegister()['components']['schemas'];
		$this->assertArrayHasKey('characterBuild', $schemas, 'the build schema must be in the merged register');
		return $schemas['characterBuild'];
	}//end build()

	/**
	 * The build schema is in the register, exportable, and holds a character,
	 * a name, a purpose, notes, and skills, items and conditions.
	 *
	 * @return void
	 */
	public function testABuildHasItsFields(): void {
		$this->assertContains('larping_character_build', $this->mergedRegister()['components']['registers']['larpinq']['schemas']);

		$build = $this->build();
		$this->assertSame('larping_character_build', $build['slug']);
		$this->assertTrue($build['configuration']['exportable']);
		$this->assertSame(['character', 'name'], $build['required']);
		$this->assertSame('character', $build['properties']['character']['$ref']);
		$this->assertSame(['plan', 'test', 'other-game'], $build['properties']['purpose']['enum']);
		$this->assertSame('larping_skill', $build['properties']['skills']['$ref']);
		$this->assertSame('larping_item', $build['properties']['items']['$ref']);
		$this->assertSame('condition', $build['properties']['conditions']['$ref']);
		foreach (['skills', 'items', 'conditions'] as $list) {
			$this->assertSame(['setting' => '@object.setting'], $build['properties'][$list]['x-relation-filter'], "{$list} come from the character's world");
		}
	}//end testABuildHasItsFields()

	/**
	 * The owner and the world are copied from the character on every save.
	 *
	 * @return void
	 */
	public function testOwnerAndWorldAreCopiedFromTheCharacter(): void {
		$build = $this->build();

		$this->assertSame(
			['mode' => 'relatedObject', 'schema' => 'character', 'field' => 'character'],
			$build['configuration']['x-openregister-references']['character']
		);
		$this->assertSame('@ref.character.ownerUid', $build['properties']['ownerUid']['calculation']['expression']['prop']);
		$this->assertSame('@ref.character.setting', $build['properties']['setting']['calculation']['expression']['prop']);
		$this->assertTrue($build['properties']['ownerUid']['calculation']['materialise']);
		$this->assertTrue($build['properties']['setting']['calculation']['materialise']);
	}//end testOwnerAndWorldAreCopiedFromTheCharacter()

	/**
	 * Game masters and the character's player read, change and delete a build;
	 * nobody else does. The schema declares its own read, so the register's
	 * manage-only block never becomes its rules.
	 *
	 * @return void
	 */
	public function testBuildsFollowTheCharactersAccess(): void {
		$rules = $this->build()['authorization'];
		$ownerOrGameMaster = [['group' => 'gamemasters'], ['group' => 'larpers', 'match' => ['ownerUid' => '$userId']]];

		foreach (['read', 'update', 'delete'] as $action) {
			$this->assertSame($ownerOrGameMaster, $rules[$action], "{$action} is for game masters and the owner");
		}

		// A create is asked about the incoming data, before the owner is
		// copied from the character; CharacterConnectionGuard refuses a
		// player's build on someone else's character.
		$this->assertSame([['group' => 'gamemasters'], 'larpers'], $rules['create']);
	}//end testBuildsFollowTheCharactersAccess()

	/**
	 * The import writes the build's schema key, so the report endpoint and the
	 * guard find the schema in production.
	 *
	 * @return void
	 */
	public function testTheImportConfiguresTheBuildSchema(): void {
		$slugs = (new ReflectionClass(SettingsLoadService::class))->getConstant('OBJECT_TYPE_SCHEMA_SLUGS');
		$keys = (new ReflectionClass(SettingsService::class))->getConstant('CONFIG_KEYS');

		$this->assertSame('larping_character_build', $slugs['characterbuild'] ?? null);
		$this->assertContains('characterbuild_schema', $keys);
		$this->assertContains('characterbuild_register', $keys);
	}//end testTheImportConfiguresTheBuildSchema()

	/**
	 * No property carries a `user` format: OpenRegister drops a schema that does.
	 *
	 * @return void
	 */
	public function testNoUserFormat(): void {
		foreach ($this->build()['properties'] as $name => $property) {
			$this->assertNotSame('user', ($property['format'] ?? null), $name);
		}
	}//end testNoUserFormat()

	/**
	 * The seed builds validate against the real schema, three of them.
	 *
	 * @return void
	 */
	public function testTheSeedBuildsValidate(): void {
		$appPath = dirname(__DIR__, 3);
		$mock = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_mock_register.json'), true);
		$schema = json_decode((string)json_encode($this->forOpis(schema: $this->build())));
		$validator = new Validator();
		$names = [];
		foreach ($mock['components']['objects'] as $object) {
			if (($object['@self']['schema'] ?? '') !== 'larping_character_build') {
				continue;
			}

			unset($object['@self']);
			$names[] = $object['name'];
			$result = $validator->validate(json_decode((string)json_encode($object)), $schema);
			$this->assertTrue($result->isValid(), "seed build {$object['name']} must validate");
		}

		// ADR-111 rule 1 (gate-101): at least three demo objects per schema.
		$this->assertSame(['Alchemist path', 'Winter campaign', 'Shield bearer test'], $names);
	}//end testTheSeedBuildsValidate()

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
			unset(
				$schema['properties'][$name]['$ref'],
				$schema['properties'][$name]['calculation'],
				$schema['properties'][$name]['x-relation-filter'],
				$schema['properties'][$name]['visible']
			);
		}

		unset($schema['authorization'], $schema['configuration']);
		return $schema;
	}//end forOpis()
}//end class
