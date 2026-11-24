<?php
/**
 * The historyEntry schema on the merged register (capability-rows-missing-requirements).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes, and
 * validates entries against the merged schema with Opis.
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
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-SMD-002: a world keeps a timeline of dated history entries.
 */
class WorldTimelineFragmentTest extends TestCase {
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
	 * The merged historyEntry schema.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function historyEntry(): array {
		$schemas = $this->mergedRegister()['components']['schemas'];
		$this->assertArrayHasKey(key: 'historyEntry', array: $schemas, message: 'the historyEntry schema must be in the merged register');
		return $schemas['historyEntry'];
	}//end historyEntry()

	/**
	 * The schema is listed with the fields the timeline sorts and shows.
	 *
	 * @return void
	 */
	public function testTheSchemaIsInTheRegister(): void {
		$register = $this->mergedRegister()['components']['registers']['larpinq'];
		$schema = $this->historyEntry();

		$this->assertSame(expected: 'larping_history_entry', actual: $schema['slug']);
		$this->assertContains(needle: 'larping_history_entry', haystack: $register['schemas']);
		$this->assertContains(needle: 'larping_lore_page', haystack: $register['schemas'], message: 'the fragment must add to the list, not replace it');
		$this->assertSame(expected: ['setting', 'date', 'title', 'visibility'], actual: $schema['required']);
		$this->assertSame(expected: 'setting', actual: $schema['properties']['setting']['$ref']);
		$this->assertSame(expected: 'date', actual: $schema['properties']['date']['format']);
		$this->assertSame(expected: 'markdown', actual: $schema['properties']['text']['format']);
		$this->assertSame(expected: 'gamemasters', actual: $schema['properties']['visibility']['default']);
		$this->assertTrue(condition: $schema['configuration']['exportable'], message: 'every larpinq schema exports (admin-import-export)');
	}//end testTheSchemaIsInTheRegister()

	/**
	 * Players read only entries meant for players; only game masters write.
	 *
	 * @return void
	 */
	public function testAHiddenEntryStaysHidden(): void {
		$authorization = $this->historyEntry()['authorization'];

		$this->assertSame(
			expected: [
				['group' => 'gamemasters'],
				['group' => 'larpers', 'match' => ['visibility' => 'players']],
			],
			actual: $authorization['read']
		);
		foreach (['create', 'update', 'delete'] as $action) {
			$this->assertSame(expected: ['gamemasters'], actual: $authorization[$action], message: "only game masters may {$action}");
		}
	}//end testAHiddenEntryStaysHidden()

	/**
	 * The founding of Aldmoor validates; an entry without a date does not.
	 *
	 * @return void
	 */
	public function testAnEntryNeedsADate(): void {
		$schema = $this->historyEntry();
		foreach ($schema['properties'] as $name => $property) {
			unset($schema['properties'][$name]['$ref']);
		}

		unset($schema['authorization'], $schema['configuration']);
		$opis = json_decode((string)json_encode($schema));
		$validator = new Validator();

		$founding = [
			'setting' => '00000000-0000-4000-8000-000000000000',
			'date' => '1712-04-01',
			'dateLabel' => 'Year 1 of the Crown',
			'title' => 'The founding of Aldmoor',
			'visibility' => 'players',
		];
		$this->assertTrue(condition: $validator->validate(json_decode((string)json_encode($founding)), $opis)->isValid());

		unset($founding['date']);
		$this->assertFalse(condition: $validator->validate(json_decode((string)json_encode($founding)), $opis)->isValid(), message: 'an entry without a date cannot be sorted');
	}//end testAnEntryNeedsADate()
}//end class
