<?php

/**
 * Tests the unique-holder-bulk-writes register fragment.
 *
 * The character, item and condition schemas set `fireEventsOnBulkWrites`, so
 * OpenRegister runs the unique-holder veto on bulk saves and imports too, and
 * their versions and the register version move above every earlier one, so an
 * existing install takes the setting on its next import.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The bulk-write setting on the schemas the unique-holder veto guards.
 *
 * @covers \OCA\Larpinq\Service\ConfigFileLoaderService
 */
class UniqueHolderBulkWritesFragmentTest extends TestCase {

	/**
	 * The versions before this fragment, highest of the monolith and the other fragments.
	 *
	 * @var array<string,string>
	 */
	private const EARLIER = [
		'register' => '1.4.0',
		'character' => '1.5.0',
		'item' => '2.0.0',
		'condition' => '1.1.0',
	];

	/**
	 * The register with every fragment merged in.
	 *
	 * @return array<string,mixed> The merged register.
	 */
	private function mergedRegister(): array {
		$monolith = json_decode((string)file_get_contents(dirname(__DIR__, 3).'/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		// @var array<string, mixed> $merged
		$merged = $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, dirname(__DIR__, 3));
		return $merged;
	}//end mergedRegister()

	/**
	 * REQ-UHE-006: bulk writes of characters, items and conditions fire the save events.
	 *
	 * @return void
	 */
	public function testTheGuardedSchemasFireEventsOnBulkWrites(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];

		foreach (['character', 'item', 'condition'] as $schema) {
			$this->assertTrue($schemas[$schema]['configuration']['fireEventsOnBulkWrites'] ?? null, $schema);
		}
	}//end testTheGuardedSchemasFireEventsOnBulkWrites()

	/**
	 * The character schema keeps the configuration it had.
	 *
	 * @return void
	 */
	public function testTheCharacterConfigurationIsKept(): void {
		$configuration = $this->mergedRegister()['components']['schemas']['character']['configuration'];

		$this->assertSame(['photos'], $configuration['linkedTypes']);
		$this->assertSame('approved', $configuration['x-openregister-lifecycle']['field']);
	}//end testTheCharacterConfigurationIsKept()

	/**
	 * The schema and register versions move up, so an existing install imports the setting.
	 *
	 * @return void
	 */
	public function testTheVersionsMoveUp(): void {
		$merged = $this->mergedRegister()['components'];
		$after = [
			'register' => (string)$merged['registers']['larpinq']['version'],
			'character' => (string)$merged['schemas']['character']['version'],
			'item' => (string)$merged['schemas']['item']['version'],
			'condition' => (string)$merged['schemas']['condition']['version'],
		];

		foreach (self::EARLIER as $name => $before) {
			$this->assertTrue(version_compare($after[$name], $before, '>'), "{$name} version {$after[$name]} must be above {$before}");
		}
	}//end testTheVersionsMoveUp()
}//end class
