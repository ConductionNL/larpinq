<?php

/**
 * The MCP dialect on the merged register (larpinq-mcp-adoption).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes, and
 * checks the `configuration.x-openregister-mcp` blocks OpenRegister derives
 * its MCP tools from.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/larpinq-mcp-adoption
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-001, REQ-005 and REQ-007 of larpinq-mcp-adoption.
 */
class McpAdoptionFragmentTest extends TestCase {

	/**
	 * The verbs each curated schema must expose, and nothing more.
	 *
	 * @var array<string, list<string>>
	 */
	private const CURATED_VERBS = [
		'character'  => ['search', 'get', 'create'],
		'player'     => ['search', 'get'],
		'setting'    => ['search', 'get'],
		'skill'      => ['search', 'get'],
		'item'       => ['search', 'get'],
		'condition'  => ['search', 'get'],
		'event'      => ['search', 'get'],
		'xpAward'    => ['search', 'get', 'create'],
		'attendance' => ['search', 'get', 'update'],
	];

	/**
	 * The scope each verb must declare.
	 *
	 * @var array<string, string>
	 */
	private const VERB_SCOPE = [
		'search' => 'read',
		'get'    => 'read',
		'create' => 'create',
		'update' => 'update',
	];

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
	 * The MCP block of one merged schema, or null when it declares none.
	 *
	 * @param array<string, mixed> $schemas The merged schemas.
	 * @param string               $name    The schema key.
	 *
	 * @return array<string, mixed>|null The x-openregister-mcp block.
	 */
	private function mcpBlock(array $schemas, string $name): ?array {
		return $schemas[$name]['configuration']['x-openregister-mcp'] ?? null;
	}//end mcpBlock()

	/**
	 * The 9 curated schemas are enabled with exactly their argued verbs, never delete.
	 *
	 * @return void
	 */
	public function testCuratedSchemasExposeExactlyTheirVerbs(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		foreach (self::CURATED_VERBS as $name => $verbs) {
			$block = $this->mcpBlock(schemas: $schemas, name: $name);
			$this->assertIsArray(actual: $block, message: "schema {$name} must declare x-openregister-mcp");
			$this->assertTrue(condition: $block['enabled'], message: "schema {$name} must be enabled");
			$this->assertSame(expected: $verbs, actual: array_keys($block['tools']), message: "schema {$name} verbs");
			$this->assertArrayNotHasKey(key: 'delete', array: $block['tools']);
		}
	}//end testCuratedSchemasExposeExactlyTheirVerbs()

	/**
	 * The two internal primitives stay off the MCP surface.
	 *
	 * @return void
	 */
	public function testInternalPrimitivesAreExcluded(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		$this->assertArrayHasKey(key: 'ability', array: $schemas);
		$this->assertArrayHasKey(key: 'effect', array: $schemas);
		$this->assertNull(actual: $this->mcpBlock(schemas: $schemas, name: 'ability'));
		$this->assertNull(actual: $this->mcpBlock(schemas: $schemas, name: 'effect'));

		$declared = [];
		foreach ($schemas as $name => $schema) {
			if (isset($schema['configuration']['x-openregister-mcp']) === true) {
				$declared[] = $name;
			}
		}

		sort($declared);
		$expected = array_keys(self::CURATED_VERBS);
		sort($expected);
		$this->assertSame(expected: $expected, actual: $declared);
	}//end testInternalPrimitivesAreExcluded()

	/**
	 * Every search filter names a real property of the merged schema.
	 *
	 * @return void
	 */
	public function testEverySearchFilterIsARealProperty(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		foreach (array_keys(self::CURATED_VERBS) as $name) {
			$filters = $this->mcpBlock(schemas: $schemas, name: $name)['tools']['search']['filters'];
			$this->assertNotEmpty(actual: $filters, message: "schema {$name} search must declare filters");
			foreach ($filters as $filter) {
				$this->assertArrayHasKey(
					key: $filter,
					array: $schemas[$name]['properties'],
					message: "filter {$filter} on {$name} must be a real property"
				);
			}
		}
	}//end testEverySearchFilterIsARealProperty()

	/**
	 * Scope and hints are honest: reads are read-only, writes are not destructive.
	 *
	 * @return void
	 */
	public function testScopesAndHintsMatchTheVerb(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		foreach (array_keys(self::CURATED_VERBS) as $name) {
			foreach ($this->mcpBlock(schemas: $schemas, name: $name)['tools'] as $verb => $tool) {
				$label = "{$name}.{$verb}";
				$this->assertSame(expected: self::VERB_SCOPE[$verb], actual: $tool['scope'], message: $label);
				$this->assertSame(expected: self::VERB_SCOPE[$verb] === 'read', actual: $tool['readOnlyHint'], message: $label);
				$this->assertFalse(condition: $tool['destructiveHint'], message: $label);
				$this->assertIsBool(actual: $tool['idempotentHint'], message: $label);
				$this->assertNotSame(expected: '', actual: trim((string)($tool['description'] ?? '')), message: $label);
			}
		}

		$tools = $this->mcpBlock(schemas: $schemas, name: 'character')['tools'];
		$this->assertFalse(condition: $tools['create']['idempotentHint']);
		$this->assertFalse(condition: $this->mcpBlock(schemas: $schemas, name: 'xpAward')['tools']['create']['idempotentHint']);
		$this->assertTrue(condition: $this->mcpBlock(schemas: $schemas, name: 'attendance')['tools']['update']['idempotentHint']);
	}//end testScopesAndHintsMatchTheVerb()

	/**
	 * The writes stay bounded by the gates they rely on, and the merge keeps them.
	 *
	 * @return void
	 */
	public function testTheWritesKeepTheirBoundingGates(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		$lifecycle = $schemas['character']['configuration']['x-openregister-lifecycle'];
		$this->assertSame(expected: 'approved', actual: $lifecycle['field']);
		$this->assertSame(expected: 'no', actual: $lifecycle['initial']);
		$this->assertContains(needle: 'photos', haystack: $schemas['character']['configuration']['linkedTypes']);

		$this->assertContains(needle: 'gamemasters', haystack: $schemas['xpAward']['authorization']['create']);
		$this->assertContains(needle: 'gamemasters', haystack: $schemas['attendance']['authorization']['update']);
	}//end testTheWritesKeepTheirBoundingGates()
}//end class
