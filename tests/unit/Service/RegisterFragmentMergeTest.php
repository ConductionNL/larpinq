<?php

/**
 * Unit tests for the ADR-037 register fragment deep-merge.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests the private static deepMergeConfig() that powers ADR-037 modular
 * register fragments. Disjoint fragments must union by key, lists concatenate,
 * and scalars overwrite — the OpenAPI assembly semantics that make concurrent
 * same-app builds conflict-free.
 */
class RegisterFragmentMergeTest extends TestCase {
	/**
	 * Invoke the private static ConfigFileLoaderService::deepMergeConfig().
	 *
	 * @param array<mixed> $base The base array.
	 * @param array<mixed> $overlay The overlay array.
	 *
	 * @return array<mixed> The merged result.
	 */
	private function deepMerge(array $base, array $overlay): array {
		$method = new ReflectionMethod(ConfigFileLoaderService::class, 'deepMergeConfig');
		$method->setAccessible(true);

		// @var array<mixed> $result
		$result = $method->invoke(null, $base, $overlay);
		return $result;
	}//end deepMerge()

	/**
	 * Disjoint schema keys union into a single map.
	 *
	 * @return void
	 */
	public function testDisjointSchemasUnionByKey(): void {
		$base = ['components' => ['schemas' => ['Character' => ['type' => 'object']]]];
		$overlay = ['components' => ['schemas' => ['Quest' => ['type' => 'object']]]];

		$merged = $this->deepMerge(base: $base, overlay: $overlay);

		$this->assertArrayHasKey(key: 'Character', array: $merged['components']['schemas']);
		$this->assertArrayHasKey(key: 'Quest', array: $merged['components']['schemas']);
		$this->assertSame(expected: ['type' => 'object'], actual: $merged['components']['schemas']['Character']);
		$this->assertSame(expected: ['type' => 'object'], actual: $merged['components']['schemas']['Quest']);

	}//end testDisjointSchemasUnionByKey()

	/**
	 * List arrays concatenate rather than overwrite.
	 *
	 * @return void
	 */
	public function testListArraysConcatenate(): void {
		$base = ['required' => ['name']];
		$overlay = ['required' => ['level']];

		$merged = $this->deepMerge(base: $base, overlay: $overlay);

		$this->assertSame(expected: ['name', 'level'], actual: $merged['required']);

	}//end testListArraysConcatenate()

	/**
	 * Scalars in the overlay overwrite the base.
	 *
	 * @return void
	 */
	public function testScalarOverlayOverwrites(): void {
		$base = ['info' => ['version' => '1.0.0', 'title' => 'Larpinq']];
		$overlay = ['info' => ['version' => '1.1.0']];

		$merged = $this->deepMerge(base: $base, overlay: $overlay);

		$this->assertSame(expected: '1.1.0', actual: $merged['info']['version']);
		$this->assertSame(expected: 'Larpinq', actual: $merged['info']['title']);

	}//end testScalarOverlayOverwrites()

	/**
	 * Nested objects merge recursively, preserving sibling keys at every depth.
	 *
	 * @return void
	 */
	public function testNestedMergePreservesSiblings(): void {
		$base = [
			'components' => [
				'schemas' => ['Character' => ['type' => 'object']],
				'paths' => ['/characters' => ['get' => []]],
			],
		];
		$overlay = [
			'components' => [
				'schemas' => ['Item' => ['type' => 'object']],
			],
		];

		$merged = $this->deepMerge(base: $base, overlay: $overlay);

		$this->assertArrayHasKey(key: 'Character', array: $merged['components']['schemas']);
		$this->assertArrayHasKey(key: 'Item', array: $merged['components']['schemas']);
		$this->assertArrayHasKey(key: '/characters', array: $merged['components']['paths']);

	}//end testNestedMergePreservesSiblings()

	/**
	 * The event-checkin-roster fragment adds the larping_attendance schema
	 * over the real monolith without removing any existing schema or property.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	public function testEventCheckinRosterFragmentAddsAttendanceSchema(): void {
		$baseDir = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($baseDir . '/lib/Settings/larpinq_register.json'), true);
		$fragment = json_decode((string)file_get_contents($baseDir . '/lib/Settings/register.d/event-checkin-roster.json'), true);

		$this->assertIsArray(actual: $monolith, message: 'monolith register JSON must parse');
		$this->assertIsArray(actual: $fragment, message: 'fragment JSON must parse');

		$existingSchemaNames = array_keys($monolith['components']['schemas']);

		$merged = $this->deepMerge(base: $monolith, overlay: $fragment);
		$mergedNames = array_keys($merged['components']['schemas']);

		// No existing schema is removed by the merge.
		foreach ($existingSchemaNames as $name) {
			$this->assertContains(needle: $name, haystack: $mergedNames, message: "existing schema {$name} must survive the merge");
		}

		// The attendance schema is added with the namespaced slug.
		$this->assertArrayHasKey(key: 'attendance', array: $merged['components']['schemas']);
		$attendance = $merged['components']['schemas']['attendance'];
		$this->assertSame(expected: 'larping_attendance', actual: $attendance['slug']);

		// All five properties are present, each with a non-empty title (gate-28).
		foreach (['event', 'character', 'status', 'checkedInAt', 'checkedInBy'] as $prop) {
			$this->assertArrayHasKey(key: $prop, array: $attendance['properties'], message: "property {$prop} present");
			$this->assertNotEmpty(actual: $attendance['properties'][$prop]['title'], message: "property {$prop} has a title");
		}

		// Status is constrained to the three attendance states.
		$this->assertSame(
			expected: ['registered', 'checked-in', 'no-show'],
			actual: $attendance['properties']['status']['enum']
		);

		// Write RBAC is delegated to OpenRegister on the schema: game masters,
		// and stewards at the gate (admin-staff-roles-and-invites D3).
		$this->assertSame(expected: ['gamemasters', 'larpinq-stewards'], actual: $attendance['authorization']['create']);
		$this->assertSame(expected: ['gamemasters'], actual: $attendance['authorization']['delete']);

		// A representative existing schema keeps all its properties.
		$this->assertArrayHasKey(
			key: 'awardedBy',
			array: $merged['components']['schemas']['xpAward']['properties'],
			message: 'xpAward.awardedBy must survive the merge'
		);

	}//end testEventCheckinRosterFragmentAddsAttendanceSchema()
}//end class
