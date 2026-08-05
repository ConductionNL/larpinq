<?php

/**
 * Unit tests for EventsController check-in roster + attendance recording.
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
use OCA\LarpingApp\Service\EventRosterService;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the GM-only, server-stamped, participant-scoped attendance endpoint
 * and the graceful-degradation roster read (event-checkin-roster).
 */
class EventsControllerAttendanceTest extends TestCase
{
    private const EVT  = '00000000-0000-0000-0000-0000000000ev';
    private const CHAR = '00000000-0000-0000-0000-00000000char';

    private RegisterObjectFetcher&MockObject $objectFetcher;
    private DocuDeskPdfRenderer&MockObject $pdfRenderer;
    private IUserSession&MockObject $userSession;
    private IGroupManager&MockObject $groupManager;
    private IRequest&MockObject $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectFetcher = $this->createMock(RegisterObjectFetcher::class);
        $this->pdfRenderer   = $this->createMock(DocuDeskPdfRenderer::class);
        $this->userSession   = $this->createMock(IUserSession::class);
        $this->groupManager  = $this->createMock(IGroupManager::class);
        $this->request       = $this->createMock(IRequest::class);
    }

    private function controller(): EventsController
    {
        // The real EventRosterService over the mocked fetcher: the attendance /
        // participation rules stay under test end-to-end through the controller.
        return new EventsController(
            'larpingapp',
            $this->request,
            new EventRosterService($this->objectFetcher),
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

    /**
     * @param array<string,string> $params Request params (character/status).
     */
    private function withParams(array $params): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static function (string $key, $default = null) use ($params) {
                return $params[$key] ?? $default;
            }
        );
    }

    // ---- recordAttendance -------------------------------------------------

    public function testRecordUnauthenticatedReturns401(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $result = $this->controller()->recordAttendance(self::EVT);
        self::assertSame(401, $result->getStatus());
    }

    public function testRecordNonGmRefused(): void
    {
        $this->asUser('player1', isGm: false, isAdmin: false);
        // A non-GM must never reach the write path.
        $this->objectFetcher->expects(self::never())->method('saveObject');
        $result = $this->controller()->recordAttendance(self::EVT);
        self::assertSame(403, $result->getStatus());
    }

    public function testRecordMissingCharacterReturns400(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->withParams(['status' => 'checked-in']);
        $result = $this->controller()->recordAttendance(self::EVT);
        self::assertSame(400, $result->getStatus());
    }

    public function testRecordInvalidStatusReturns400(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->withParams(['character' => self::CHAR, 'status' => 'teleported']);
        $result = $this->controller()->recordAttendance(self::EVT);
        self::assertSame(400, $result->getStatus());
    }

    public function testRecordEventNotFoundReturns404(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->withParams(['character' => self::CHAR, 'status' => 'checked-in']);
        $this->objectFetcher->method('getObject')->willThrowException(new Exception('nope'));
        $result = $this->controller()->recordAttendance(self::EVT);
        self::assertSame(404, $result->getStatus());
    }

    public function testRecordNonParticipantRefused(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->withParams(['character' => self::CHAR, 'status' => 'checked-in']);
        // Event has no players; character does not reference the event.
        $this->objectFetcher->method('getObject')->willReturnCallback(
            function (string $objectType, string $id): array {
                if ($objectType === 'event') {
                    return ['id' => self::EVT, 'name' => 'Summer LARP', 'players' => []];
                }
                return ['id' => self::CHAR, 'events' => ['some-other-event']];
            }
        );
        $this->objectFetcher->expects(self::never())->method('saveObject');
        $result = $this->controller()->recordAttendance(self::EVT);
        self::assertSame(422, $result->getStatus());
    }

    public function testRecordStampsServerProvenanceIgnoringBody(): void
    {
        $this->asUser('gm-real', isGm: true);
        // Spoofed provenance in the body MUST be ignored.
        $this->withParams([
            'character'   => self::CHAR,
            'status'      => 'checked-in',
            'checkedInBy' => 'attacker',
            'checkedInAt' => '1999-01-01T00:00:00+00:00',
        ]);
        // Participant via the event players[] branch.
        $this->objectFetcher->method('getObject')->willReturn(
            ['id' => self::EVT, 'name' => 'Summer LARP', 'players' => [self::CHAR]]
        );
        // Attendance schema present; no prior record → create (uuid null).
        $this->objectFetcher->method('getObjects')->willReturn([]);

        $captured = null;
        $this->objectFetcher->method('saveObject')->willReturnCallback(
            function (string $objectType, array $data, ?string $uuid) use (&$captured): array {
                $captured = ['type' => $objectType, 'data' => $data, 'uuid' => $uuid];
                return $data;
            }
        );

        $result = $this->controller()->recordAttendance(self::EVT);
        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(200, $result->getStatus());

        self::assertSame('attendance', $captured['type']);
        self::assertNull($captured['uuid']);
        self::assertSame(self::CHAR, $captured['data']['character']);
        self::assertSame(self::EVT, $captured['data']['event']);
        self::assertSame('checked-in', $captured['data']['status']);
        // Server-stamped: uid is the acting GM, NOT the spoofed 'attacker'.
        self::assertSame('gm-real', $captured['data']['checkedInBy']);
        self::assertNotSame('attacker', $captured['data']['checkedInBy']);
        // checkedInAt is a server ISO-8601 timestamp, NOT the spoofed 1999 value.
        self::assertNotSame('1999-01-01T00:00:00+00:00', $captured['data']['checkedInAt']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $captured['data']['checkedInAt']);
    }

    public function testRecordUpdatesExistingRecordInPlace(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->withParams(['character' => self::CHAR, 'status' => 'no-show']);
        $this->objectFetcher->method('getObject')->willReturn(
            ['id' => self::EVT, 'players' => [self::CHAR]]
        );
        // A prior attendance record exists → update path passes its uuid.
        $this->objectFetcher->method('getObjects')->willReturn([
            ['id' => 'att-1', 'event' => self::EVT, 'character' => self::CHAR, 'status' => 'checked-in'],
        ]);

        $capturedUuid = 'sentinel';
        $this->objectFetcher->method('saveObject')->willReturnCallback(
            function (string $objectType, array $data, ?string $uuid) use (&$capturedUuid): array {
                $capturedUuid = $uuid;
                return $data;
            }
        );

        $result = $this->controller()->recordAttendance(self::EVT);
        self::assertSame(200, $result->getStatus());
        self::assertSame('att-1', $capturedUuid);
    }

    public function testRecordDegradesTo424WhenSchemaAbsent(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->withParams(['character' => self::CHAR, 'status' => 'checked-in']);
        $this->objectFetcher->method('getObject')->willReturn(
            ['id' => self::EVT, 'players' => [self::CHAR]]
        );
        // Attendance schema not configured → loadAttendance reports unavailable.
        $this->objectFetcher->method('getObjects')->willThrowException(new Exception('not configured'));
        $this->objectFetcher->expects(self::never())->method('saveObject');

        $result = $this->controller()->recordAttendance(self::EVT);
        self::assertSame(424, $result->getStatus());
    }

    // ---- roster -----------------------------------------------------------

    public function testRosterUnauthenticatedReturns401(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $result = $this->controller()->roster(self::EVT);
        self::assertSame(401, $result->getStatus());
    }

    public function testRosterEventNotFoundReturns404(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->objectFetcher->method('getObject')->willThrowException(new Exception('nope'));
        $result = $this->controller()->roster(self::EVT);
        self::assertSame(404, $result->getStatus());
    }

    public function testRosterListsParticipantsWithStatus(): void
    {
        $this->asUser('gm1', isGm: true);
        $this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'players' => []]);
        $this->objectFetcher->method('getObjects')->willReturnCallback(
            function (string $objectType, $limit = null, $offset = null, $filters = []): array {
                if ($objectType === 'attendance') {
                    return [
                        ['event' => self::EVT, 'character' => self::CHAR, 'status' => 'checked-in', 'checkedInBy' => 'gm1'],
                    ];
                }
                if ($objectType === 'character') {
                    return [
                        ['id' => self::CHAR, 'name' => 'Aldric', 'type' => 'Knight', 'ocName' => 'Alice', 'events' => [self::EVT]],
                        ['id' => 'other', 'name' => 'Elsewhere', 'events' => ['x']],
                    ];
                }
                return [];
            }
        );

        $result = $this->controller()->roster(self::EVT);
        self::assertSame(200, $result->getStatus());
        $data = $result->getData();
        self::assertTrue($data['attendanceAvailable']);
        self::assertTrue($data['isGm']);
        self::assertCount(1, $data['participants']);
        self::assertSame('Aldric', $data['participants'][0]['name']);
        self::assertSame('checked-in', $data['participants'][0]['status']);
        self::assertSame('Alice', $data['participants'][0]['playerName']);
    }

    public function testRosterDegradesWhenAttendanceUnavailable(): void
    {
        // A player (non-GM) viewing an event whose attendance schema is absent.
        $this->asUser('player1', isGm: false);
        $this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'players' => []]);
        $this->objectFetcher->method('getObjects')->willReturnCallback(
            function (string $objectType, $limit = null, $offset = null, $filters = []): array {
                if ($objectType === 'attendance') {
                    throw new Exception('not configured');
                }
                if ($objectType === 'character') {
                    return [
                        ['id' => self::CHAR, 'name' => 'Aldric', 'type' => 'Knight', 'events' => [self::EVT]],
                    ];
                }
                return [];
            }
        );

        $result = $this->controller()->roster(self::EVT);
        self::assertSame(200, $result->getStatus());
        $data = $result->getData();
        // Degraded: participant still listed read-only, no attendance, not GM.
        self::assertFalse($data['attendanceAvailable']);
        self::assertFalse($data['isGm']);
        self::assertCount(1, $data['participants']);
        self::assertSame('registered', $data['participants'][0]['status']);
    }
}
