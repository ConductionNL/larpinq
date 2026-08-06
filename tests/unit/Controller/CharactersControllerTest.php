<?php

/**
 * Unit tests for CharactersController.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Controller;

use Exception;
use OCA\LarpingApp\Controller\CharactersController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCA\LarpingApp\Service\DocuDeskPdfRenderer;
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
    private IAppManager&MockObject $appManager;
    private ContainerInterface&MockObject $container;
    private IUserSession&MockObject $userSession;
    private IGroupManager&MockObject $groupManager;
    private LoggerInterface&MockObject $logger;
    private \OCA\LarpingApp\Service\SkillRequirementService&MockObject $requirementService;

    /**
     * A REAL renderer wired to the same IAppManager / ContainerInterface / Logger
     * mocks the controller used to hold directly. Keeping it real (rather than a
     * mock of the renderer) means every downloadPdf test below still drives the
     * identical end-to-end pipeline — appManager -> container -> TemplateService
     * -> PdfService — so these tests remain a behaviour-preservation check across
     * the consolidation, not an assertion about the new internal shape.
     */
    private DocuDeskPdfRenderer $pdfRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->objectFetcher = $this->createMock(RegisterObjectFetcher::class);
        $this->appManager    = $this->createMock(IAppManager::class);
        $this->container     = $this->createMock(ContainerInterface::class);
        $this->userSession   = $this->createMock(IUserSession::class);
        $this->groupManager  = $this->createMock(IGroupManager::class);
        $this->logger        = $this->createMock(LoggerInterface::class);
        $this->requirementService = $this->createMock(\OCA\LarpingApp\Service\SkillRequirementService::class);

        $this->pdfRenderer = new DocuDeskPdfRenderer(
            $this->appManager,
            $this->container,
            $this->logger,
        );

        // Default: authenticated admin user.
        $mockUser = $this->createMock(IUser::class);
        $mockUser->method('getUID')->willReturn('admin');
        $this->userSession->method('getUser')->willReturn($mockUser);
        $this->groupManager->method('isAdmin')->with('admin')->willReturn(true);

        $this->controller = new CharactersController(
            'larpingapp',
            $this->createMock(IRequest::class),
            $this->objectFetcher,
            $this->pdfRenderer,
            $this->userSession,
            $this->groupManager,
            $this->requirementService,
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
            $this->pdfRenderer,
            $unauthSession,
            $this->groupManager,
            $this->requirementService,
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
            $this->pdfRenderer,
            $nonAdminSession,
            $nonAdminGroupManager,
            $this->requirementService,
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

        // A WELL-FORMED template UUID: template validation runs before the
        // DocuDesk probe, so a malformed value here would yield 400 and this
        // test would pass for the wrong reason.
        $result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-00000000042f');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(424, $result->getStatus());
        self::assertStringContainsString('DocuDesk', $result->getData()['error']);
    }

    public function testDownloadPdfReturns404WhenCharacterNotFound(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(true);
        $this->objectFetcher->method('getObject')
            ->willThrowException(new Exception('Not found'));

        $result = $this->controller->downloadPdf('nonexistent', '00000000-0000-0000-0000-000000000001');

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

        $result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-000000000002');

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

        $result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-000000000003');

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

        $result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-000000000004');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(500, $result->getStatus());
        self::assertStringContainsString('PDF generation failed', $result->getData()['error']);
        // The raw exception message (including internal paths) must NOT be leaked.
        self::assertStringNotContainsString('/internal/path/file.php', $result->getData()['error']);
    }

    public function testDownloadPdfReturns400ForNonUuidTemplate(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(true);

        $result = $this->controller->downloadPdf('char-1', '../../etc/passwd');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(400, $result->getStatus());
        self::assertStringContainsString('UUID', $result->getData()['error']);
    }

    /**
     * A malformed template ID is the caller's error whether or not DocuDesk is
     * installed. Without this ordering the 400 branch is unreachable on every
     * instance lacking DocuDesk (which is every CI runner), and a crafted
     * template value is answered with a misleading 424.
     */
    public function testDownloadPdfReturns400ForNonUuidTemplateEvenWhenDocuDeskAbsent(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(false);

        $result = $this->controller->downloadPdf('char-1', 'not-a-uuid');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(400, $result->getStatus());
        self::assertStringContainsString('UUID', $result->getData()['error']);
    }

    public function testDownloadPdfTranslatesDoesNotExistExceptionTo404(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(true);
        $this->objectFetcher->method('getObject')
            ->willThrowException(new DoesNotExistException('no such character'));

        $result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-00000000000a');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(404, $result->getStatus());
        self::assertSame('Character not found', $result->getData()['error']);
        // The OR exception message must not leak to the client.
        self::assertStringNotContainsString('no such character', $result->getData()['error']);
    }

    /**
     * The consolidation must not alter what DocuDesk actually receives. Pins the
     * template body, the render context and the page options passed to renderPdf,
     * plus the derived download filename — the details a refactor could silently
     * change while every status-code assertion stayed green.
     */
    public function testDownloadPdfPassesUnchangedContentContextAndOptionsToDocuDesk(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(true);

        $character = ['id' => 'char-1', 'name' => 'Sir Lancelot'];
        $this->objectFetcher->method('getObject')->willReturn($character);

        $templateData = [
            'content'     => '<h1>{{ character.name }}</h1>',
            'format'      => 'A3',
            'orientation' => 'L',
        ];

        $mockTemplateService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getTemplate'])
            ->getMock();
        $mockTemplateService->method('getTemplate')->willReturn($templateData);

        $mockPdfService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['renderPdf'])
            ->getMock();
        $mockPdfService->expects(self::once())
            ->method('renderPdf')
            ->with(
                '<h1>{{ character.name }}</h1>',
                ['character' => $character, 'template' => $templateData],
                ['format' => 'A3', 'orientation' => 'L'],
            )
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

        $result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-00000000000b');

        self::assertInstanceOf(DataDownloadResponse::class, $result);
        self::assertSame('%PDF-1.4 mock content', $result->render());
        self::assertStringContainsString(
            'Sir Lancelot_character_sheet.pdf',
            $this->filenameFromResponse($result),
        );
    }

    /**
     * The template id is lower-cased before it reaches DocuDesk, so an
     * upper-case UUID resolves the same template as its lower-case form.
     */
    public function testDownloadPdfLowerCasesTemplateIdBeforeLookup(): void
    {
        $this->appManager->method('isEnabledForUser')->willReturn(true);
        $this->objectFetcher->method('getObject')->willReturn(['id' => 'char-1', 'name' => 'Fighter']);

        $mockTemplateService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getTemplate'])
            ->getMock();
        $mockTemplateService->expects(self::once())
            ->method('getTemplate')
            ->with('0000000a-0000-0000-0000-00000000000c')
            ->willReturn(['content' => '', 'format' => 'A4', 'orientation' => 'P']);

        $mockPdfService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['renderPdf'])
            ->getMock();
        $mockPdfService->method('renderPdf')->willReturn('%PDF-1.4');

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

        $result = $this->controller->downloadPdf('char-1', '0000000A-0000-0000-0000-00000000000C');

        self::assertInstanceOf(DataDownloadResponse::class, $result);
    }

    /**
     * Read the Content-Disposition off a response without calling getHeaders(),
     * which reaches into the \OC runtime container and is unavailable in a pure
     * unit test. The protected `headers` array is populated by addHeader() in the
     * DownloadResponse constructor, so reflection sees the real value.
     */
    private function filenameFromResponse(DataDownloadResponse $response): string
    {
        $property = new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers');
        $property->setAccessible(true);
        /* @var array<string,string> $headers */
        $headers = $property->getValue($response);

        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'content-disposition') {
                return (string) $value;
            }
        }
        return '';
    }

    public function testRequirementReportTranslatesDoesNotExistExceptionTo404(): void
    {
        $this->objectFetcher->method('getObject')
            ->willThrowException(new DoesNotExistException('no such character'));

        $result = $this->controller->requirementReport('00000000-0000-0000-0000-00000000000d');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(404, $result->getStatus());
        self::assertSame('Character not found', $result->getData()['error']);
    }

    public function testRequirementReportReturns401WhenNotAuthenticated(): void
    {
        $unauthSession = $this->createMock(IUserSession::class);
        $unauthSession->method('getUser')->willReturn(null);

        $controller = new CharactersController(
            'larpingapp',
            $this->createMock(IRequest::class),
            $this->objectFetcher,
            $this->pdfRenderer,
            $unauthSession,
            $this->groupManager,
            $this->requirementService,
        );

        $result = $controller->requirementReport('00000000-0000-0000-0000-000000000005');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(401, $result->getStatus());
    }

    public function testRequirementReportReturns404WhenCharacterNotFound(): void
    {
        $this->objectFetcher->method('getObject')
            ->willThrowException(new Exception('Not found'));

        $result = $this->controller->requirementReport('00000000-0000-0000-0000-000000000006');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(404, $result->getStatus());
    }

    public function testRequirementReportReturnsReport(): void
    {
        $this->objectFetcher->method('getObject')
            ->willReturn(['id' => 'char-1', 'name' => 'Fighter', 'skills' => []]);

        $report = ['valid' => true, 'requirements' => [], 'budget' => ['ok' => true], 'dependents' => []];
        $this->requirementService->method('validate')->willReturn($report);

        $result = $this->controller->requirementReport('00000000-0000-0000-0000-000000000007');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(200, $result->getStatus());
        self::assertSame($report, $result->getData());
    }
}
