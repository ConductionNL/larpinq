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
    protected function setUp(): void
    {
        parent::setUp();

        $this->request         = $this->createMock(IRequest::class);
        $this->settingsService = $this->createMock(SettingsService::class);

        $appManager = $this->createMock(IAppManager::class);
        $appManager->method('getInstalledApps')->willReturn(['openregister']);

        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('getUser')->willReturn(null);

        $this->controller = new SettingsController(
            request: $this->request,
            container: $this->createMock(ContainerInterface::class),
            appManager: $appManager,
            settingsService: $this->settingsService,
            groupManager: $this->createMock(IGroupManager::class),
            userSession: $userSession,
        );

    }//end setUp()

    /**
     * Test that index() returns a JSONResponse with objectTypes and configuration keys.
     *
     * @return void
     */
    public function testIndexReturnsJsonResponseWithExpectedKeys(): void
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(['openRegisterUrl' => 'http://localhost']);

        $result = $this->controller->index();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertArrayHasKey('objectTypes', $result->getData());
        self::assertArrayHasKey('configuration', $result->getData());

    }//end testIndexReturnsJsonResponseWithExpectedKeys()

    /**
     * Test that index() includes all expected object types.
     *
     * @return void
     */
    public function testIndexIncludesAllObjectTypes(): void
    {
        $this->settingsService->method('getSettings')->willReturn([]);

        $result     = $this->controller->index();
        $objectTypes = $result->getData()['objectTypes'];

        self::assertContains('character', $objectTypes);
        self::assertContains('skill', $objectTypes);
        self::assertContains('item', $objectTypes);

    }//end testIndexIncludesAllObjectTypes()

    /**
     * Test that create() calls updateSettings and returns a success response.
     *
     * @return void
     */
    public function testCreateCallsUpdateSettingsAndReturnsSuccess(): void
    {
        $params = ['openRegisterUrl' => 'http://new-url'];

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

}//end class
