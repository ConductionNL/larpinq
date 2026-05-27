<?php

/**
 * Unit tests for CharactersController.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Controller;

use Exception;
use OCA\LarpingApp\Controller\CharactersController;
use OCA\LarpingApp\Service\CharacterService;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for CharactersController.
 */
class CharactersControllerTest extends TestCase
{

    private CharactersController $controller;
    private RegisterObjectFetcher&MockObject $objectFetcher;
    private CharacterService&MockObject $characterService;
    private IAppManager&MockObject $appManager;
    private ContainerInterface&MockObject $container;
    private IUserSession&MockObject $userSession;
    private IGroupManager&MockObject $groupManager;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->objectFetcher    = $this->createMock(RegisterObjectFetcher::class);
        $this->characterService = $this->createMock(CharacterService::class);
        $this->appManager       = $this->createMock(IAppManager::class);
        $this->container        = $this->createMock(ContainerInterface::class);
        $this->userSession      = $this->createMock(IUserSession::class);
        $this->groupManager     = $this->createMock(IGroupManager::class);
        $this->logger           = $this->createMock(LoggerInterface::class);

        // Default: authenticated admin user.
        $mockUser = $this->createMock(IUser::class);
        $mockUser->method('getUID')->willReturn('admin');
        $this->userSession->method('getUser')->willReturn($mockUser);
        $this->groupManager->method('isAdmin')->with('admin')->willReturn(true);

        $this->controller = new CharactersController(
            'larpingapp',
            $this->createMock(IRequest::class),
            $this->objectFetcher,
            $this->characterService,
            $this->appManager,
            $this->container,
            $this->userSession,
            $this->groupManager,
            $this->logger,
        );
    }

    public function testDownloadPdfReturns401WhenNotAuthenticated(): void
    {
        $unauthSession = $this->createMock(IUserSession::class);
        $unauthSession->method('getUser')->willReturn(null);

        $controller = new CharactersController(
            'larpingapp',
            $this->createMock(IRequest::class),
            $this->objectFetcher,
            $this->characterService,
            $this->appManager,
            $this->container,
            $unauthSession,
            $this->groupManager,
            $this->logger,
        );

        $result = $controller->downloadPdf('char-1', 'tpl-1');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(401, $result->getStatus());
        self::assertArrayHasKey('error', $result->getData());
    }

    public function testDownloadPdfReturns403ForNonAdminUser(): void
    {
        $nonAdminUser = $this->createMock(IUser::class);
        $nonAdminUser->method('getUID')->willReturn('player1');

        $nonAdminSession = $this->createMock(IUserSession::class);
        $nonAdminSession->method('getUser')->willReturn($nonAdminUser);

        $nonAdminGroupManager = $this->createMock(IGroupManager::class);
        $nonAdminGroupManager->method('isAdmin')->with('player1')->willReturn(false);

        $controller = new CharactersController(
            'larpingapp',
            $this->createMock(IRequest::class),
            $this->objectFetcher,
            $this->characterService,
            $this->appManager,
            $this->container,
            $nonAdminSession,
            $nonAdminGroupManager,
            $this->logger,
        );

        $result = $controller->downloadPdf('char-1', 'tpl-1');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(403, $result->getStatus());
        self::assertArrayHasKey('error', $result->getData());
    }

    public function testDownloadPdfReturns424WhenDocuDeskNotInstalled(): void
    {
        $this->appManager->method('isEnabledForUser')
            ->with('docudesk')
            ->willReturn(false);

        $result = $this->controller->downloadPdf('char-1', 'tpl-1');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(424, $result->getStatus());
        self::assertStringContainsString('DocuDesk', $result->getData()['error']);
    }

    public function testDownloadPdfReturns404WhenCharacterNotFound(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(true);
        $this->objectFetcher->method('getObject')
            ->willThrowException(new Exception('Not found'));

        $result = $this->controller->downloadPdf('nonexistent', 'tpl-1');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(404, $result->getStatus());
        self::assertSame('Character not found', $result->getData()['error']);
    }

    public function testDownloadPdfReturns404WhenTemplateNotFound(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(true);
        $this->objectFetcher->method('getObject')
            ->willReturn(['id' => 'char-1', 'name' => 'Fighter']);

        $mockTemplateService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getTemplate'])
            ->getMock();
        $mockTemplateService->method('getTemplate')
            ->willThrowException(new Exception('Template not found'));

        $this->container->method('get')
            ->willReturnCallback(function (string $class) use ($mockTemplateService) {
                if ($class === 'OCA\DocuDesk\Service\TemplateService') {
                    return $mockTemplateService;
                }
                return null;
            });

        $result = $this->controller->downloadPdf('char-1', 'nonexistent');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(404, $result->getStatus());
    }

    public function testDownloadPdfReturnsDataDownloadResponseOnSuccess(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(true);
        $this->objectFetcher->method('getObject')
            ->willReturn(['id' => 'char-1', 'name' => 'Sir Lancelot']);

        $mockTemplateService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getTemplate'])
            ->getMock();
        $mockTemplateService->method('getTemplate')
            ->willReturn([
                'content'     => '<h1>{{ character.name }}</h1>',
                'format'      => 'A4',
                'orientation' => 'P',
            ]);

        $mockPdfService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['renderPdf'])
            ->getMock();
        $mockPdfService->method('renderPdf')
            ->willReturn('%PDF-1.4 mock content');

        $this->container->method('get')
            ->willReturnCallback(function (string $class) use ($mockTemplateService, $mockPdfService) {
                if ($class === 'OCA\DocuDesk\Service\TemplateService') {
                    return $mockTemplateService;
                }
                if ($class === 'OCA\DocuDesk\Service\PdfService') {
                    return $mockPdfService;
                }
                return null;
            });

        $result = $this->controller->downloadPdf('char-1', 'tpl-1');

        self::assertInstanceOf(DataDownloadResponse::class, $result);
    }

    public function testDownloadPdfReturns500OnRenderFailureWithGenericMessage(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(true);
        $this->objectFetcher->method('getObject')
            ->willReturn(['id' => 'char-1', 'name' => 'Fighter']);

        $mockTemplateService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getTemplate'])
            ->getMock();
        $mockTemplateService->method('getTemplate')
            ->willReturn(['content' => '', 'format' => 'A4', 'orientation' => 'P']);

        $mockPdfService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['renderPdf'])
            ->getMock();
        $mockPdfService->method('renderPdf')
            ->willThrowException(new Exception('Render failed: /internal/path/file.php line 42'));

        $this->container->method('get')
            ->willReturnCallback(function (string $class) use ($mockTemplateService, $mockPdfService) {
                if ($class === 'OCA\DocuDesk\Service\TemplateService') {
                    return $mockTemplateService;
                }
                if ($class === 'OCA\DocuDesk\Service\PdfService') {
                    return $mockPdfService;
                }
                return null;
            });

        $result = $this->controller->downloadPdf('char-1', 'tpl-1');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(500, $result->getStatus());
        self::assertStringContainsString('PDF generation failed', $result->getData()['error']);
        // The raw exception message (including internal paths) must NOT be leaked.
        self::assertStringNotContainsString('/internal/path/file.php', $result->getData()['error']);
    }
}
