<?php

/**
 * Unit tests for SettingsController.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\SettingsController;
use OCA\Larpinq\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for SettingsController.
 */
class SettingsControllerTest extends TestCase {

	/**
	 * The controller under test.
	 *
	 * @var SettingsController
	 */
	private SettingsController $controller;

	/**
	 * Mock IRequest.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest&MockObject $request;

	/**
	 * Mock SettingsService.
	 *
	 * @var SettingsService&MockObject
	 */
	private SettingsService&MockObject $settingsService;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->settingsService = $this->createMock(originalClassName: SettingsService::class);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getInstalledApps')->willReturn(['openregister']);

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		$this->controller = new SettingsController(
			request: $this->request,
			container: $this->createMock(originalClassName: ContainerInterface::class),
			appManager: $appManager,
			settingsService: $this->settingsService,
			callerAccess: new CallerAccess($userSession, $this->createMock(originalClassName: IGroupManager::class)),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);

	}//end setUp()

	/**
	 * Test that index() returns a JSONResponse with objectTypes and availableRegisters for a
	 * non-admin user. The 'configuration' key must NOT be present (C2 security fix).
	 *
	 * @return void
	 */
	public function testIndexReturnsJsonResponseWithExpectedKeys(): void {
		// setUp() creates a null user → isAdmin = false; getSettings must NOT be called.
		$this->settingsService->expects($this->never())->method('getSettings');

		$result = $this->controller->index();

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertArrayHasKey(key: 'objectTypes', array: $result->getData());
		self::assertArrayHasKey(key: 'availableRegisters', array: $result->getData());
		// Non-admin must not receive the configuration block (register/schema IDs).
		self::assertArrayNotHasKey(key: 'configuration', array: $result->getData());
		// Non-admin must receive an empty availableRegisters list.
		self::assertEmpty(actual: $result->getData()['availableRegisters']);

	}//end testIndexReturnsJsonResponseWithExpectedKeys()

	/**
	 * Test that index() returns the configuration block only for admin users.
	 *
	 * @return void
	 */
	public function testIndexReturnsConfigurationForAdmin(): void {
		$mockUser = $this->createMock(originalClassName: \OCP\IUser::class);
		$mockUser->method('getUID')->willReturn('admin');

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($mockUser);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('isAdmin')->with('admin')->willReturn(true);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getInstalledApps')->willReturn([]);

		$settingsService = $this->createMock(originalClassName: SettingsService::class);
		$settingsService->expects($this->once())
			->method('getSettings')
			->willReturn(['openRegisterUrl' => 'http://localhost']);

		$adminController = new SettingsController(
			request: $this->request,
			container: $this->createMock(originalClassName: ContainerInterface::class),
			appManager: $appManager,
			settingsService: $settingsService,
			callerAccess: new CallerAccess($userSession, $groupManager),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);

		$result = $adminController->index();

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertArrayHasKey(key: 'configuration', array: $result->getData());
		self::assertSame(expected: 'http://localhost', actual: $result->getData()['configuration']['openRegisterUrl']);

	}//end testIndexReturnsConfigurationForAdmin()

	/**
	 * Test that index() includes all expected object types.
	 *
	 * @return void
	 */
	public function testIndexIncludesAllObjectTypes(): void {
		$this->settingsService->method('getSettings')->willReturn([]);

		$result = $this->controller->index();
		$objectTypes = $result->getData()['objectTypes'];

		self::assertContains(needle: 'character', haystack: $objectTypes);
		self::assertContains(needle: 'skill', haystack: $objectTypes);
		self::assertContains(needle: 'item', haystack: $objectTypes);

	}//end testIndexIncludesAllObjectTypes()

	/**
	 * Test that create() calls updateSettings and returns a success response.
	 *
	 * @return void
	 */
	public function testCreateCallsUpdateSettingsAndReturnsSuccess(): void {
		$params = ['openRegisterUrl' => 'http://new-url'];

		$this->request->expects($this->once())
			->method('getParams')
			->willReturn($params);

		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with(data: $params)
			->willReturn($params);

		$result = $this->controller->create();

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertTrue(condition: $result->getData()['success']);
		self::assertArrayHasKey(key: 'config', array: $result->getData());

	}//end testCreateCallsUpdateSettingsAndReturnsSuccess()

	/**
	 * Test that create() returns a 500 response when an exception is thrown.
	 *
	 * @return void
	 */
	public function testCreateReturns500OnException(): void {
		$this->request->method('getParams')->willReturn([]);

		$this->settingsService->method('updateSettings')
			->willThrowException(new \Exception('Service error'));

		$result = $this->controller->create();

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 500, actual: $result->getStatus());
		self::assertArrayHasKey(key: 'error', array: $result->getData());

	}//end testCreateReturns500OnException()

}//end class
