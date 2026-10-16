<?php

/**
 * Every larpinq schema is exportable on the merged register (admin-import-export).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes.
 * OpenRegister reads the flag at `configuration.exportable` (Schema::jsonSerialize
 * mirrors it; a top-level `exportable` is folded into it on hydrate), and
 * CnIndexPage's Export menu renders only when it is true.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/data-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-AIE-001 and REQ-AIE-003 of admin-import-export.
 */
class AdminImportExportFragmentTest extends TestCase {

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
	 * Every schema, including those a fragment adds, carries exportable true.
	 *
	 * @return void
	 */
	public function testEverySchemaIsExportable(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		$this->assertArrayHasKey('attendance', $schemas, 'the fragment-added schema must be covered too');
		foreach ($schemas as $key => $schema) {
			$this->assertTrue(
				($schema['configuration']['exportable'] ?? null) === true,
				"schema {$key} must be exportable"
			);
		}
	}//end testEverySchemaIsExportable()

	/**
	 * The flag does not replace the configuration the other fragments set.
	 *
	 * @return void
	 */
	public function testTheFlagKeepsTheOtherConfiguration(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		$this->assertContains('photos', $schemas['character']['configuration']['linkedTypes']);
		$this->assertArrayHasKey('x-openregister-lifecycle', $schemas['character']['configuration']);
		$this->assertTrue($schemas['skill']['configuration']['x-openregister-shareable']);
		$this->assertContains('deck', $schemas['event']['configuration']['linkedTypes']);
	}//end testTheFlagKeepsTheOtherConfiguration()
}//end class
