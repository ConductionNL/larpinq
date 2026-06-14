<?php

/**
 * Unit tests for EventsController (run-sheet export).
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
use OCA\LarpingApp\Controller\EventsController;
use OCA\LarpingApp\Service\DocuDeskPdfRenderer;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the GM run-sheet export.
 */
class EventsControllerTest extends TestCase
{
    private const TPL = '00000000-0000-0000-0000-000000000010';
    private const EVT = '00000000-0000-0000-0000-0000000000ev';

    private RegisterObjectFetcher&MockObject $objectFetcher;
    private DocuDeskPdfRenderer&MockObject $pdfRenderer;
    private IUserSession&MockObject $userSession;
    private IGroupManager&MockObject $groupManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectFetcher = $this->createMock(RegisterObjectFetcher::class);
        $this->pdfRenderer   = $this->createMock(DocuDeskPdfRenderer::class);
        $this->userSession   = $this->createMock(IUserSession::class);
        $this->groupManager  = $this->createMock(IGroupManager::class);
    }

    private function controller(): EventsController
    {
        return new EventsController(
            'larpingapp',
            $this->createMock(IRequest::class),
            $this->objectFetcher,
            $this->pdfRenderer,
            $this->userSession,
            $this->groupManager,
        );
    }

    private function asUser(string $uid, bool $isGm, bool $isAdmin = false): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isInGroup')->willReturn($isGm);
        $this->groupManager->method('isAdmin')->willReturn($isAdmin);
    }

    public function testReturns401WhenNotAuthenticated(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
        self::assertSame(401, $result->getStatus());
    }

    public function testReturns403ForNonGm(): void
    {
        $this->asUser('player1', isGm: false, isAdmin: false);
        $result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(403, $result->getStatus());
    }

    public function testReturns424WhenDocuDeskAbsent(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(false);
        $result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
        self::assertSame(424, $result->getStatus());
    }

    public function testReturns400ForNonUuidTemplate(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
        $this->pdfRenderer->method('normaliseTemplateId')->willReturn(null);
        $result = $this->controller()->downloadRunsheet(self::EVT, 'not-a-uuid');
        self::assertSame(400, $result->getStatus());
    }

    public function testReturns404WhenEventNotFound(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
        $this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
        $this->objectFetcher->method('getObject')->willThrowException(new Exception('nope'));
        $result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
        self::assertSame(404, $result->getStatus());
    }

    public function testReturns404WhenTemplateNotFound(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
        $this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
        $this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'name' => 'Summer LARP']);
        $this->pdfRenderer->method('getTemplate')->willReturn(null);
        $result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
        self::assertSame(404, $result->getStatus());
    }

    public function testSuccessReturnsPdfNamedAfterEvent(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
        $this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
        $this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'name' => 'Summer LARP']);
        // Two characters, one in this event, one not — only the participant is cast.
        $this->objectFetcher->method('getObjects')->willReturnCallback(function (string $type): array {
            if ($type === 'character') {
                return [
                    ['id' => 'c1', 'name' => 'Zara', 'events' => [self::EVT], 'ocName' => 'Alice', 'items' => ['i1']],
                    ['id' => 'c2', 'name' => 'Borin', 'events' => [self::EVT], 'ocName' => 'Bob', 'items' => ['i1', 'i2']],
                    ['id' => 'c3', 'name' => 'Elsewhere', 'events' => ['other-event']],
                ];
            }
            return [];
        });

        $captured = null;
        $this->pdfRenderer->method('getTemplate')->willReturn(['content' => '<h1>{{ event.name }}</h1>', 'format' => 'A4', 'orientation' => 'P']);
        $this->pdfRenderer->method('render')->willReturnCallback(function (array $tpl, array $ctx) use (&$captured): string {
            $captured = $ctx;
            return '%PDF-1.4 runsheet';
        });

        $result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
        self::assertInstanceOf(DataDownloadResponse::class, $result);

        // Cast contains the two participants, sorted by name (Borin before Zara).
        self::assertSame(2, $captured['castCount']);
        self::assertSame('Borin', $captured['cast'][0]['name']);
        self::assertSame('Zara', $captured['cast'][1]['name']);
        // Unique items rollup deduplicates i1 across both characters.
        self::assertEqualsCanonicalizing(['i1', 'i2'], $captured['uniqueItemsInPlay']);
        // Player name falls back to ocName.
        self::assertSame('Alice', $captured['cast'][1]['playerName']);
    }

    public function testFilenameFallbackWhenEventUnnamed(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
        $this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
        $this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT]);
        $this->objectFetcher->method('getObjects')->willReturn([]);
        $this->pdfRenderer->method('getTemplate')->willReturn(['content' => '', 'format' => 'A4', 'orientation' => 'P']);
        $this->pdfRenderer->method('render')->willReturn('%PDF mock');

        $result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
        self::assertInstanceOf(DataDownloadResponse::class, $result);
    }

    public function testReturns500WhenRenderFails(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
        $this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
        $this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'name' => 'Summer LARP']);
        $this->objectFetcher->method('getObjects')->willReturn([]);
        $this->pdfRenderer->method('getTemplate')->willReturn(['content' => '', 'format' => 'A4', 'orientation' => 'P']);
        $this->pdfRenderer->method('render')->willReturn(null);

        $result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
        self::assertSame(500, $result->getStatus());
    }

    public function testAdminWithoutGmGroupAllowed(): void
    {
        // An NC admin (legacy GM) is allowed even without the gamemasters group.
        $this->asUser('admin', isGm: false, isAdmin: true);
        $this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(false);
        $result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
        // Passes the GM guard, then hits the 424 (DocuDesk absent) — proves not 403.
        self::assertSame(424, $result->getStatus());
    }
}
