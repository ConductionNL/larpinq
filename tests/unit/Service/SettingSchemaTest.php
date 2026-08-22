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

	protected function setUp(): void {
		parent::setUp();
		$path = __DIR__ . '/../../../lib/Settings/larpinq_register.json';
		$this->register = json_decode((string)file_get_contents($path), true);
	}

	public function testRegisterJsonIsWellFormed(): void {
		self::assertIsArray($this->register);
		self::assertArrayHasKey('components', $this->register);
		self::assertArrayHasKey('schemas', $this->register['components']);
	}

	public function testSettingSchemaIsCampaignEntityV2(): void {
		$setting = $this->register['components']['schemas']['setting'];
		self::assertSame('2.0.0', $setting['version']);
		self::assertSame('setting', $setting['slug']);

		$props = $setting['properties'];
		self::assertArrayHasKey('name', $props);
		self::assertArrayHasKey('description', $props);
		self::assertArrayHasKey('status', $props);

		// The vestigial key-value `value` property is gone.
		self::assertArrayNotHasKey('value', $props);

		// status is an enum active|archived defaulting to active.
		self::assertSame(['active', 'archived'], $props['status']['enum']);
		self::assertSame('active', $props['status']['default']);

		self::assertSame(['name'], $setting['required']);
	}

	public function testScopingPropertyAddedToGameEntities(): void {
		$schemas = $this->register['components']['schemas'];
		foreach (['character', 'event', 'skill', 'item', 'condition', 'ability', 'effect'] as $type) {
			self::assertArrayHasKey(
				'setting',
				$schemas[$type]['properties'],
				"Schema $type must carry the optional setting scoping property"
			);
			self::assertSame('uuid', $schemas[$type]['properties']['setting']['format']);
		}
	}

	public function testPlayerIsNotScoped(): void {
		// A player is a real person who spans campaigns — never setting-scoped.
		$player = $this->register['components']['schemas']['player'];
		self::assertArrayNotHasKey('setting', $player['properties']);
	}

	public function testSettingRegisteredInSchemaList(): void {
		$schemas = $this->register['components']['registers']['larpingapp']['schemas'];
		self::assertContains('setting', $schemas);
	}
}
