<?php

/**
 * Stewards at the gate: they check people in and do not get the run sheet.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-stewards-check-people-in-req-asr-003
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\EventsController;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Support\AppIdentity;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * A member of `larpinq-stewards` only, with no game master or admin rights.
 */
class EventsControllerStewardTest extends TestCase {
	private const EVT = '00000000-0000-0000-0000-0000000000ev';
	private const CHAR = '00000000-0000-0000-0000-00000000char';

	private RegisterObjectFetcher&MockObject $objectFetcher;
	private DocuDeskPdfRenderer&MockObject $pdfRenderer;
	private IGroupManager&MockObject $groupManager;
	private IUserSession&MockObject $userSession;
	private IRequest&MockObject $request;

	/**
	 * Joris, signed in, in these groups only.
	 *
	 * @param list<string> $groups The groups Joris is in.
	 *
	 * @return EventsController
	 */
	private function controllerFor(array $groups): EventsController {
		$this->objectFetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->pdfRenderer = $this->createMock(DocuDeskPdfRenderer::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->groupManager->method('isInGroup')->willReturnCallback(
			static fn (string $uid, string $group): bool => $uid === 'joris' && in_array($group, $groups, true)
		);
		$this->groupManager->method('isAdmin')->willReturn(false);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('joris');
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->method('getUser')->willReturn($user);
		$this->request = $this->createMock(IRequest::class);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $key, $default = null) => (['character' => self::CHAR, 'status' => 'checked-in'][$key] ?? $default)
		);

		return new EventsController(
			request: $this->request,
			rosterService: new EventRosterService(objectFetcher: $this->objectFetcher),
			pdfRenderer: $this->pdfRenderer,
			callerAccess: new CallerAccess(userSession: $this->userSession, groupManager: $this->groupManager),
		);
	}

	/**
	 * A steward checks Mirela in; the record names the steward.
	 *
	 * @return void
	 */
	public function testAStewardChecksSomeoneIn(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::ROLE_STEWARDS]);
		$this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'name' => 'Winter Court 2026', 'players' => [self::CHAR]]);
		$this->objectFetcher->method('getObjects')->willReturn([]);
		$saved = null;
		$this->objectFetcher->expects(self::once())->method('saveObject')->willReturnCallback(
			function (string $objectType, array $data, ?string $uuid) use (&$saved): array {
				$saved = $data;
				return $data;
			}
		);

		$result = $controller->recordAttendance(self::EVT);

		self::assertSame(200, $result->getStatus());
		self::assertSame('checked-in', $saved['status']);
		self::assertSame('joris', $saved['checkedInBy']);
	}

	/**
	 * A steward does not get the run sheet, and the renderer is never asked.
	 *
	 * @return void
	 */
	public function testAStewardCannotDownloadTheRunSheet(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::ROLE_STEWARDS]);
		$this->pdfRenderer->expects(self::never())->method('normaliseTemplateId');

		$result = $controller->downloadRunsheet(self::EVT, '00000000-0000-4000-8000-000000000001');

		self::assertSame(403, $result->getStatus());
	}

	/**
	 * Another staff role does not check people in.
	 *
	 * @return void
	 */
	public function testAWriterCannotCheckIn(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::ROLE_WRITERS]);
		$this->objectFetcher->expects(self::never())->method('saveObject');

		self::assertSame(403, $controller->recordAttendance(self::EVT)->getStatus());
	}

	/**
	 * The roster tells the page whether the caller may check in.
	 *
	 * @return void
	 */
	public function testTheRosterSaysAStewardMayCheckIn(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::ROLE_STEWARDS]);
		$this->objectFetcher->method('getObject')->willReturn(['id' => self::EVT, 'name' => 'Winter Court 2026', 'players' => []]);
		$this->objectFetcher->method('getObjects')->willReturn([]);

		$roster = $controller->roster(self::EVT)->getData();

		self::assertFalse($roster['isGm']);
		self::assertTrue($roster['canCheckIn']);
	}
}
