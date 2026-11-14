<?php

/**
 * The event's world is shown and set on the event pages
 * (events-world-scope-and-upcoming, REQ-EWU-001).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/setting-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The merged register shows the event's world.
 */
class EventsWorldScopeFragmentTest extends TestCase {

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
	 * The event's world is visible, so the event form, the event page and the
	 * Events list show it, and it still points at a world.
	 *
	 * @return void
	 */
	public function testTheEventWorldIsVisible(): void {
		$setting = $this->mergedRegister()['components']['schemas']['event']['properties']['setting'];

		$this->assertTrue(condition: $setting['visible'] ?? null, message: 'event.setting must be visible');
		$this->assertSame(expected: 'World', actual: $setting['title'] ?? null);
		$this->assertSame(expected: 'setting', actual: $setting['$ref'] ?? null);
		$this->assertSame(expected: 'uuid', actual: $setting['format'] ?? null);
	}//end testTheEventWorldIsVisible()
}//end class
