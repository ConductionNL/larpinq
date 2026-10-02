<?php

/**
 * Larpinq Check-in Code Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Service\CheckinCodes;
use OCA\Larpinq\Service\CodeCheckin;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use PHPUnit\Framework\TestCase;

/**
 * Every accepted registration gets a check-in code through the real
 * registration listener, and a steward checks a participant in with it.
 */
class CheckinCodeTest extends TestCase {

	private const REG = 'e0000000-0000-4000-8000-000000000001';

	private const MIRELA = 'd0000000-0000-4000-8000-000000000001';

	private const OTHER_EVENT = 'a0000000-0000-4000-8000-000000000002';

	private const CODE_FORMAT = '/^[A-Z2-7]{26}$/';

	private PaymentWorld $world;

	protected function setUp(): void {
		$this->world = new PaymentWorld($this);
		$this->world->store->seed('character', ['id' => self::MIRELA, 'name' => 'Mirela the Wanderer']);
		$this->world->store->seed('event', ['id' => self::OTHER_EVENT, 'name' => 'Spring Fair 2027', 'players' => []]);
	}//end setUp()

	/**
	 * Anna's pending registration gets a code when a game master accepts it.
	 *
	 * @return void
	 */
	public function testAcceptingGivesACode(): void {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::ANNA, status: 'pending');
		$this->assertArrayNotHasKey('checkinCode', $this->world->registration(id: self::REG));

		$stored = $this->world->update(id: self::REG, data: ['status' => 'accepted']);

