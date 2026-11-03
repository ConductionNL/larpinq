<?php

/**
 * Unit tests for the setting-management register schema shape.
 *
 * Asserts the v2 `setting` schema (campaign entity) and the optional `setting`
 * scoping property added to the game entities, directly off the shipped
 * register JSON — the source of truth OpenRegister imports.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the setting-management schema changes.
 */
class SettingSchemaTest extends TestCase {

	/**
	 * Decoded register JSON.
	 *
	 * @var array<string, mixed>
	 */
	private array $register;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$path = __DIR__ . '/../../../lib/Settings/larpinq_register.json';
		$this->register = json_decode((string)file_get_contents($path), true);
	}

	/**
	 * Register JSON is well formed.
	 *
	 * @return void
	 */
	public function testRegisterJsonIsWellFormed(): void {
		self::assertIsArray(actual: $this->register);
		self::assertArrayHasKey(key: 'components', array: $this->register);
		self::assertArrayHasKey(key: 'schemas', array: $this->register['components']);
	}

	/**
	 * Setting schema is campaign entity v 2.
	 *
	 * @return void
	 */
	public function testSettingSchemaIsCampaignEntityV2(): void {
		$setting = $this->register['components']['schemas']['setting'];
		self::assertSame(expected: '2.0.0', actual: $setting['version']);
		self::assertSame(expected: 'setting', actual: $setting['slug']);

		$props = $setting['properties'];
		self::assertArrayHasKey(key: 'name', array: $props);
		self::assertArrayHasKey(key: 'description', array: $props);
		self::assertArrayHasKey(key: 'status', array: $props);

		// The vestigial key-value `value` property is gone.
		self::assertArrayNotHasKey(key: 'value', array: $props);

		// The status is an enum active|archived defaulting to active.
		self::assertSame(expected: ['active', 'archived'], actual: $props['status']['enum']);
		self::assertSame(expected: 'active', actual: $props['status']['default']);

		self::assertSame(expected: ['name'], actual: $setting['required']);
	}

	/**
	 * Scoping property added to game entities.
	 *
	 * @return void
	 */
	public function testScopingPropertyAddedToGameEntities(): void {
		$schemas = $this->register['components']['schemas'];
		foreach (['character', 'event', 'skill', 'item', 'condition', 'ability', 'effect'] as $type) {
			self::assertArrayHasKey(
				key: 'setting',
				array: $schemas[$type]['properties'],
				message: "Schema $type must carry the optional setting scoping property"
			);
			self::assertSame(expected: 'uuid', actual: $schemas[$type]['properties']['setting']['format']);
		}
	}

	/**
	 * Player is not scoped.
	 *
	 * @return void
	 */
	public function testPlayerIsNotScoped(): void {
		// A player is a real person who spans campaigns — never setting-scoped.
		$player = $this->register['components']['schemas']['player'];
		self::assertArrayNotHasKey(key: 'setting', array: $player['properties']);
	}

	/**
	 * Setting registered in schema list.
	 *
	 * @return void
	 */
	public function testSettingRegisteredInSchemaList(): void {
		$schemas = $this->register['components']['registers']['larpinq']['schemas'];
		self::assertContains(needle: 'setting', haystack: $schemas);
	}
}
