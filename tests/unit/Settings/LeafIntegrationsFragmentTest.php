<?php

/**
 * The talk, polls and deck leaves on the merged register (leaf-integrations).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/leaf-integrations/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-001 to REQ-003 of leaf-integrations.
 */
class LeafIntegrationsFragmentTest extends TestCase {

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
	 * The linked types one schema carries after the merge.
	 *
	 * @param string $schema The schema key.
	 *
	 * @return array<int, string> The linked types.
	 */
	private function linkedTypes(string $schema): array {
		return (array)($this->mergedRegister()['components']['schemas'][$schema]['configuration']['linkedTypes'] ?? []);
	}//end linkedTypes()

	/**
	 * An event carries all six leaves, each once.
	 *
	 * @return void
	 */
	public function testEventCarriesAllSixLeaves(): void {
		$types = $this->linkedTypes(schema: 'event');

		foreach (['calendar', 'maps', 'forms', 'talk', 'polls', 'deck'] as $leaf) {
			$this->assertContains($leaf, $types, "event must link {$leaf}");
		}

		$this->assertSame(count($types), count(array_unique($types)), 'no leaf may be declared twice');
	}//end testEventCarriesAllSixLeaves()

	/**
	 * A setting carries talk and polls, and no deck.
	 *
	 * @return void
	 */
	public function testSettingCarriesTalkAndPollsButNoDeck(): void {
		$types = $this->linkedTypes(schema: 'setting');

		$this->assertContains('talk', $types);
		$this->assertContains('polls', $types);
		$this->assertNotContains('deck', $types);
	}//end testSettingCarriesTalkAndPollsButNoDeck()

	/**
	 * The declarations come from their own fragment, which adds nothing else.
	 *
	 * @return void
	 */
	public function testTheLeavesComeFromTheirOwnFragment(): void {
		$path = dirname(__DIR__, 3) . '/lib/Settings/register.d/leaf-integrations.json';
		$this->assertFileExists($path);

		$fragment = json_decode((string)file_get_contents($path), true);
		// The whole fragment, so it cannot grow a property, a hook or a write
		// path: the leaves link to the objects and change nothing on them (REQ-005).
		$this->assertSame(
			[
				'components' => [
					'schemas' => [
						'event'   => ['configuration' => ['linkedTypes' => ['talk', 'polls', 'deck']]],
						'setting' => ['configuration' => ['linkedTypes' => ['talk', 'polls']]],
					],
				],
			],
			$fragment
		);
	}//end testTheLeavesComeFromTheirOwnFragment()
}//end class
