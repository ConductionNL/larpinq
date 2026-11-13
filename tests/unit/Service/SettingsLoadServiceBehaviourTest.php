<?php

/**
 * Unit tests for SettingsLoadService: importing the register and recording the schema and register ids.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\SettingsLoadService;
use OCA\Larpinq\Service\SettingsMapBuilder;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * @covers \OCA\Larpinq\Service\SettingsLoadService
 * @uses   \OCA\Larpinq\Service\SettingsMapBuilder
 */
class SettingsLoadServiceBehaviourTest extends TestCase {

	/**
	 * Every app config write, as [key, value].
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	private array $written = [];

	/**
	 * The import calls, as their named arguments.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $imports = [];

	/**
	 * The service over a configuration service that answers $result.
	 *
	 * @param array<string, mixed> $result      The import result.
	 * @param string               $signature   The fragment signature.
	 * @param array<int, string>   $installed   The installed apps.
	 *
	 * @return SettingsLoadService
	 */
	private function service(array $result, string $signature = '', array $installed = ['openregister']): SettingsLoadService {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->assertSame(expected: 'larpinq', actual: $app);
				$this->written[] = [$key, $value];
				return true;
			}
		);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getInstalledApps')->willReturn($installed);
		$apps->method('getAppVersion')->willReturn('1.4.0');

		$imports       = &$this->imports;
		$configuration = new class($result, $imports) {
			/**
			 * Constructor.
			 *
			 * @param array<string, mixed>              $result  The answer.
			 * @param array<int, array<string, mixed>> $imports Where the calls go.
			 */
			public function __construct(private array $result, private array &$imports) {
			}

			/**
			 * Record an import and answer.
			 *
			 * @param string               $appId   The app.
			 * @param array<string, mixed> $data    The configuration.
			 * @param string               $version The version.
			 * @param bool                 $force   Whether to force.
			 *
			 * @return array<string, mixed>
			 */
			public function importFromApp(string $appId, array $data, string $version, bool $force): array {
				$this->imports[] = ['appId' => $appId, 'data' => $data, 'version' => $version, 'force' => $force];
				return $this->result;
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->with('OCA\OpenRegister\Service\ConfigurationService')->willReturn($configuration);

		$files = $this->createMock(originalClassName: ConfigFileLoaderService::class);
		$files->method('loadConfigurationFile')->willReturn(['components' => []]);
		$files->method('ensureSourceType')->willReturnCallback(static fn (array $data): array => $data + ['sourceType' => 'local']);
		$files->method('getFragmentSignature')->willReturn($signature);

		return new SettingsLoadService(
			appConfig: $config,
			appManager: $apps,
			container: $container,
			mapBuilder: new SettingsMapBuilder(),
			fileLoader: $files,
		);
	}//end service()

	/**
	 * Loading imports the configuration without forcing, at the app version, and records
	 * the register and each known schema with its source and register.
	 *
	 * @return void
	 */
	public function testLoadingRecordsTheRegisterAndKnownSchemas(): void {
		$result = [
			'registers' => [['slug' => 'other', 'id' => 1], ['slug' => 'larpinq', 'id' => 7]],
			'schemas' => [['slug' => 'character', 'id' => 11], ['slug' => 'larping_event', 'id' => 12], ['slug' => 'unknown', 'id' => 99]],
		];

		$this->assertSame(expected: $result, actual: $this->service(result: $result)->loadSettings());

		$this->assertSame(
			expected: [['appId' => 'larpinq', 'data' => ['components' => [], 'sourceType' => 'local'], 'version' => '1.4.0', 'force' => false]],
			actual: $this->imports
		);
		$this->assertSame(
			expected: [
				['register', '7'],
				['character_schema', '11'],
				['character_source', 'openregister'],
				['character_register', '7'],
				['event_schema', '12'],
				['event_source', 'openregister'],
				['event_register', '7'],
			],
			actual: $this->written
		);
	}//end testLoadingRecordsTheRegisterAndKnownSchemas()

	/**
	 * Reloading forces the import, and the fragment signature joins the version.
	 *
	 * @return void
	 */
	public function testReloadingForcesWithTheFragmentSignature(): void {
		$this->service(result: [], signature: 'abc123')->reloadSettings();

		$this->assertTrue(condition: $this->imports[0]['force']);
		$this->assertSame(expected: '1.4.0+frag.abc123', actual: $this->imports[0]['version']);
		$this->assertSame(expected: [], actual: $this->written);
	}//end testReloadingForcesWithTheFragmentSignature()

	/**
	 * Without the register, schemas are recorded with no register id.
	 *
	 * @return void
	 */
	public function testWithoutTheRegisterSchemasAreRecordedWithoutOne(): void {
		$this->service(result: ['schemas' => [['slug' => 'player', 'uuid' => 'p-uuid']]])->loadSettings();

		$this->assertSame(expected: [['player_schema', 'p-uuid'], ['player_source', 'openregister']], actual: $this->written);
	}//end testWithoutTheRegisterSchemasAreRecordedWithoutOne()

	/**
	 * Without OpenRegister installed nothing is imported or written.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterNothingIsImported(): void {
		$service = $this->service(result: [], installed: ['files']);

		try {
			$service->loadSettings();
			$this->fail(message: 'Loading must be refused without OpenRegister.');
		} catch (RuntimeException $exception) {
			$this->assertSame(expected: 'Configuration service is not available.', actual: $exception->getMessage());
		}

		$this->assertSame(expected: [], actual: $this->imports);
		$this->assertSame(expected: [], actual: $this->written);
	}//end testWithoutOpenRegisterNothingIsImported()
}//end class
