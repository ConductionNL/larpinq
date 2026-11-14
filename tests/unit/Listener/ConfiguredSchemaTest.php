<?php

/**
 * ConfiguredSchema matches an entity against the schema id stored under an app config key.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\ConfiguredSchema;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Larpinq\Listener\ConfiguredSchema
 */
class ConfiguredSchemaTest extends TestCase {

	/**
	 * The check over a config that stores $schema under player_schema.
	 *
	 * @param string $schema The stored schema id.
	 *
	 * @return ConfiguredSchema
	 */
	private function check(string $schema): ConfiguredSchema {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->expects($this->once())->method('getValueString')
			->with('larpinq', 'player_schema', '')
			->willReturn($schema);

		return new ConfiguredSchema(config: $config, key: 'player_schema');
	}

	/**
	 * An entity with a schema accessor.
	 *
	 * @param mixed $schema The schema it reports.
	 *
	 * @return object
	 */
	private function entity(mixed $schema): object {
		return new class ($schema) {
			/**
			 * Build it.
			 *
			 * @param mixed $schema The schema.
			 */
			public function __construct(private mixed $schema) {
			}

			/**
			 * The schema.
			 *
			 * @return mixed
			 */
			public function getSchema(): mixed {
				return $this->schema;
			}
		};
	}

	/**
	 * The same schema id matches, also when the entity holds it as an int.
	 *
	 * @return void
	 */
	public function testTheConfiguredSchemaMatches(): void {
		$this->assertTrue(condition: $this->check(schema: '42')->matches(entity: $this->entity(schema: '42')));
		$this->assertTrue(condition: $this->check(schema: '42')->matches(entity: $this->entity(schema: 42)));
	}

	/**
	 * Another schema does not match.
	 *
	 * @return void
	 */
	public function testAnotherSchemaDoesNotMatch(): void {
		$this->assertFalse(condition: $this->check(schema: '42')->matches(entity: $this->entity(schema: '43')));
	}

	/**
	 * Nothing configured matches nothing, not even an entity without a schema.
	 *
	 * @return void
	 */
	public function testAnEmptyConfigMatchesNothing(): void {
		$this->assertFalse(condition: $this->check(schema: '')->matches(entity: $this->entity(schema: '')));
		$this->assertFalse(condition: $this->check(schema: '')->matches(entity: $this->entity(schema: null)));
	}

	/**
	 * An object without getSchema() never matches.
	 *
	 * @return void
	 */
	public function testAnObjectWithoutASchemaNeverMatches(): void {
		$this->assertFalse(condition: $this->check(schema: '42')->matches(entity: new \stdClass()));
	}
}
