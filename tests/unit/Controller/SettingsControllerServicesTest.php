<?php

/**
 * Unit tests for SettingsController's OpenRegister service lookups and the re-import.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\SettingsController;
use OCA\Larpinq\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\Larpinq\Controller\SettingsController
 * @uses   \OCA\Larpinq\Controller\CallerAccess
 */
class SettingsControllerServicesTest extends TestCase {

	/**
	 * The settings service.
	 *
	 * @var SettingsService&MockObject
	 */
	private SettingsService&MockObject $settings;

	/**
	 * Build the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->settings = $this->createMock(originalClassName: SettingsService::class);
	}//end setUp()

	/**
	 * A controller on an instance with the given apps and container.
	 *
	 * @param array<int, string>      $apps      The installed apps.
	 * @param ContainerInterface|null $container The container.
	 *
	 * @return SettingsController
	 */
	private function controller(array $apps, ?ContainerInterface $container = null): SettingsController {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getInstalledApps')->willReturn($apps);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn(null);

		return new SettingsController(
			request: $this->createMock(originalClassName: IRequest::class),
			container: ($container ?? $this->createMock(originalClassName: ContainerInterface::class)),
			appManager: $appManager,
			settingsService: $this->settings,
			callerAccess: new CallerAccess(userSession: $session, groupManager: $this->createMock(originalClassName: IGroupManager::class)),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);
	}//end controller()

	/**
	 * With OpenRegister installed, its object and configuration services come from the container.
	 *
	 * @return void
	 */
	public function testTheServicesComeFromTheContainerWithOpenRegister(): void {
		$objects       = new \stdClass();
		$configuration = new \stdClass();
		$container     = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => match ($id) {
				'OCA\OpenRegister\Service\ObjectService'        => $objects,
				'OCA\OpenRegister\Service\ConfigurationService' => $configuration,
			}
		);
		$controller = $this->controller(apps: ['files', 'openregister'], container: $container);

		$this->assertSame(expected: $objects, actual: $controller->getObjectService());
		$this->assertSame(expected: $configuration, actual: $controller->getConfigurationService());
	}//end testTheServicesComeFromTheContainerWithOpenRegister()

	/**
	 * Without OpenRegister both lookups refuse, and the container is not asked.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterTheLookupsRefuse(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects($this->never())->method('get');
		$controller = $this->controller(apps: ['files'], container: $container);

		$refusals = [
			'getObjectService'        => 'OpenRegister service is not available.',
			'getConfigurationService' => 'Configuration service is not available.',
		];
		foreach ($refusals as $method => $message) {
			try {
				$controller->$method();
				$this->fail(message: $method.' must refuse without OpenRegister.');
			} catch (RuntimeException $exception) {
				$this->assertSame(expected: $message, actual: $exception->getMessage());
			}
		}
	}//end testWithoutOpenRegisterTheLookupsRefuse()

	/**
	 * A re-import reports success with the settings and how many registers and schemas it imported.
	 *
	 * @return void
	 */
	public function testAReimportReportsWhatItImported(): void {
		$this->settings->expects($this->once())->method('reloadSettings')->willReturn(['registers' => [1], 'schemas' => [1, 2]]);
		$this->settings->method('getSettings')->willReturn(['register' => '7']);

		$response = $this->controller(apps: ['openregister'])->reimport();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(
			expected: [
				'success' => true,
				'message' => 'Configuration re-imported successfully',
				'config' => ['register' => '7'],
				'result' => ['registers' => 1, 'schemas' => 2],
			],
			actual: $response->getData()
		);
	}//end testAReimportReportsWhatItImported()

	/**
	 * A failed re-import answers 500 with its reason.
	 *
	 * @return void
	 */
	public function testAFailedReimportAnswersWithItsReason(): void {
		$this->settings->method('reloadSettings')->willThrowException(new RuntimeException('Configuration service is not available.'));

		$response = $this->controller(apps: [])->reimport();

		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: ['success' => false, 'message' => 'Configuration service is not available.'], actual: $response->getData());
	}//end testAFailedReimportAnswersWithItsReason()
}//end class
