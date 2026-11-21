<?php

/**
 * EventCapacityLock: one lock per event, held by this request.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\EventCapacityLock;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The per-event lock that capacity decisions are taken under.
 */
#[CoversClass(EventCapacityLock::class)]
class EventCapacityLockTest extends TestCase {

	/**
	 * A lock taken once is held: a second acquire asks nothing, release frees it once.
	 *
	 * @return void
	 */
	public function testAHeldLockIsTakenOnceAndReleasedOnce(): void {
		$locks = $this->createMock(originalClassName: ILockingProvider::class);
		$locks->expects($this->once())->method('acquireLock')->with('larpinq/event-capacity/e1', ILockingProvider::LOCK_EXCLUSIVE);
		$locks->expects($this->once())->method('releaseLock')->with('larpinq/event-capacity/e1', ILockingProvider::LOCK_EXCLUSIVE);
		$lock = new EventCapacityLock(locking: $locks, retry: ['attempts' => 3, 'waitMicros' => 0]);

		$this->assertTrue(condition: $lock->acquire(eventId: 'e1'));
		$this->assertTrue(condition: $lock->acquire(eventId: 'e1'));
		$lock->release(eventId: 'e1');
		$lock->release(eventId: 'e1');
	}//end testAHeldLockIsTakenOnceAndReleasedOnce()

	/**
	 * A busy lock is tried the given number of times, then given up; nothing is released.
	 *
	 * @return void
	 */
	public function testABusyLockIsTriedThenGivenUp(): void {
		$locks = $this->createMock(originalClassName: ILockingProvider::class);
		$locks->expects($this->exactly(count: 3))->method('acquireLock')->willThrowException(new LockedException('larpinq/event-capacity/e1'));
		$locks->expects($this->never())->method('releaseLock');
		$lock = new EventCapacityLock(locking: $locks, retry: ['attempts' => 3, 'waitMicros' => 0]);

		$this->assertFalse(condition: $lock->acquire(eventId: 'e1'));
		$lock->release(eventId: 'e1');
	}//end testABusyLockIsTriedThenGivenUp()

	/**
	 * A lock freed on a later try is taken.
	 *
	 * @return void
	 */
	public function testALockFreedOnALaterTryIsTaken(): void {
		$locks = $this->createMock(originalClassName: ILockingProvider::class);
		$calls = 0;
		$locks->method('acquireLock')->willReturnCallback(
			static function () use (&$calls): void {
				$calls++;
				if ($calls === 1) {
					throw new LockedException('larpinq/event-capacity/e2');
				}
			}
		);
		$lock = new EventCapacityLock(locking: $locks, retry: ['attempts' => 2, 'waitMicros' => 0]);

		$this->assertTrue(condition: $lock->acquire(eventId: 'e2'));
		$this->assertSame(expected: 2, actual: $calls);
	}//end testALockFreedOnALaterTryIsTaken()
}//end class
