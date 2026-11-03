<?php

/**
 * DemoDataServiceTest.
 *
 * The failure this service must never have is the quiet one: reporting that
 * demo data was installed on an instance where nothing was written. So the
 * assertions here are about what it refuses to do — skip on a version gate,
 * swallow a missing descriptor, or claim success without OpenRegister.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\DemoDataService;
use OCP\App\IAppManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Covers the demo import's decision table.
 */
class DemoDataServiceTest extends TestCase {
	private IAppManager&MockObject $appManager;

	private ContainerInterface&MockObject $container;

	private DemoDataService $service;

	private string $appDir;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->appDir = sys_get_temp_dir() . '/larpinq-demo-' . uniqid();
		mkdir($this->appDir . '/lib/Settings', 0777, true);

		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->container  = $this->createMock(originalClassName: ContainerInterface::class);

		$this->appManager->method('getAppPath')->willReturn($this->appDir);
		$this->appManager->method('getAppVersion')->willReturn('1.2.3');
		$this->appManager->method('getInstalledApps')->willReturn(['larpinq', 'openregister']);

		$this->service = new DemoDataService(
			appManager: $this->appManager,
			container: $this->container,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}

	/**
	 * Undo the global state a test changed.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$file = $this->appDir . '/lib/Settings/larpinq_mock_register.json';
		if (is_file($file) === true) {
			unlink($file);
		}
		@rmdir($this->appDir . '/lib/Settings');
		@rmdir($this->appDir . '/lib');
		@rmdir($this->appDir);
	}

	private function shipDescriptor(int $objects = 2): void {
		file_put_contents(
			$this->appDir . '/lib/Settings/larpinq_mock_register.json',
			json_encode(
				[
					'x-openregister' => ['type' => 'mock', 'app' => 'larpinq'],
					'components' => [
						'registers' => ['larpinq' => ['schemas' => ['Thing']]],
						'schemas' => ['Thing' => ['type' => 'object']],
						'objects' => array_fill(0, $objects, ['@self' => ['register' => 'larpinq', 'schema' => 'Thing']]),
					],
				]
			)
		);
	}

	/**
	 * A stand-in for OpenRegister's importer that records how it was called.
	 *
	 * @return object The fake.
	 */
	private function importerSpy(): object {
		return new class {
			/** @var array<string, mixed> */
			public array $seen = [];

			/**
			 * @param string               $appId   Config identity.
			 * @param array<string, mixed> $data    Descriptor.
			 * @param string               $version App version.
			 * @param boolean              $force   Whether the version gate is bypassed.
			 *
			 * @return array<string, mixed>
			 */
			public function importFromApp(string $appId, array $data, string $version, bool $force): array {
				$this->seen = ['appId' => $appId, 'version' => $version, 'force' => $force];
				return ['registers' => ['larpinq'], 'schemas' => ['Thing']];
			}
		};
	}

	/**
	 * Declining is offered even when no dataset ships.
	 *
	 * 🔴 "NO THANKS" HAS TO BE SAYABLE. Every app in this fleet implemented a
	 * `skip-demo-data` action that no manifest step could reach, so the step
	 * stayed outstanding and CnAppRoot reopened the wizard over every page
	 * unless the operator imported data they did not want.
	 *
	 * @return void
	 */
	public function testDecliningIsOfferedEvenWhenNoDatasetShips(): void {
		$choices = $this->service->listChoices();

		$this->assertSame(expected: ['none'], actual: array_column($choices, 'id'));
		$this->assertNotSame(expected: '', actual: $choices[0]['description']);
		$this->assertNotSame(expected: '', actual: $choices[0]['icon']);

	}//end testDecliningIsOfferedEvenWhenNoDatasetShips()

