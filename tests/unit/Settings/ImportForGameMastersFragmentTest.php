<?php

/**
 * Game masters may manage, and so import into, the larpinq register
 * (DECISIONS row 25, data-portability REQ-AIE-002).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes.
 * OpenRegister's register import (`RegistersController::import`) answers only
 * to users its `checkRegisterManagePermission()` lets through: the admin
 * group, or a group named in the register's `authorization.manage`.
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

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The register's manage rule names the game master group, and nothing else.
 */
class ImportForGameMastersFragmentTest extends TestCase {

	/**
	 * The monolith register file.
	 *
	 * @return array<string, mixed> The register as shipped before fragments.
	 */
	private function monolith(): array {
		$data = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/larpinq_register.json'), true);
		$this->assertIsArray($data);
		return $data;
	}//end monolith()

	/**
	 * The register as OpenRegister imports it: monolith plus every fragment.
	 *
	 * @return array<string, mixed> The merged register.
	 */
	private function mergedRegister(): array {
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$loader = $reflection->newInstanceWithoutConstructor();
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		// @var array<string, mixed> $merged
		$merged = $merge->invoke($loader, $this->monolith(), dirname(__DIR__, 3));
		return $merged;
	}//end mergedRegister()

	/**
	 * Game masters may manage the register; players are not named.
	 *
	 * @return void
	 */
	public function testGameMastersManageTheRegister(): void {
		$register = $this->mergedRegister()['components']['registers']['larpinq'];

		$this->assertSame([Application::GM_GROUP], $register['authorization']['manage'] ?? null);
		$this->assertNotContains('larpers', $register['authorization']['manage']);
	}//end testGameMastersManageTheRegister()

	/**
	 * The register version moves up, because OpenRegister skips a register's
	 * own fields (its authorization among them) unless the incoming version is
	 * newer: without it an existing install would keep admins-only import.
	 *
	 * @return void
	 */
	public function testTheRegisterVersionMovesUpSoExistingInstallsTakeTheRule(): void {
		$before = (string)$this->monolith()['components']['registers']['larpinq']['version'];
		$after = (string)$this->mergedRegister()['components']['registers']['larpinq']['version'];

		$this->assertTrue(version_compare($after, $before, '>'), "register version {$after} must be above {$before}");
	}//end testTheRegisterVersionMovesUpSoExistingInstallsTakeTheRule()

	/**
	 * The fragment only adds the rule: the register keeps its slug and schemas.
	 *
	 * @return void
	 */
	public function testTheRestOfTheRegisterIsKept(): void {
		$register = $this->mergedRegister()['components']['registers']['larpinq'];

		$this->assertSame('larpinq', $register['slug']);
		foreach ($this->monolith()['components']['registers']['larpinq']['schemas'] as $schema) {
			$this->assertContains($schema, $register['schemas']);
		}
	}//end testTheRestOfTheRegisterIsKept()
}//end class
