<?php

/**
 * Unit tests for SettingsLoadService.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
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
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Importing the register into OpenRegister and recording the schema ids it got.
 *
 * @covers \OCA\Larpinq\Service\SettingsLoadService
 * @uses   \OCA\Larpinq\Service\SettingsMapBuilder
 */
class SettingsLoadServiceTest extends TestCase {

	/**
	 * Every setValueString() call, as "key=value".
	 *
	 * @var list<string>
	 */
	private array $written = [];

	/**
	 * The fake OpenRegister ConfigurationService.
	 *
	 * @var object
	 */
	private object $configurationService;

	/**
	 * Build the service.
	 *
	 * @param array<string, mixed> $importResult       What importFromApp() returns
	 * @param string               $fragmentSignature The register.d signature
	 * @param list<string>         $installedApps     Installed apps
	 *
	 * @return SettingsLoadService
	 */
	private function service(array $importResult, string $fragmentSignature = '', array $installedApps = ['openregister']): SettingsLoadService {
		$this->written = [];
		$written = &$this->written;

		$this->configurationService = new class ($importResult) {
			/**
			 * The arguments of each import.
			 *
			 * @var list<array<string, mixed>>
			 */
			public array $imports = [];

			public function __construct(private readonly array $result) {
			}

			public function importFromApp(string $appId, array $data, string $version, bool $force): array {
				$this->imports[] = ['appId' => $appId, 'data' => $data, 'version' => $version, 'force' => $force];
				return $this->result;
			}
		};

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('setValueString')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$written): bool {
				$written[] = "$app:$key=$value";
				return true;
			}
		);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getInstalledApps')->willReturn($installedApps);
		$appManager->method('getAppVersion')->with('larpinq')->willReturn('2.4.0');

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with('OCA\OpenRegister\Service\ConfigurationService')->willReturn($this->configurationService);

		$fileLoader = $this->createMock(ConfigFileLoaderService::class);
		$fileLoader->method('loadConfigurationFile')->willReturn(['openapi' => '3.0.0']);
		$fileLoader->method('ensureSourceType')->willReturnCallback(
			static fn (array $data): array => $data + ['x-source' => 'app']
		);
		$fileLoader->method('getFragmentSignature')->willReturn($fragmentSignature);

		return new SettingsLoadService(
			appConfig: $appConfig,
			appManager: $appManager,
			container: $container,
			mapBuilder: new SettingsMapBuilder(),
			fileLoader: $fileLoader,
		);

	}//end service()

	/**
	 * A load imports the prepared file under the app version, without forcing.
	 *
	 * @return void
	 */
	public function testALoadImportsThePreparedFileWithoutForcing(): void {
		$result = ['schemas' => [], 'registers' => []];

		self::assertSame($result, $this->service($result)->loadSettings());
		self::assertSame(
			[['appId' => 'larpinq', 'data' => ['openapi' => '3.0.0', 'x-source' => 'app'], 'version' => '2.4.0', 'force' => false]],
			$this->configurationService->imports
		);
		self::assertSame([], $this->written);

	}//end testALoadImportsThePreparedFileWithoutForcing()

	/**
	 * A reload forces, and a fragment signature is folded into the version.
	 *
	 * @return void
	 */
	public function testAReloadForcesAndFoldsTheFragmentSignatureIntoTheVersion(): void {
		$this->service(['schemas' => []], 'abc123')->reloadSettings();

		self::assertCount(1, $this->configurationService->imports);
		self::assertTrue($this->configurationService->imports[0]['force']);
		self::assertSame('2.4.0+frag.abc123', $this->configurationService->imports[0]['version']);

	}//end testAReloadForcesAndFoldsTheFragmentSignatureIntoTheVersion()

	/**
	 * Without OpenRegister installed nothing is imported.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterTheLoadFails(): void {
		$service = $this->service(['schemas' => []], '', ['files', 'larpinq']);

		try {
			$service->loadSettings();
			self::fail('The load ran without OpenRegister.');
		} catch (RuntimeException $e) {
			self::assertSame('Configuration service is not available.', $e->getMessage());
		}

		self::assertSame([], $this->configurationService->imports);
		self::assertSame([], $this->written);

	}//end testWithoutOpenRegisterTheLoadFails()

	/**
	 * The register id and each known schema id are recorded per object type.
	 *
	 * @return void
	 */
	public function testTheRegisterAndKnownSchemasAreRecorded(): void {
		$this->service(
			[
				'registers' => [['slug' => 'other', 'id' => 1], ['slug' => 'larpinq', 'id' => 7]],
				'schemas' => [
					['slug' => 'character', 'id' => 11],
					['slug' => 'larping_skill', 'uuid' => 'skill-uuid'],
					['slug' => 'not_an_object_type', 'id' => 99],
				],
			]
		)->loadSettings();

		self::assertSame(
			[
				'larpinq:register=7',
				'larpinq:character_schema=11',
				'larpinq:character_source=openregister',
				'larpinq:character_register=7',
				'larpinq:skill_schema=skill-uuid',
				'larpinq:skill_source=openregister',
				'larpinq:skill_register=7',
			],
			$this->written
		);

	}//end testTheRegisterAndKnownSchemasAreRecorded()

	/**
	 * Without this app's register the schemas are recorded without a register.
	 *
	 * @return void
	 */
	public function testWithoutTheRegisterSchemasAreRecordedAlone(): void {
		$this->service(
			[
				'registers' => [['slug' => 'other', 'id' => 1]],
				'schemas' => [['slug' => 'xpAward', 'id' => 5], ['slug' => 'larping_access_code', 'id' => 6]],
			]
		)->reloadSettings();

		self::assertSame(
			[
				'larpinq:xpaward_schema=5',
				'larpinq:xpaward_source=openregister',
				'larpinq:accesscode_schema=6',
				'larpinq:accesscode_source=openregister',
			],
			$this->written
		);

	}//end testWithoutTheRegisterSchemasAreRecordedAlone()

	/**
	 * A schema stored without an id is not recorded.
	 *
	 * @return void
	 */
	public function testASchemaWithoutAnIdIsNotRecorded(): void {
		$this->service(['schemas' => [['slug' => 'player']]])->loadSettings();

		self::assertSame([], $this->written);

	}//end testASchemaWithoutAnIdIsNotRecorded()
}//end class