	/**
	 * The shipped dataset is offered with the count it actually carries.
	 *
	 * The card promises a number, so the number has to come from the file that
	 * will be imported rather than from a manifest that could disagree with it.
	 *
	 * @return void
	 */
	public function testTheShippedDatasetIsOfferedWithItsRealCount(): void {
		$this->shipDescriptor(objects: 3);

		$choices = $this->service->listChoices();

		$this->assertSame(expected: ['none', 'demo'], actual: array_column($choices, 'id'));
		$this->assertSame(expected: 3, actual: $choices[1]['objectCount']);
		// 🔴 NO NUMBER IN THE SENTENCE. The wizard translates a card's
		// description by literal lookup, so an interpolated count would leave a
		// Dutch operator reading English.
		$this->assertDoesNotMatchRegularExpression(pattern: '/\d/', string: $choices[1]['description']);

	}//end testTheShippedDatasetIsOfferedWithItsRealCount()

	/**
	 * It imports the descriptor and reports the counts.
	 *
	 * @return void
	 */
	public function testItImportsTheDescriptorAndReportsTheCounts(): void {
		$this->shipDescriptor(objects: 5);
		$spy = $this->importerSpy();
		$this->container->method('get')->willReturn($spy);

		$result = $this->service->install();

		$this->assertSame(expected: 5, actual: $result['objects']);
		$this->assertSame(expected: 1, actual: $result['registers']);
		$this->assertSame(expected: 1, actual: $result['schemas']);
	}

	/**
	 * 🔴 THE IMPORT IS FORCED. OpenRegister version-gates a non-forced import
	 * and SKIPS silently when the version has not moved. An operator who asks
	 * for demo data and is told it worked, on an instance where nothing was
	 * written, has been lied to by a version compare.
	 */
	public function testTheImportIsForcedSoAVersionGateCannotSilentlySkipIt(): void {
		$this->shipDescriptor();
		$spy = $this->importerSpy();
		$this->container->method('get')->willReturn($spy);

		$this->service->install();

		$this->assertTrue(condition: $spy->seen['force'], message: 'a version gate must not be able to skip an explicit request');
	}

	/**
	 * Its own configuration identity, so the demo import and the real
	 * configuration import cannot mask one another's version gate.
	 */
	public function testItImportsUnderItsOwnConfigurationIdentity(): void {
		$this->shipDescriptor();
		$spy = $this->importerSpy();
		$this->container->method('get')->willReturn($spy);

		$this->service->install();

		$this->assertSame(expected: 'larpinq.demo', actual: $spy->seen['appId']);
	}

	/**
	 * A missing descriptor throws rather than reporting success.
	 *
	 * @return void
	 */
	public function testAMissingDescriptorThrowsRatherThanReportingSuccess(): void {
		$this->container->expects($this->never())->method('get');

		$this->expectException(exception: RuntimeException::class);
		$this->service->install();
	}

	/**
	 * Unparsable JSON throws rather than importing nothing.
	 *
	 * @return void
	 */
	public function testUnparsableJsonThrowsRatherThanImportingNothing(): void {
		file_put_contents($this->appDir . '/lib/Settings/larpinq_mock_register.json', 'not json');
		$this->container->expects($this->never())->method('get');

		$this->expectException(exception: RuntimeException::class);
		$this->service->install();
	}

	/**
	 * 🔴 NAMES THE MISSING APP. "Something went wrong" on a cross-app lookup
	 * leaves an operator with nothing to act on; a cross-app class is a runtime
	 * lookup that finds nobody rather than erroring usefully.
	 */
	public function testWithoutOpenRegisterItRefusesAndSaysWhichAppIsMissing(): void {
		$this->shipDescriptor();
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$appManager->method('getAppVersion')->willReturn('1.2.3');
		$appManager->method('getInstalledApps')->willReturn(['larpinq']);

		$service = new DemoDataService(
			appManager: $appManager,
			container: $this->container,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionMessageMatches(regularExpression: '/OpenRegister/');
		$service->install();
	}

	/**
	 * Is available reflects whether the descriptor ships.
	 *
	 * @return void
	 */
	public function testIsAvailableReflectsWhetherTheDescriptorShips(): void {
		$this->assertFalse(condition: $this->service->isAvailable(), message: 'no descriptor on disk');
		$this->shipDescriptor();
		$this->assertTrue(condition: $this->service->isAvailable());
	}
}
