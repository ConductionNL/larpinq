<?php

/**
 * Golden test for the run-sheet download and the attendance write of EventsController.
 *
 * Each case calls downloadRunsheet() or recordAttendance() with scripted
 * roster, renderer, session and group answers, and records the response
 * (status, body or download name) and the roster calls the controller made,
 * so the acting user handed to recordAttendance is pinned. The request also
 * carries forged checkedInBy, actingUid and userId values, which must never
 * reach the write. Compared with fixtures/events-controller.golden.json. Set
 * RECORD_GOLDEN=1 to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\EventsController;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\AttendanceCheckIn;
use OCA\Larpinq\Service\EventRosterService;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The run-sheet and attendance endpoints, case by case.
 *
 * @covers \OCA\Larpinq\Controller\EventsController
 * @uses   \OCA\Larpinq\Controller\CallerAccess
 */
class EventsControllerGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/events-controller.golden.json';

	private const TEMPLATE = '0b6f1c7e-1d2a-4c3b-9e8f-112233445566';

	/**
	 * The cases.
	 *
	 * @return array<string,array<string,mixed>> Case name to case.
	 */
	private static function cases(): array {
		$cases = [
			'runsheet, signed out' => ['call' => 'runsheet', 'uid' => null],
			'runsheet, player' => ['call' => 'runsheet', 'uid' => 'player1'],
			'runsheet, admin' => ['call' => 'runsheet', 'uid' => 'admin1'],
			'runsheet, game master' => ['call' => 'runsheet'],
			'runsheet, invalid template' => ['call' => 'runsheet', 'template' => '../etc'],
			'runsheet, invalid template, player' => ['call' => 'runsheet', 'template' => '../etc', 'uid' => 'player1'],
			'runsheet, invalid template, no DocuDesk' => ['call' => 'runsheet', 'template' => '../etc', 'docudesk' => false],
			'runsheet, no DocuDesk' => ['call' => 'runsheet', 'docudesk' => false],
			'runsheet, no event' => ['call' => 'runsheet', 'event' => null],
			'runsheet, no template' => ['call' => 'runsheet', 'templateData' => null],
			'runsheet, render failed' => ['call' => 'runsheet', 'pdf' => null],
			'runsheet, unnamed event' => ['call' => 'runsheet', 'event' => ['id' => 'e1']],
			'runsheet, empty event name' => ['call' => 'runsheet', 'event' => ['id' => 'e1', 'name' => '']],
			'attendance, signed out' => ['call' => 'attendance', 'uid' => null],
			'attendance, player' => ['call' => 'attendance', 'uid' => 'player1'],
			'attendance, admin' => ['call' => 'attendance', 'uid' => 'admin1'],
			'attendance, game master' => ['call' => 'attendance'],
			'attendance, default status' => ['call' => 'attendance', 'params' => ['character' => 'c1']],
			'attendance, no-show' => ['call' => 'attendance', 'params' => ['character' => 'c1', 'status' => 'no-show']],
			'attendance, registered' => ['call' => 'attendance', 'params' => ['character' => 'c1', 'status' => 'registered']],
			'attendance, no character' => ['call' => 'attendance', 'params' => ['status' => 'checked-in']],
			'attendance, bad status' => ['call' => 'attendance', 'params' => ['character' => 'c1', 'status' => 'present']],
			'attendance, bad status, player' => ['call' => 'attendance', 'uid' => 'player1', 'params' => ['character' => 'c1', 'status' => 'present']],
			'attendance, no event' => ['call' => 'attendance', 'event' => null],
			'attendance, not a participant' => ['call' => 'attendance', 'participant' => false],
			'attendance, storage missing' => ['call' => 'attendance', 'saved' => null],
			'runsheet, lookalike game master' => ['call' => 'runsheet', 'uid' => 'GM1'],
			'attendance, lookalike game master' => ['call' => 'attendance', 'uid' => 'GM1'],
			'attendance, lookalike admin' => ['call' => 'attendance', 'uid' => 'Admin1'],
			'attendance, forged uid and id' => [
				'call' => 'attendance',
				'params' => ['character' => 'c1', 'status' => 'checked-in', 'uid' => 'forged', 'id' => 'other-event'],
			],
			'attendance, player with forged uid' => [
				'call' => 'attendance',
				'uid' => 'player1',
				'params' => ['character' => 'c1', 'uid' => 'gm1', 'userId' => 'gm1'],
			],
		];

		foreach ($cases as $name => $case) {
			$cases[$name] = $case + [
				'uid' => 'gm1',
				'template' => self::TEMPLATE,
				'docudesk' => true,
				'event' => ['id' => 'e1', 'name' => 'Winter Moot'],
				'templateData' => ['id' => self::TEMPLATE, 'name' => 'Runsheet'],
				'pdf' => '%PDF-1.7 run',
				'params' => ['character' => 'c1', 'status' => 'checked-in'],
				'participant' => true,
				'saved' => ['id' => 'a1', 'status' => 'checked-in'],
			];
		}

		return $cases;
	}//end cases()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach (self::cases() as $name => $case) {
			$actual[$name] = json_encode(value: $this->outcome(case: $case), flags: JSON_PRETTY_PRINT);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: JSON_PRETTY_PRINT)."\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()

	/**
	 * Run one case through the controller.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return array<string,mixed> The response and the roster calls.
	 */
	private function outcome(array $case): array {
		$calls = [];
		$controller = new EventsController(
			request: $this->request(params: $case['params']),
			rosterService: $this->roster(case: $case, calls: $calls),
			pdfRenderer: $this->renderer(case: $case, calls: $calls),
			callerAccess: $this->callerAccess(uid: $case['uid'])
		);

		if ($case['call'] === 'runsheet') {
			$response = $controller->downloadRunsheet(id: 'e1', template: $case['template']);
		} else {
			$response = $controller->recordAttendance(id: 'e1');
		}

		$outcome = ['status' => $response->getStatus(), 'calls' => $calls];
		if ($response instanceof DataDownloadResponse) {
			$headers = new \ReflectionProperty(Response::class, 'headers');
			$outcome['download'] = [$response->render(), $headers->getValue($response)['Content-Disposition'] ?? null];
		}

		if ($response instanceof JSONResponse) {
			$outcome['data'] = $response->getData();
		}

		return $outcome;
	}//end outcome()

	/**
	 * The request, with the case's body and forged identity fields.
	 *
	 * @param array<string,string> $params The body.
	 *
	 * @return IRequest The request.
	 */
	private function request(array $params): IRequest {
		$params += ['checkedInBy' => 'forged', 'checkedInAt' => '1999-01-01', 'actingUid' => 'forged', 'userId' => 'forged'];
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => ($params[$key] ?? $default)
		);

		return $request;
	}//end request()

	/**
	 * The roster service, recording each call.
	 *
	 * @param array<string,mixed> $case  The case.
	 * @param array<int,mixed>    $calls The call log.
	 *
	 * @return EventRosterService The double.
	 */
	private function roster(array $case, array &$calls): EventRosterService {
		$roster = $this->createMock(originalClassName: EventRosterService::class);
		$roster->method('getEvent')->willReturnCallback(
			function (string $eventId) use ($case, &$calls): ?array {
				$calls[] = ['getEvent', $eventId];
				return $case['event'];
			}
		);
		$roster->method('isParticipant')->willReturnCallback(
			function (string $eventId, string $characterId, array $event) use ($case, &$calls): bool {
				$calls[] = ['isParticipant', $eventId, $characterId, $event];
				return $case['participant'];
			}
		);
		$roster->method('recordAttendance')->willReturnCallback(
			function (AttendanceCheckIn $checkIn) use ($case, &$calls): ?array {
				$calls[] = ['recordAttendance', $checkIn->eventId, $checkIn->characterId, $checkIn->status, $checkIn->actingUid];
				return $case['saved'];
			}
		);
		$roster->method('buildRunsheetContext')->willReturnCallback(
			function (array $event, string $eventId) use (&$calls): array {
				$calls[] = ['buildRunsheetContext', $event, $eventId];
				return ['event' => $event, 'cast' => ['c1']];
			}
		);

		return $roster;
	}//end roster()

	/**
	 * The PDF renderer, recording each call.
	 *
	 * @param array<string,mixed> $case  The case.
	 * @param array<int,mixed>    $calls The call log.
	 *
	 * @return DocuDeskPdfRenderer The double.
	 */
	private function renderer(array $case, array &$calls): DocuDeskPdfRenderer {
		$renderer = $this->createMock(originalClassName: DocuDeskPdfRenderer::class);
		$renderer->method('normaliseTemplateId')->willReturnCallback(
			function (string $template) use (&$calls): ?string {
				$calls[] = ['normaliseTemplateId', $template];
				if (preg_match('/^[0-9a-f-]{36}$/', $template) !== 1) {
					return null;
				}

				return $template;
			}
		);
		$renderer->method('isDocuDeskAvailable')->willReturnCallback(
			function () use ($case, &$calls): bool {
				$calls[] = ['isDocuDeskAvailable'];
				return $case['docudesk'];
			}
		);
		$renderer->method('getTemplate')->willReturnCallback(
			function (string $templateId) use ($case, &$calls): ?array {
				$calls[] = ['getTemplate', $templateId];
				return $case['templateData'];
			}
		);
		$renderer->method('render')->willReturnCallback(
			function (array $templateData, array $context) use ($case, &$calls): ?string {
				$calls[] = ['render', $templateData, $context];
				return $case['pdf'];
			}
		);

		return $renderer;
	}//end renderer()

	/**
	 * The real caller access over a session and a group manager that know three users.
	 *
	 * @param string|null $uid The signed-in user, or null.
	 *
	 * @return CallerAccess The caller access.
	 */
	private function callerAccess(?string $uid): CallerAccess {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(fn (string $who): bool => $who === 'admin1');
		$groups->method('isInGroup')->willReturnCallback(
			fn (string $who, string $group): bool => $who === 'gm1' && $group === 'gamemasters'
		);

		return new CallerAccess(userSession: $session, groupManager: $groups);
	}//end callerAccess()
}//end class
