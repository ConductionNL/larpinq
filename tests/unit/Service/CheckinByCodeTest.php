<?php

/**
 * Check-in by the code of a registration: every result.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-a-code-is-checked-in-once-and-only-for-its-event-req-eqc-004
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\EventsController;
use OCA\Larpinq\Service\CheckinByCode;
use OCA\Larpinq\Service\CheckinCode;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Larpinq\Service\CheckinByCode
 * @covers \OCA\Larpinq\Service\CheckinCode
 * @covers \OCA\Larpinq\Controller\EventsController::checkinByCode
 * @uses \OCA\Larpinq\Controller\CallerAccess
 */
class CheckinByCodeTest extends TestCase {

	private const EVENT = '11111111-1111-4111-8111-111111111111';
	private const OTHER_EVENT = '22222222-2222-4222-8222-222222222222';
	private const MIRELA = '33333333-3333-4333-8333-333333333333';
	private const CODE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

	/** @var RegisterObjectFetcher&MockObject */
	private RegisterObjectFetcher&MockObject $fetcher;

	/** @var EventRosterService&MockObject */
	private EventRosterService&MockObject $roster;

	/** @var list<array<string, mixed>> The registrations the fetcher holds. */
	private array $registrations = [];

	/** @var list<array<string, mixed>> The attendance records the fetcher holds. */
	private array $attendance = [];

	protected function setUp(): void {
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			fn (string $objectType, array $filters, ?int $limit = null): array => array_values(array_filter(
				$this->registrations,
				static fn (array $row): bool => ($row['checkinCode'] ?? '') === ($filters['checkinCode'] ?? null)
			))
		);
		$this->fetcher->method('getObjects')->willReturnCallback(
			fn (string $objectType, ?int $limit = null, ?int $offset = null, ?array $filters = []): array => array_values(array_filter(
				$this->attendance,
				static fn (array $row): bool => $row['event'] === $filters['event'] && $row['character'] === $filters['character']
			))
		);
		$this->fetcher->method('getObject')->willReturn(['id' => self::MIRELA, 'name' => 'Mirela the Wanderer']);

