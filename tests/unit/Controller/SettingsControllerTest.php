<?php

/**
 * Unit tests for SettingsController.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Controller
 *
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Controller;

use OCA\LarpingApp\Controller\SettingsController;
use OCA\LarpingApp\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Tests for SettingsController.
 */
class SettingsControllerTest extends TestCase
{

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
     * Mock ContainerInterface.
     *
     * @var ContainerInterface&MockObject
     */
    private ContainerInterface&MockObject $container;

    /**
     * Mock IAppManager.
     *
     * @var IAppManager&MockObject
     */
    private IAppManager&MockObject $appManager;

    /**
     * Mock SettingsService.
     *
     * @var SettingsService&MockObject
     */
    private SettingsService&MockObject $settingsService;

    /**
     * Mock IGroupManager.
     *
     * @var IGroupManager&MockObject
     */
    private IGroupManager&MockObject $groupManager;

    /**
     * Mock IUserSession.
     *
     * @var IUserSession&MockObject
     */
    private IUserSession&MockObject $userSession;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request         = $this->createMock(IRequest::class);
        $this->container       = $this->createMock(ContainerInterface::class);
        $this->appManager      = $this->createMock(IAppManager::class);
        $this->settingsService = $this->createMock(SettingsService::class);
        $this->groupManager    = $this->createMock(IGroupManager::class);
        $this->userSession     = $this->createMock(IUserSession::class);

        $this->controller = new SettingsController(
            request: $this->request,
            container: $this->container,
            appManager: $this->appManager,
            settingsService: $this->settingsService,
            groupManager: $this->groupManager,
            userSession: $this->userSession,
        );

    }//end setUp()

    /**
     * Test that index() returns a JSONResponse with objectTypes and configuration keys.
     *
     * @return void
     */
    public function testIndexReturnsJsonResponseWithExpectedKeys(): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('admin');

        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isAdmin')->with('admin')->willReturn(true);

        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(['register' => '']);

        $result = $this->controller->index();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertArrayHasKey('objectTypes', $result->getData());
        self::assertArrayHasKey('configuration', $result->getData());
        self::assertArrayHasKey('openRegisters', $result->getData());
        self::assertArrayHasKey('isAdmin', $result->getData());

    }//end testIndexReturnsJsonResponseWithExpectedKeys()

    /**
     * Test that index() includes all expected object types.
     *
     * @return void
     */
    public function testIndexIncludesAllObjectTypes(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $this->appManager->method('getInstalledApps')->willReturn([]);
        $this->settingsService->method('getSettings')->willReturn([]);

        $result      = $this->controller->index();
        $objectTypes = $result->getData()['objectTypes'];

        self::assertContains('character', $objectTypes);
        self::assertContains('skill', $objectTypes);
        self::assertContains('item', $objectTypes);
        self::assertContains('ability', $objectTypes);
        self::assertContains('condition', $objectTypes);
        self::assertContains('effect', $objectTypes);
        self::assertContains('event', $objectTypes);
        self::assertContains('player', $objectTypes);
        self::assertContains('setting', $objectTypes);
        self::assertCount(9, $objectTypes);

    }//end testIndexIncludesAllObjectTypes()

    /**
     * Test that index() detects OpenRegister availability.
     *
     * @return void
     */
    public function testIndexDetectsOpenRegisterAvailability(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);
        $this->settingsService->method('getSettings')->willReturn([]);

        $result = $this->controller->index();

        self::assertTrue($result->getData()['openRegisters']);

    }//end testIndexDetectsOpenRegisterAvailability()

    /**
     * Test that index() identifies admin users correctly.
     *
     * @return void
     */
    public function testIndexIdentifiesAdminUser(): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('admin');

        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isAdmin')->with('admin')->willReturn(true);
        $this->appManager->method('getInstalledApps')->willReturn([]);
        $this->settingsService->method('getSettings')->willReturn([]);

        $result = $this->controller->index();

        self::assertTrue($result->getData()['isAdmin']);

    }//end testIndexIdentifiesAdminUser()

    /**
     * Test that create() calls updateSettings and returns a success response.
     *
     * @return void
     */
    public function testCreateCallsUpdateSettingsAndReturnsSuccess(): void
    {
        $params = ['register' => 'reg-123'];

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->settingsService->expects($this->once())
            ->method('updateSettings')
            ->with(data: $params)
            ->willReturn($params);

        $result = $this->controller->create();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertTrue($result->getData()['success']);
        self::assertArrayHasKey('config', $result->getData());

    }//end testCreateCallsUpdateSettingsAndReturnsSuccess()

    /**
     * Test that create() returns a 500 response when an exception is thrown.
     *
     * @return void
     */
    public function testCreateReturns500OnException(): void
    {
        $this->request->method('getParams')->willReturn([]);

        $this->settingsService->method('updateSettings')
            ->willThrowException(new \Exception('Service error'));

        $result = $this->controller->create();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(500, $result->getStatus());
        self::assertArrayHasKey('error', $result->getData());

    }//end testCreateReturns500OnException()

    /**
     * Test that reimport() calls loadSettings with force=true.
     *
     * @return void
     */
    public function testReimportCallsLoadSettingsWithForce(): void
    {
        $this->settingsService->expects($this->once())
            ->method('loadSettings')
            ->with(force: true)
            ->willReturn(['registers' => ['r1'], 'schemas' => ['s1', 's2']]);

        $this->settingsService->method('getSettings')
            ->willReturn(['register' => '1']);

        $result = $this->controller->reimport();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertTrue($result->getData()['success']);
        self::assertSame(1, $result->getData()['result']['registers']);
        self::assertSame(2, $result->getData()['result']['schemas']);

    }//end testReimportCallsLoadSettingsWithForce()

    /**
     * Test that reimport() returns 500 on exception.
     *
     * @return void
     */
    public function testReimportReturns500OnException(): void
    {
        $this->settingsService->method('loadSettings')
            ->willThrowException(new \Exception('Import failed'));

        $result = $this->controller->reimport();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(500, $result->getStatus());
        self::assertFalse($result->getData()['success']);

    }//end testReimportReturns500OnException()
}//end class