		$this->assertMatchesRegularExpression(self::CODE_FORMAT, (string)$stored['checkinCode']);
	}//end testAcceptingGivesACode()

	/**
	 * A registration created accepted gets a code; one that waits does not.
	 *
	 * @return void
	 */
	public function testACreatedRegistrationHasACodeOnlyWhenAccepted(): void {
		$this->world->store->seed('tickettype', ['id' => PaymentWorld::TICKET, 'event' => PaymentWorld::EVENT, 'name' => 'Player', 'role' => 'player', 'amount' => 8500, 'currency' => 'EUR', 'hidden' => false]);
		$accepted = $this->world->fetcher()->saveObjectWithAppAuthority('registration', ['event' => PaymentWorld::EVENT, 'player' => PaymentWorld::ANNA, 'playerUid' => 'anna', 'ticketType' => PaymentWorld::TICKET]);
		$this->assertSame('accepted', $accepted['status']);
		$this->assertMatchesRegularExpression(self::CODE_FORMAT, (string)$accepted['checkinCode']);

		$this->world->store->seed('event', ['id' => self::OTHER_EVENT, 'name' => 'Spring Fair 2027', 'approvalRequired' => true, 'players' => []]);
		$pending = $this->world->fetcher()->saveObjectWithAppAuthority('registration', ['event' => self::OTHER_EVENT, 'player' => PaymentWorld::SANNE, 'playerUid' => 'sanne', 'checkinCode' => 'AAAAAAAAAAAAAAAAAAAAAAAAAA']);
		$this->assertSame('pending', $pending['status']);
		$this->assertSame('', (string)($pending['checkinCode'] ?? ''), 'a registration that waits has no code, whatever a client sent');
	}//end testACreatedRegistrationHasACodeOnlyWhenAccepted()

	/**
	 * A code is the server's: an update cannot replace it, and a new holder gets a new one.
	 *
	 * @return void
	 */
	public function testTheCodeIsKeptAndRotatesWithTheHolder(): void {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::ANNA, status: 'pending');
		$code = (string)$this->world->update(id: self::REG, data: ['status' => 'accepted'])['checkinCode'];

		$kept = $this->world->update(id: self::REG, data: ['checkinCode' => 'AAAAAAAAAAAAAAAAAAAAAAAAAA', 'notes' => 'late arrival']);
		$this->assertSame($code, $kept['checkinCode']);

		$moved = $this->world->update(id: self::REG, data: ['player' => PaymentWorld::PIETER, 'playerUid' => 'pieter']);
		$this->assertMatchesRegularExpression(self::CODE_FORMAT, (string)$moved['checkinCode']);
		$this->assertNotSame($code, $moved['checkinCode'], 'the old holder cannot check in with the code they kept');
	}//end testTheCodeIsKeptAndRotatesWithTheHolder()

	/**
	 * Codes are random: a thousand never collide, and all are well formed.
	 *
	 * @return void
	 */
	public function testCodesAreRandomAndWellFormed(): void {
		$codes = new CheckinCodes();
		$seen = [];
		for ($i = 0; $i < 1000; $i++) {
			$code = $codes->generate();
			$this->assertMatchesRegularExpression(self::CODE_FORMAT, $code);
			$seen[$code] = true;
		}

		$this->assertCount(1000, $seen);
		$this->assertSame('ABCDEFGHJKMNPQRSTVWXYZ2345', $codes->normalise(code: ' abcd-efgh jkmn pqrs tvwx yz23 45 '), 'case, spaces and dashes are ignored');
		$this->assertSame('', $codes->normalise(code: 'not a code'));
	}//end testCodesAreRandomAndWellFormed()

	/**
	 * Anna arrives at the gate: her code checks Mirela in; a second scan says when and by whom.
	 *
	 * @return void
	 */
	public function testAnnaIsCheckedInOnceByHerCode(): void {
		$code = $this->acceptedAnna();

		$first = $this->checkin()->checkIn(eventId: PaymentWorld::EVENT, code: strtolower($code), actingUid: 'gm');
		$this->assertSame(200, $first['status']);
		$this->assertSame(['status' => 'checked-in', 'name' => 'Anna', 'character' => 'Mirela the Wanderer'], $first['body']);
		$attendance = array_values($this->world->store->objects['attendance'] ?? []);
		$this->assertCount(1, $attendance);
		$this->assertSame(['event' => PaymentWorld::EVENT, 'character' => self::MIRELA, 'status' => 'checked-in', 'checkedInBy' => 'gm'], array_intersect_key($attendance[0], array_flip(['event', 'character', 'status', 'checkedInBy'])));

		$second = $this->checkin()->checkIn(eventId: PaymentWorld::EVENT, code: $code, actingUid: 'joris');
		$this->assertSame(200, $second['status']);
		$this->assertSame('already', $second['body']['status']);
		$this->assertSame('gm', $second['body']['by']);
		$this->assertSame($attendance[0]['checkedInAt'], $second['body']['at']);
		$this->assertCount(1, $this->world->store->objects['attendance'], 'a second scan writes nothing');
	}//end testAnnaIsCheckedInOnceByHerCode()

	/**
	 * An unknown code, another event's code, a malformed code, a registration
	 * that is no longer accepted, and one without a character check nobody in.
	 *
	 * @return void
	 */
	public function testACodeThatCannotCheckAnyoneInChecksNobodyIn(): void {
		$code = $this->acceptedAnna();

		$this->assertSame([404, 'unknown'], $this->outcome(eventId: PaymentWorld::EVENT, code: 'ZZZZZZZZZZZZZZZZZZZZZZZZZZ'));
		$this->assertSame([404, 'unknown'], $this->outcome(eventId: self::OTHER_EVENT, code: $code));
		$this->assertSame([404, 'unknown'], $this->outcome(eventId: PaymentWorld::EVENT, code: ''));

		$this->world->store->objects['registration'][self::REG]['character'] = '';
		$this->assertSame([409, 'no-character'], $this->outcome(eventId: PaymentWorld::EVENT, code: $code));

		$this->world->store->objects['registration'][self::REG]['status'] = 'cancelled';
		$this->assertSame([409, 'not-accepted'], $this->outcome(eventId: PaymentWorld::EVENT, code: $code));
		$this->assertSame([], $this->world->store->objects['attendance'] ?? []);
	}//end testACodeThatCannotCheckAnyoneInChecksNobodyIn()

	/**
	 * Anna, accepted for Winter Court 2026 with Mirela.
	 *
	 * @return string Her code.
	 */
	private function acceptedAnna(): string {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::ANNA, status: 'pending', more: ['character' => self::MIRELA]);
		return (string)$this->world->update(id: self::REG, data: ['status' => 'accepted'])['checkinCode'];
	}//end acceptedAnna()

	/**
	 * The check-in over the world's register.
	 *
	 * @return CodeCheckin The service.
	 */
	private function checkin(): CodeCheckin {
		return new CodeCheckin($this->world->fetcher(), new EventRosterService($this->world->fetcher()), new CheckinCodes());
	}//end checkin()

	/**
	 * The status code and result of one check-in.
	 *
	 * @param string $eventId The event.
	 * @param string $code The code.
	 *
	 * @return array{0: int, 1: string} The status code and the result.
	 */
	private function outcome(string $eventId, string $code): array {
		$result = $this->checkin()->checkIn(eventId: $eventId, code: $code, actingUid: 'gm');
		return [$result['status'], (string)$result['body']['status']];
	}//end outcome()
}//end class