		$this->roster = $this->createMock(EventRosterService::class);
	}//end setUp()

	/**
	 * The service under test.
	 *
	 * @return CheckinByCode
	 */
	private function service(): CheckinByCode {
		$users = $this->createMock(IUserManager::class);
		$users->method('getDisplayName')->willReturnMap([['anna', 'Anna de Vries'], ['joris', 'Joris']]);

		return new CheckinByCode(fetcher: $this->fetcher, roster: $this->roster, users: $users);
	}//end service()

	/**
	 * Anna's accepted registration.
	 *
	 * @param string $status The status.
	 * @param string $event  The event.
	 *
	 * @return void
	 */
	private function anna(string $status = 'accepted', string $event = self::EVENT): void {
		$this->registrations[] = [
			'id' => '44444444-4444-4444-8444-444444444444',
			'event' => $event,
			'character' => self::MIRELA,
			'playerUid' => 'anna',
			'status' => $status,
			'checkinCode' => self::CODE,
		];
	}//end anna()

	public function testAnnaIsCheckedInWithHerNameAndCharacter(): void {
		$this->anna();
		$this->roster->expects($this->once())->method('recordAttendance')
			->with(self::EVENT, self::MIRELA, 'checked-in', 'gm')
			->willReturn(['status' => 'checked-in']);

		// Typed by hand, with spaces and in lower case.
		[$status, $answer] = $this->service()->checkIn(eventId: self::EVENT, code: ' abcd efgh ijkl mnop qrst uvwx yz ', actingUid: 'gm');

		$this->assertSame(200, $status);
		$this->assertSame(['status' => 'checked-in', 'name' => 'Anna de Vries', 'character' => 'Mirela the Wanderer'], $answer);
	}//end testAnnaIsCheckedInWithHerNameAndCharacter()

	public function testASecondUseSaysWhenAndByWhom(): void {
		$this->anna();
		$this->attendance[] = ['event' => self::EVENT, 'character' => self::MIRELA, 'status' => 'checked-in', 'checkedInAt' => '2026-12-12T18:02:00+01:00', 'checkedInBy' => 'joris'];
		$this->roster->expects($this->never())->method('recordAttendance');

		[$status, $answer] = $this->service()->checkIn(eventId: self::EVENT, code: self::CODE, actingUid: 'gm');

		$this->assertSame(200, $status);
		$this->assertSame(['status' => 'already', 'at' => '2026-12-12T18:02:00+01:00', 'by' => 'Joris'], $answer);
	}//end testASecondUseSaysWhenAndByWhom()

	public function testANoShowRecordDoesNotCountAsCheckedIn(): void {
		$this->anna();
		$this->attendance[] = ['event' => self::EVENT, 'character' => self::MIRELA, 'status' => 'no-show'];
		$this->roster->expects($this->once())->method('recordAttendance')->willReturn(['status' => 'checked-in']);

		[$status, $answer] = $this->service()->checkIn(eventId: self::EVENT, code: self::CODE, actingUid: 'gm');

		$this->assertSame([200, 'checked-in'], [$status, $answer['status']]);
	}//end testANoShowRecordDoesNotCountAsCheckedIn()

	public function testANotAcceptedRegistrationChecksNobodyIn(): void {
		$this->anna(status: 'waitlisted');
		$this->roster->expects($this->never())->method('recordAttendance');

		$this->assertSame([409, ['status' => 'not-accepted']], $this->service()->checkIn(eventId: self::EVENT, code: self::CODE, actingUid: 'gm'));
	}//end testANotAcceptedRegistrationChecksNobodyIn()

	public function testAnUnknownCodeOrOneOfAnotherEventReadsTheSame(): void {
		$this->anna(event: self::OTHER_EVENT);
		$this->roster->expects($this->never())->method('recordAttendance');

		$this->assertSame([404, ['status' => 'unknown']], $this->service()->checkIn(eventId: self::EVENT, code: self::CODE, actingUid: 'gm'));
		$this->assertSame([404, ['status' => 'unknown']], $this->service()->checkIn(eventId: self::EVENT, code: 'ZZZZZZZZZZZZZZZZZZZZZZZZZZ', actingUid: 'gm'));
		$this->assertSame([404, ['status' => 'unknown']], $this->service()->checkIn(eventId: self::EVENT, code: 'not a code', actingUid: 'gm'));
	}//end testAnUnknownCodeOrOneOfAnotherEventReadsTheSame()

	public function testMissingAttendanceStorageIsSaid(): void {
		$this->anna();
		$this->roster->method('recordAttendance')->willReturn(null);

		$this->assertSame([424, ['status' => 'unavailable']], $this->service()->checkIn(eventId: self::EVENT, code: self::CODE, actingUid: 'gm'));
	}//end testMissingAttendanceStorageIsSaid()

	public function testCodesAreBase32OfSixteenBytes(): void {
		$this->assertSame(str_repeat('A', 26), CheckinCode::encode(bytes: str_repeat("\0", 16)));
		$this->assertSame(str_repeat('7', 25) . '4', CheckinCode::encode(bytes: str_repeat("\xff", 16)));
		$this->assertMatchesRegularExpression('/^[A-Z2-7]{26}$/', CheckinCode::generate());
		$this->assertSame('', CheckinCode::normalise(code: 'ABC'));
		$this->assertSame('', CheckinCode::normalise(code: str_repeat('1', 26)));
	}//end testCodesAreBase32OfSixteenBytes()

	/**
	 * The controller: a player is refused before any code is read; a game master's code is checked in.
	 *
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-stewards-check-in-by-scanning-or-typing-a-code-req-eqc-003
	 */
	public function testThePlayerIsRefusedAndTheGameMasterChecksIn(): void {
		$this->anna();
		$this->roster->method('recordAttendance')->willReturn(['status' => 'checked-in']);

		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key, $default = null) => $key === 'code' ? self::CODE : $default);
		$controller = function (string $uid, bool $gm) use ($request): EventsController {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$session = $this->createMock(IUserSession::class);
			$session->method('getUser')->willReturn($user);
			$groups = $this->createMock(IGroupManager::class);
			$groups->method('isInGroup')->willReturn($gm);
			$groups->method('isAdmin')->willReturn(false);

			return new EventsController(
				request: $request,
				rosterService: $this->roster,
				pdfRenderer: $this->createMock(DocuDeskPdfRenderer::class),
				callerAccess: new CallerAccess(userSession: $session, groupManager: $groups),
				checkinByCode: $this->service(),
			);
		};

		$refused = $controller('karel', false)->checkinByCode(self::EVENT);
		$this->assertSame(403, $refused->getStatus());

		$done = $controller('gm', true)->checkinByCode(self::EVENT);
		$this->assertSame(200, $done->getStatus());
		$this->assertSame('Anna de Vries', $done->getData()['name']);

		$attributes = array_map(
			static fn (\ReflectionAttribute $a): string => $a->getName(),
			(new \ReflectionMethod(EventsController::class, 'checkinByCode'))->getAttributes()
		);
		$this->assertContains(\OCP\AppFramework\Http\Attribute\UserRateLimit::class, $attributes);
	}//end testThePlayerIsRefusedAndTheGameMasterChecksIn()
}//end class
