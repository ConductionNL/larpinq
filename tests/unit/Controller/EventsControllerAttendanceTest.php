<?php

/**
 * Unit tests for EventsController check-in roster + attendance recording.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use Exception;
use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\EventsController;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
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
class EventsControllerAttendanceTest extends TestCase {
	private const EVT = '00000000-0000-0000-0000-0000000000ev';
	private const CHAR = '00000000-0000-0000-0000-00000000char';

	/** @var RegisterObjectFetcher&MockObject The object fetcher. */
	private RegisterObjectFetcher&MockObject $objectFetcher;
	/** @var DocuDeskPdfRenderer&MockObject The pdf renderer. */
	private DocuDeskPdfRenderer&MockObject $pdfRenderer;
	/** @var IUserSession&MockObject The user session. */
	private IUserSession&MockObject $userSession;
	/** @var IGroupManager&MockObject The group manager. */
	private IGroupManager&MockObject $groupManager;
	/** @var IRequest&MockObject The request. */
	private IRequest&MockObject $request;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->objectFetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$this->pdfRenderer = $this->createMock(originalClassName: DocuDeskPdfRenderer::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->request = $this->createMock(originalClassName: IRequest::class);
	}

	/**
	 * The controller over the real roster service and the doubles.
	 *
	 * @return EventsController The controller over the real roster service and the doubles.
	 */
	private function controller(): EventsController {
		// The real EventRosterService over the mocked fetcher: the attendance /
		// participation rules stay under test end-to-end through the controller.
		return new EventsController(
			request: $this->request,
			rosterService: new EventRosterService(objectFetcher: $this->objectFetcher),
			pdfRenderer: $this->pdfRenderer,
			callerAccess: new CallerAccess(userSession: $this->userSession, groupManager: $this->groupManager),
		);
	}

	/**
	 * Sign in a user, as a game master and or administrator or not.
	 *
	 * @param string $uid The user id.
	 * @param bool $isGm Whether the user is a game master.
	 * @param bool $isAdmin Whether the user is an administrator.
	 *
	 * @return void
	 */
	private function asUser(string $uid, bool $isGm, bool $isAdmin = false): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isInGroup')->willReturn($isGm);
		$this->groupManager->method('isAdmin')->willReturn($isAdmin);
	}

	/**
	 * @param array<string,string> $params Request params (character/status).
	 *
	 * @return void
	 */
	private function withParams(array $params): void {
		$this->request->method('getParam')->willReturnCallback(
			static function (string $key, $default = null) use ($params) {
				return $params[$key] ?? $default;
			}
		);
	}

	// ---- recordAttendance -------------------------------------------------

	/**
	 * Recording attendance without a signed-in user answers 401.
	 *
	 * @return void
	 */
	public function testRecordUnauthenticatedReturns401(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$result = $this->controller()->recordAttendance(self::EVT);
		self::assertSame(expected: 401, actual: $result->getStatus());
	}

	/**
	 * Record non GM refused.
	 *
	 * @return void
	 */
	public function testRecordNonGmRefused(): void {
		$this->asUser(uid: 'player1', isGm: false, isAdmin: false);
		// A non-GM must never reach the write path.
		$this->objectFetcher->expects(self::never())->method('saveObject');
		$result = $this->controller()->recordAttendance(self::EVT);
		self::assertSame(expected: 403, actual: $result->getStatus());
	}

	/**
	 * Record missing character returns400.
	 *
	 * @return void
	 */
	public function testRecordMissingCharacterReturns400(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->withParams(params: ['status' => 'checked-in']);
		$result = $this->controller()->recordAttendance(self::EVT);
		self::assertSame(expected: 400, actual: $result->getStatus());
	}

	/**
	 * Record invalid status returns400.
	 *
	 * @return void
	 */
	public function testRecordInvalidStatusReturns400(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->withParams(params: ['character' => self::CHAR, 'status' => 'teleported']);
		$result = $this->controller()->recordAttendance(self::EVT);
		self::assertSame(expected: 400, actual: $result->getStatus());
	}

	/**
	 * Record event not found returns404.
	 *
	 * @return void
	 */
	public function testRecordEventNotFoundReturns404(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->withParams(params: ['character' => self::CHAR, 'status' => 'checked-in']);
		$this->objectFetcher->method('getObject')->willThrowException(new Exception('nope'));
		$result = $this->controller()->recordAttendance(self::EVT);
		self::assertSame(expected: 404, actual: $result->getStatus());
	}

	/**
	 * Record non participant refused.
	 *
	 * @return void
	 */
	public function testRecordNonParticipantRefused(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->withParams(params: ['character' => self::CHAR, 'status' => 'checked-in']);
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
		self::assertSame(expected: 422, actual: $result->getStatus());
	}

	/**
	 * Record stamps server provenance ignoring body.
	 *
	 * @return void
	 */
	public function testRecordStampsServerProvenanceIgnoringBody(): void {
		$this->asUser(uid: 'gm-real', isGm: true);
		// Spoofed provenance in the body MUST be ignored.
		$this->withParams(params: [
			'character' => self::CHAR,
			'status' => 'checked-in',
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
		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 200, actual: $result->getStatus());

		self::assertSame(expected: 'attendance', actual: $captured['type']);
		self::assertNull(actual: $captured['uuid']);
		self::assertSame(expected: self::CHAR, actual: $captured['data']['character']);
		self::assertSame(expected: self::EVT, actual: $captured['data']['event']);
		self::assertSame(expected: 'checked-in', actual: $captured['data']['status']);
		// Server-stamped: uid is the acting GM, NOT the spoofed 'attacker'.
		self::assertSame(expected: 'gm-real', actual: $captured['data']['checkedInBy']);
		self::assertNotSame(expected: 'attacker', actual: $captured['data']['checkedInBy']);
		// The checkedInAt value is a server ISO-8601 timestamp, NOT the spoofed 1999 value.
		self::assertNotSame(expected: '1999-01-01T00:00:00+00:00', actual: $captured['data']['checkedInAt']);
		self::assertMatchesRegularExpression(pattern: '/^\d{4}-\d{2}-\d{2}T/', string: $captured['data']['checkedInAt']);
	}

	/**
	 * Record updates existing record in place.
	 *
	 * @return void
	 */
	public function testRecordUpdatesExistingRecordInPlace(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->withParams(params: ['character' => self::CHAR, 'status' => 'no-show']);
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
		self::assertSame(expected: 200, actual: $result->getStatus());
		self::assertSame(expected: 'att-1', actual: $capturedUuid);
	}

	/**
	 * Run a check-out over a prior record and capture what is stored.
	 *
	 * @param array<string,mixed> $prior The current attendance record.
	 *
	 * @return array{0: JSONResponse, 1: array<string,mixed>|null} The response and the stored data.
	 */
	private function checkOutOver(array $prior): array {
		$this->asUser(uid: 'gm-anna', isGm: true);
		$this->withParams(params: [
			'character' => self::CHAR,
			'status' => 'checked-out',
			'checkedOutBy' => 'attacker',
			'checkedOutAt' => '1999-01-01T00:00:00+00:00',
		]);
		$this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'players' => [self::CHAR]]);
		$this->objectFetcher->method('getObjects')->willReturn([
			['id' => 'att-1', 'event' => self::EVT, 'character' => self::CHAR] + $prior,
		]);

		$captured = null;
		$this->objectFetcher->method('saveObject')->willReturnCallback(
			function (string $objectType, array $data, ?string $uuid) use (&$captured): array {
				$captured = $data + ['uuid' => $uuid];
				return $data;
			}
		);

		return [$this->controller()->recordAttendance(self::EVT), $captured];
	}

	/**
	 * A check-out keeps the check-in stamps and stamps its own from the server.
	 *
	 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
	 *
	 * @return void
	 */
	public function testCheckOutStampsTheServerAndKeepsTheCheckIn(): void {
		[$result, $stored] = $this->checkOutOver(prior: [
			'status' => 'checked-in',
			'checkedInAt' => '2026-12-05T09:00:00+01:00',
			'checkedInBy' => 'gm-bram',
		]);

		self::assertSame(expected: 200, actual: $result->getStatus());
		self::assertSame(expected: 'att-1', actual: $stored['uuid']);
		self::assertSame(expected: 'checked-out', actual: $stored['status']);
		self::assertSame(expected: '2026-12-05T09:00:00+01:00', actual: $stored['checkedInAt']);
		self::assertSame(expected: 'gm-bram', actual: $stored['checkedInBy']);
		self::assertSame(expected: 'gm-anna', actual: $stored['checkedOutBy']);
		self::assertNotSame(expected: '1999-01-01T00:00:00+00:00', actual: $stored['checkedOutAt']);
		self::assertMatchesRegularExpression(pattern: '/^\d{4}-\d{2}-\d{2}T/', string: $stored['checkedOutAt']);
	}

	/**
	 * A check-out of someone who is not checked in is refused, and nothing is stored.
	 *
	 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
	 *
	 * @return void
	 */
	public function testCheckOutFromRegisteredIsRefused(): void {
		$this->objectFetcher->expects(self::never())->method('saveObject');
		[$result, $stored] = $this->checkOutOver(prior: ['status' => 'registered']);

		self::assertSame(expected: 422, actual: $result->getStatus());
		self::assertStringContainsString(needle: 'Invalid status change', haystack: $result->getData()['error']);
		self::assertNull(actual: $stored);
	}

	/**
	 * A check-out with no attendance record at all is refused too.
	 *
	 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
	 *
	 * @return void
	 */
	public function testCheckOutWithoutARecordIsRefused(): void {
		$this->asUser(uid: 'gm-anna', isGm: true);
		$this->withParams(params: ['character' => self::CHAR, 'status' => 'checked-out']);
		$this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'players' => [self::CHAR]]);
		$this->objectFetcher->method('getObjects')->willReturn([]);
		$this->objectFetcher->expects(self::never())->method('saveObject');

		self::assertSame(expected: 422, actual: $this->controller()->recordAttendance(self::EVT)->getStatus());
	}

	/**
	 * Checking in again after a check-out clears the check-out stamps.
	 *
	 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
	 *
	 * @return void
	 */
	public function testANewCheckInClearsTheCheckOut(): void {
		$this->asUser(uid: 'gm-anna', isGm: true);
		$this->withParams(params: ['character' => self::CHAR, 'status' => 'checked-in']);
		$this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'players' => [self::CHAR]]);
		$this->objectFetcher->method('getObjects')->willReturn([
			[
				'id' => 'att-1', 'event' => self::EVT, 'character' => self::CHAR, 'status' => 'checked-out',
				'checkedOutAt' => '2026-12-05T22:10:00+01:00', 'checkedOutBy' => 'gm-anna',
			],
		]);
		$stored = null;
		$this->objectFetcher->method('saveObject')->willReturnCallback(
			function (string $objectType, array $data, ?string $uuid) use (&$stored): array {
				$stored = $data;
				return $data;
			}
		);

		self::assertSame(expected: 200, actual: $this->controller()->recordAttendance(self::EVT)->getStatus());
		self::assertSame(expected: 'checked-in', actual: $stored['status']);
		self::assertArrayNotHasKey(key: 'checkedOutAt', array: $stored);
		self::assertArrayNotHasKey(key: 'checkedOutBy', array: $stored);
		self::assertSame(expected: 'gm-anna', actual: $stored['checkedInBy']);
	}

	/**
	 * Record degrades to424 when schema absent.
	 *
	 * @return void
	 */
	public function testRecordDegradesTo424WhenSchemaAbsent(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->withParams(params: ['character' => self::CHAR, 'status' => 'checked-in']);
		$this->objectFetcher->method('getObject')->willReturn(
			['id' => self::EVT, 'players' => [self::CHAR]]
		);
		// Attendance schema not configured → loadAttendance reports unavailable.
		$this->objectFetcher->method('getObjects')->willThrowException(new Exception('not configured'));
		$this->objectFetcher->expects(self::never())->method('saveObject');

		$result = $this->controller()->recordAttendance(self::EVT);
		self::assertSame(expected: 424, actual: $result->getStatus());
	}

	// ---- roster -----------------------------------------------------------

	/**
	 * Reading the roster without a signed-in user answers 401.
	 *
	 * @return void
	 */
	public function testRosterUnauthenticatedReturns401(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$result = $this->controller()->roster(self::EVT);
		self::assertSame(expected: 401, actual: $result->getStatus());
	}

	/**
	 * Roster event not found returns404.
	 *
	 * @return void
	 */
	public function testRosterEventNotFoundReturns404(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->objectFetcher->method('getObject')->willThrowException(new Exception('nope'));
		$result = $this->controller()->roster(self::EVT);
		self::assertSame(expected: 404, actual: $result->getStatus());
	}

	/**
	 * Roster lists participants with status.
	 *
	 * @return void
	 */
	public function testRosterListsParticipantsWithStatus(): void {
		$this->asUser(uid: 'gm1', isGm: true);
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
		self::assertSame(expected: 200, actual: $result->getStatus());
		$data = $result->getData();
		self::assertTrue(condition: $data['attendanceAvailable']);
		self::assertTrue(condition: $data['isGm']);
		self::assertCount(expectedCount: 1, haystack: $data['participants']);
		self::assertSame(expected: 'Aldric', actual: $data['participants'][0]['name']);
		self::assertSame(expected: 'checked-in', actual: $data['participants'][0]['status']);
		self::assertSame(expected: 'Alice', actual: $data['participants'][0]['playerName']);
	}

	/**
	 * Roster degrades when attendance unavailable.
	 *
	 * @return void
	 */
	public function testRosterDegradesWhenAttendanceUnavailable(): void {
		// A player (non-GM) viewing an event whose attendance schema is absent.
		$this->asUser(uid: 'player1', isGm: false);
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
		self::assertSame(expected: 200, actual: $result->getStatus());
		$data = $result->getData();
		// Degraded: participant still listed read-only, no attendance, not GM.
		self::assertFalse(condition: $data['attendanceAvailable']);
		self::assertFalse(condition: $data['isGm']);
		self::assertCount(expectedCount: 1, haystack: $data['participants']);
		self::assertSame(expected: 'registered', actual: $data['participants'][0]['status']);
	}
}
