<?php

/**
 * The lorePage schema on the merged register (worlds-lore-pages).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes, and
 * validates the seed pages of the mock register against the merged schema
 * with Opis, the validator OpenRegister uses.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/world-lore/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-WLP-001 to REQ-WLP-003 of worlds-lore-pages.
 */
class LorePagesFragmentTest extends TestCase {

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
	 * The merged lorePage schema.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function lorePage(): array {
		$schemas = $this->mergedRegister()['components']['schemas'];
		$this->assertArrayHasKey('lorePage', $schemas, 'the lorePage schema must be in the merged register');
		return $schemas['lorePage'];
	}//end lorePage()

	/**
	 * The schema is in the register with the fields the pages use.
	 *
	 * @return void
	 */
	public function testTheSchemaIsInTheRegister(): void {
		$register = $this->mergedRegister()['components']['registers']['larpinq'];
		$schema = $this->lorePage();

		$this->assertSame('larping_lore_page', $schema['slug']);
		$this->assertContains('larping_lore_page', $register['schemas']);
		$this->assertContains('larping_skill', $register['schemas'], 'the fragment must add to the list, not replace it');
		foreach (['title', 'body', 'setting', 'category', 'visibility', 'revealFrom', 'parent', 'order'] as $field) {
			$this->assertArrayHasKey($field, $schema['properties'], "lorePage needs {$field}");
		}
		$this->assertSame('markdown', $schema['properties']['body']['format']);
		$this->assertSame('date-time', $schema['properties']['revealFrom']['format']);
		$this->assertSame(['place', 'faction', 'history', 'rules', 'other'], $schema['properties']['category']['enum']);
		$this->assertTrue($schema['configuration']['exportable'], 'every larpinq schema exports (admin-import-export)');
	}//end testTheSchemaIsInTheRegister()

	/**
	 * Players read only pages for players whose reveal moment has passed.
	 *
	 * @return void
	 */
	public function testPlayersReadOnlyRevealedPlayerPages(): void {
		$authorization = $this->lorePage()['authorization'];

		$this->assertSame(
			[
				['group' => 'gamemasters'],
				['group' => 'larpers', 'match' => ['visibility' => 'players', 'revealFrom' => ['$lte' => '$now']]],
			],
			$authorization['read']
		);
		foreach (['create', 'update', 'delete'] as $action) {
			$this->assertSame(['gamemasters'], $authorization[$action], "only game masters may {$action}");
		}
	}//end testPlayersReadOnlyRevealedPlayerPages()

	/**
	 * A page with no reveal moment cannot be saved, so no page needs a rule for
	 * an empty moment.
	 *
	 * @return void
	 */
	public function testTheRevealMomentIsRequired(): void {
		$this->assertContains('revealFrom', $this->lorePage()['required']);
		$this->assertContains('visibility', $this->lorePage()['required']);
	}//end testTheRevealMomentIsRequired()

	/**
	 * The four seed pages validate against the real schema.
	 *
	 * @return void
	 */
	public function testTheSeedPagesValidate(): void {
		$appPath = dirname(__DIR__, 3);
		$mock = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_mock_register.json'), true);
		$pages = array_values(
			array_filter(
				$mock['components']['objects'],
				static fn (array $object): bool => ($object['@self']['schema'] ?? '') === 'larping_lore_page'
			)
		);
		$this->assertCount(4, $pages);

		$validator = new Validator();
		$schema = json_decode((string)json_encode($this->forOpis(schema: $this->lorePage())));
		foreach ($pages as $page) {
			unset($page['@self']);
			$result = $validator->validate(json_decode((string)json_encode($page)), $schema);
			$this->assertTrue($result->isValid(), 'seed page ' . $page['title'] . ' must validate');
		}

		$bad = json_decode((string)json_encode(['title' => 'No moment', 'visibility' => 'players']));
		$this->assertFalse($validator->validate($bad, $schema)->isValid(), 'a page without a reveal moment is refused');
	}//end testTheSeedPagesValidate()

	/**
	 * The schema as OpenRegister hands it to Opis: a `$ref` relation accepts a
	 * UUID string, and the OpenRegister-only keys are not JSON Schema.
	 *
	 * @param array<string, mixed> $schema The register schema.
	 *
	 * @return array<string, mixed> The JSON Schema.
	 */
	private function forOpis(array $schema): array {
		foreach ($schema['properties'] as $name => $property) {
			unset($schema['properties'][$name]['$ref']);
		}

		unset($schema['authorization'], $schema['configuration']);
		return $schema;
	}//end forOpis()
}//end class
