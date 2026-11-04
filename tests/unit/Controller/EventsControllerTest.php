<?php

/**
 * Unit tests for EventsController (run-sheet export).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use Exception;
use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\EventsController;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
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
class EventsControllerTest extends TestCase {
	private const TPL = '00000000-0000-0000-0000-000000000010';
	private const EVT = '00000000-0000-0000-0000-0000000000ev';

	/** @var RegisterObjectFetcher&MockObject The object fetcher. */
	private RegisterObjectFetcher&MockObject $objectFetcher;
	/** @var DocuDeskPdfRenderer&MockObject The pdf renderer. */
	private DocuDeskPdfRenderer&MockObject $pdfRenderer;
	/** @var IUserSession&MockObject The user session. */
	private IUserSession&MockObject $userSession;
	/** @var IGroupManager&MockObject The group manager. */
	private IGroupManager&MockObject $groupManager;

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
	}

	/**
	 * The controller over the doubles.
	 *
	 * @return EventsController The controller over the doubles.
	 */
	private function controller(): EventsController {
		// The real EventRosterService over the mocked fetcher: the roster /
		// run-sheet rules stay under test end-to-end through the controller.
		return new EventsController(
			request: $this->createMock(originalClassName: IRequest::class),
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
	 * Returns401 when not authenticated.
	 *
	 * @return void
	 */
	public function testReturns401WhenNotAuthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
		self::assertSame(expected: 401, actual: $result->getStatus());
	}

	/**
	 * Returns403 for non GM.
	 *
	 * @return void
	 */
	public function testReturns403ForNonGm(): void {
		$this->asUser(uid: 'player1', isGm: false, isAdmin: false);
		$result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 403, actual: $result->getStatus());
	}

	/**
	 * Returns424 when docu desk absent.
	 *
	 * @return void
	 */
	public function testReturns424WhenDocuDeskAbsent(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		// The run-sheet download validates the template id BEFORE probing for
		// DocuDesk, so the id must normalise successfully for the 424 branch to
		// be reachable at all. Without this stub the mock returns null and the
		// method short-circuits at the 400 — the assertion below would then be
		// green for the wrong reason.
		$this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
		$this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(false);
		$result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
		self::assertSame(expected: 424, actual: $result->getStatus());
	}

	/**
	 * Returns400 for non UUID template.
	 *
	 * @return void
	 */
	public function testReturns400ForNonUuidTemplate(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
		$this->pdfRenderer->method('normaliseTemplateId')->willReturn(null);
		$result = $this->controller()->downloadRunsheet(self::EVT, 'not-a-uuid');
		self::assertSame(expected: 400, actual: $result->getStatus());
	}

	/**
	 * A malformed template id is a 400 even when DocuDesk is ABSENT.
	 *
	 * This is the combination no test covered, and the gap is exactly why the
	 * bug survived: every existing case fixed one guard while leaving the other
	 * in its passing state, so either guard order satisfied all of them. On CI
	 * — where DocuDesk is not installed — the availability probe ran first and
	 * answered 424 to every request, making the documented 400 contract
	 * unreachable and hiding the fact that a crafted template value was never
	 * rejected on its own merits. Reverting the guard order in
	 * EventsController::downloadRunsheet() turns this test red (424 != 400).
	 *
	 * @return void
	 */
	public function testReturns400ForNonUuidTemplateEvenWhenDocuDeskAbsent(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(false);
		$this->pdfRenderer->method('normaliseTemplateId')->willReturn(null);
		$result = $this->controller()->downloadRunsheet(self::EVT, 'not-a-uuid');
		self::assertSame(expected: 400, actual: $result->getStatus());
	}

	/**
	 * Returns404 when event not found.
	 *
	 * @return void
	 */
	public function testReturns404WhenEventNotFound(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
		$this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
		$this->objectFetcher->method('getObject')->willThrowException(new Exception('nope'));
		$result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
		self::assertSame(expected: 404, actual: $result->getStatus());
	}

	/**
	 * Returns404 when template not found.
	 *
	 * @return void
	 */
	public function testReturns404WhenTemplateNotFound(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
		$this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
		$this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'name' => 'Summer LARP']);
		$this->pdfRenderer->method('getTemplate')->willReturn(null);
		$result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
		self::assertSame(expected: 404, actual: $result->getStatus());
	}

	/**
	 * Success returns PDF named after event.
	 *
	 * @return void
	 */
	public function testSuccessReturnsPdfNamedAfterEvent(): void {
		$this->asUser(uid: 'gm1', isGm: true);
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
		self::assertInstanceOf(expected: DataDownloadResponse::class, actual: $result);

		// Cast contains the two participants, sorted by name (Borin before Zara).
		self::assertSame(expected: 2, actual: $captured['castCount']);
		self::assertSame(expected: 'Borin', actual: $captured['cast'][0]['name']);
		self::assertSame(expected: 'Zara', actual: $captured['cast'][1]['name']);
		// Unique items rollup deduplicates i1 across both characters.
		self::assertEqualsCanonicalizing(expected: ['i1', 'i2'], actual: $captured['uniqueItemsInPlay']);
		// Player name falls back to ocName.
		self::assertSame(expected: 'Alice', actual: $captured['cast'][1]['playerName']);
	}

	/**
	 * Filename fallback when event unnamed.
	 *
	 * @return void
	 */
	public function testFilenameFallbackWhenEventUnnamed(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
		$this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
		$this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT]);
		$this->objectFetcher->method('getObjects')->willReturn([]);
		$this->pdfRenderer->method('getTemplate')->willReturn(['content' => '', 'format' => 'A4', 'orientation' => 'P']);
		$this->pdfRenderer->method('render')->willReturn('%PDF mock');

		$result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
		self::assertInstanceOf(expected: DataDownloadResponse::class, actual: $result);
	}

	/**
	 * Returns500 when render fails.
	 *
	 * @return void
	 */
	public function testReturns500WhenRenderFails(): void {
		$this->asUser(uid: 'gm1', isGm: true);
		$this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(true);
		$this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
		$this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'name' => 'Summer LARP']);
		$this->objectFetcher->method('getObjects')->willReturn([]);
		$this->pdfRenderer->method('getTemplate')->willReturn(['content' => '', 'format' => 'A4', 'orientation' => 'P']);
		$this->pdfRenderer->method('render')->willReturn(null);

		$result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
		self::assertSame(expected: 500, actual: $result->getStatus());
	}

	/**
	 * Admin without GM group allowed.
	 *
	 * @return void
	 */
	public function testAdminWithoutGmGroupAllowed(): void {
		// An NC admin (legacy GM) is allowed even without the gamemasters group.
		$this->asUser(uid: 'admin', isGm: false, isAdmin: true);
		$this->pdfRenderer->method('normaliseTemplateId')->willReturn(self::TPL);
		$this->pdfRenderer->method('isDocuDeskAvailable')->willReturn(false);
		$result = $this->controller()->downloadRunsheet(self::EVT, self::TPL);
		// Passes the GM guard and template validation, then hits the 424
		// (DocuDesk absent) — proves not 403.
		self::assertSame(expected: 424, actual: $result->getStatus());
	}
}
