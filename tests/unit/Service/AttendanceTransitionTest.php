<?php

/**
 * Unit tests for AttendanceTransition
 *
 * @category Tests
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\AttendanceTransition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Which status may follow which, and what a status stores.
 *
 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
 */
class AttendanceTransitionTest extends TestCase {

	/**
	 * A check-out only follows a check-in; every other status follows anything.
	 *
	 * @param array<string,mixed> $prior The current record.
	 * @param string $status The status asked for.
	 * @param bool $allowed Whether it is allowed.
	 *
	 * @return void
	 */
	#[DataProvider('transitions')]
	public function testWhichStatusMayFollow(array $prior, string $status, bool $allowed): void {
		self::assertSame(expected: $allowed, actual: (new AttendanceTransition())->allows(prior: $prior, status: $status));
	}//end testWhichStatusMayFollow()

	/**
	 * The transitions.
	 *
	 * @return array<string, array{array<string,mixed>, string, bool}>
	 */
	public static function transitions(): array {
		return [
			'check out a checked-in participant' => [['status' => 'checked-in'], 'checked-out', true],
			'check out someone registered' => [['status' => 'registered'], 'checked-out', false],
			'check out a no-show' => [['status' => 'no-show'], 'checked-out', false],
			'check out twice' => [['status' => 'checked-out'], 'checked-out', false],
			'check out without a record' => [[], 'checked-out', false],
			'check in again after a check-out' => [['status' => 'checked-out'], 'checked-in', true],
			'no-show from anything' => [['status' => 'checked-out'], 'no-show', true],
			'check in without a record' => [[], 'checked-in', true],
		];
	}//end transitions()

	/**
	 * A check-out keeps the check-in stamps and adds its own.
	 *
	 * @return void
	 */
	public function testACheckOutKeepsTheCheckInStamps(): void {
		$payload = (new AttendanceTransition())->payload(
			ids: ['event' => 'e1', 'character' => 'isolde'],
			status: 'checked-out',
			stamp: ['uid' => 'anna', 'at' => '2026-12-05T22:10:00+01:00'],
			prior: ['status' => 'checked-in', 'checkedInAt' => '2026-12-05T09:00:00+01:00', 'checkedInBy' => 'bram', 'checkedOutBy' => 'stale']
		);

		self::assertSame(
			expected: [
				'event' => 'e1',
				'character' => 'isolde',
				'status' => 'checked-out',
				'checkedInAt' => '2026-12-05T09:00:00+01:00',
				'checkedInBy' => 'bram',
				'checkedOutAt' => '2026-12-05T22:10:00+01:00',
				'checkedOutBy' => 'anna',
			],
			actual: $payload
		);
	}//end testACheckOutKeepsTheCheckInStamps()

	/**
	 * A check-out over a record without check-in stamps stores none.
	 *
	 * @return void
	 */
	public function testACheckOutWithoutCheckInStampsAddsNone(): void {
		$payload = (new AttendanceTransition())->payload(
			ids: ['event' => 'e1', 'character' => 'isolde'],
			status: 'checked-out',
			stamp: ['uid' => 'anna', 'at' => 'now'],
			prior: ['status' => 'checked-in', 'checkedInAt' => '', 'checkedInBy' => 7]
		);

		self::assertArrayNotHasKey(key: 'checkedInAt', array: $payload);
		self::assertArrayNotHasKey(key: 'checkedInBy', array: $payload);
	}//end testACheckOutWithoutCheckInStampsAddsNone()

	/**
	 * Any other status stamps the check-in fields and drops the check-out ones.
	 *
	 * @return void
	 */
	public function testACheckInClearsTheCheckOut(): void {
		$payload = (new AttendanceTransition())->payload(
			ids: ['event' => 'e1', 'character' => 'isolde'],
			status: 'checked-in',
			stamp: ['uid' => 'anna', 'at' => 'now'],
			prior: ['status' => 'checked-out', 'checkedOutAt' => 'then', 'checkedOutBy' => 'anna']
		);

		self::assertSame(
			expected: ['event' => 'e1', 'character' => 'isolde', 'status' => 'checked-in', 'checkedInAt' => 'now', 'checkedInBy' => 'anna'],
			actual: $payload
		);
	}//end testACheckInClearsTheCheckOut()
}//end class
