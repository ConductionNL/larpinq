<?php

/**
 * The Aldmoor rulebook seed of the demo data (rules-rulebook-and-spell-lists 1.3).
 *
 * Reads the real demo descriptor and the register as the import merges it:
 * the lists, spells and rules chapters of design.md are there, every
 * `@ref:` token names an object of the same seed, Firebolt and Ward cost
 * mana through an effect on the Mana ability, Blood rite is hidden from the
 * rulebook, and every seeded object validates against its merged schema with
 * Opis, the validator OpenRegister uses, once its references are resolved.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-RRS-001, REQ-RRS-003 and REQ-RRS-004 seed data.
 */
class RulebookSeedTest extends TestCase {

	/**
	 * The demo objects, keyed by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function objects(): array {
		$mock = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/larpinq_mock_register.json'), true);
		$bySlug = [];
		foreach ($mock['components']['objects'] as $object) {
			$this->assertArrayNotHasKey(key: $object['@self']['slug'], array: $bySlug, message: 'a demo slug is unique');
			$bySlug[$object['@self']['slug']] = $object;
		}

		return $bySlug;
	}//end objects()

	/**
	 * The register as OpenRegister imports it: monolith plus every fragment.
	 *
	 * @return array<string, mixed>
	 */
	private function mergedRegister(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		return $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath);
	}//end mergedRegister()

	/**
	 * The seeded objects of one schema in Aldmoor, by name.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function inAldmoor(string $schema): array {
		$found = [];
		foreach ($this->objects() as $object) {
			if ($object['@self']['schema'] === $schema && ($object['setting'] ?? '') === '@ref:setting:setting-aldmoor') {
				$found[$object['name'] ?? $object['title']] = $object;
			}
		}

		return $found;
	}//end inAldmoor()

	public function testAldmoorHasTheListsOfTheDesign(): void {
		$this->assertSame(expected: 'Aldmoor', actual: $this->objects()['setting-aldmoor']['name']);

		$lists = $this->inAldmoor(schema: 'larping_skill_list');
		$this->assertSame(
			expected: [
				'Martial skills' => ['skills', null, 1],
				'Arcane spells' => ['spells', '@ref:ability:ability-aldmoor-mana', 2],
				'Divine powers' => ['powers', null, 3],
			],
			actual: array_map(static fn (array $list): array => [$list['kind'], $list['costAbility'] ?? null, $list['order']], $lists)
		);
		$this->assertSame(expected: 'Mana', actual: $this->objects()['ability-aldmoor-mana']['name']);
	}//end testAldmoorHasTheListsOfTheDesign()

	public function testFireboltAndWardCostManaAndNeedArcaneLore(): void {
		$objects = $this->objects();
		foreach (['skill-firebolt' => 3, 'skill-ward' => 2] as $slug => $cost) {
			$skill = $objects[$slug];
			$this->assertSame(expected: '@ref:larping_skill_list:skill-list-arcane-spells', actual: $skill['list'], message: $slug);
			$this->assertSame(expected: ['@ref:larping_skill:skill-arcane-lore'], actual: $skill['requiredSkills'], message: $slug);
			$this->assertCount(expectedCount: 1, haystack: $skill['effects'], message: $slug);

			$effect = $objects[substr($skill['effects'][0], strlen('@ref:effect:'))];
			$this->assertSame(
				expected: [$cost, 'negative', ['@ref:ability:ability-aldmoor-mana']],
				actual: [$effect['modifier'], $effect['modification'], $effect['abilities']],
				message: $slug . ' pays its cost from Mana'
			);
		}

		$this->assertSame(expected: 'Arcane lore', actual: $objects['skill-arcane-lore']['name']);
	}//end testFireboltAndWardCostManaAndNeedArcaneLore()

	public function testBloodRiteIsTheOnlySeededSkillHiddenFromTheRulebook(): void {
		$hidden = array_keys(array_filter($this->inAldmoor(schema: 'larping_skill'), static fn (array $skill): bool => ($skill['hiddenFromRulebook'] ?? false) === true));
		$this->assertSame(expected: ['Blood rite'], actual: $hidden);
	}//end testBloodRiteIsTheOnlySeededSkillHiddenFromTheRulebook()

	public function testTheTwoRulesChaptersAreForPlayersInOrder(): void {
		$chapters = array_filter($this->inAldmoor(schema: 'larping_lore_page'), static fn (array $page): bool => $page['category'] === 'rules');
		uasort($chapters, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

		$this->assertSame(expected: ['How combat works', 'Casting a spell'], actual: array_keys($chapters));
		foreach ($chapters as $title => $chapter) {
			$this->assertSame(expected: 'players', actual: $chapter['visibility'], message: $title);
			$this->assertNotSame(expected: '', actual: trim($chapter['body']), message: $title);
		}
	}//end testTheTwoRulesChaptersAreForPlayersInOrder()

	public function testEveryReferenceNamesASeededObjectOfThatSchema(): void {
		$objects = $this->objects();
		$refs = 0;
		array_walk_recursive(
			$objects,
			function (mixed $value) use ($objects, &$refs): void {
				if (is_string($value) === false || str_starts_with($value, '@ref:') === false) {
					return;
				}

				$refs++;
				[, $schema, $slug] = explode(':', $value, 3);
				$this->assertArrayHasKey(key: $slug, array: $objects, message: $value);
				$this->assertSame(expected: $schema, actual: $objects[$slug]['@self']['schema'], message: $value);
			}
		);
		$this->assertGreaterThanOrEqual(expected: 20, actual: $refs);
	}//end testEveryReferenceNamesASeededObjectOfThatSchema()

	public function testTheSeedValidatesAgainstTheMergedSchemas(): void {
		$schemas = [];
		foreach ($this->mergedRegister()['components']['schemas'] as $schema) {
			$schemas[$schema['slug']] = $schema;
		}

		$validator = new Validator();
		$seen = [];
		foreach ($this->objects() as $slug => $object) {
			if (str_contains(json_encode($object), 'setting-aldmoor') === false && $slug !== 'setting-aldmoor') {
				continue;
			}

			$schemaSlug = $object['@self']['schema'];
			$seen[$schemaSlug] = ($seen[$schemaSlug] ?? 0) + 1;
			unset($object['@self']);
			// The importer swaps each `@ref:` token for the referenced object's uuid before it validates.
			array_walk_recursive(
				$object,
				static function (mixed &$value): void {
					if (is_string($value) === true && str_starts_with($value, '@ref:') === true) {
						$value = '5f0c2d1e-7a3b-4c8d-9e0f-1a2b3c4d5e6f';
					}
				}
			);

			$result = $validator->validate(json_decode((string)json_encode($object)), json_decode((string)json_encode($this->forOpis(schema: $schemas[$schemaSlug]))));
			$this->assertTrue(condition: $result->isValid(), message: "seed {$slug} must validate");
		}

		$this->assertSame(
			expected: ['setting' => 1, 'ability' => 1, 'larping_skill_list' => 3, 'effect' => 2, 'larping_skill' => 4, 'larping_lore_page' => 2],
			actual: $seen
		);
	}//end testTheSeedValidatesAgainstTheMergedSchemas()

	/**
	 * The schema as OpenRegister hands it to Opis: a `$ref` relation accepts a
	 * UUID string, and the OpenRegister-only keys are not JSON Schema.
	 *
	 * @param array<string, mixed> $schema The register schema.
	 *
	 * @return array<string, mixed>
	 */
	private function forOpis(array $schema): array {
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation']);
			if (isset($schema['properties'][$name]['items']) === true && is_array($schema['properties'][$name]['items']) === true) {
				unset($schema['properties'][$name]['items']['$ref']);
			}
		}

		unset($schema['authorization'], $schema['configuration']);
		return $schema;
	}//end forOpis()
}//end class
