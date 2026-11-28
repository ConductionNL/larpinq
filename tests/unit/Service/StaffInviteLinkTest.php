<?php

/**
 * StaffInviteLink: the invite link and the per-invite redeem lock.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/staff-roles/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\StaffInviteLink;
use OCA\Larpinq\Service\StaffInviteService;
use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * REQ-ASR-004 link and lock, by the extracted collaborator.
 */
#[CoversClass(StaffInviteLink::class)]
#[UsesClass(StaffInviteService::class)]
class StaffInviteLinkTest extends TestCase {

	private const CODE = '0123456789abcdef0123456789abcdef';

	/**
	 * The link is the app page plus staff-invite/<token>.
	 *
	 * @return void
	 */
	public function testUrl(): void {
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->with('larpinq.dashboard.page')->willReturn('https://cloud.example/apps/larpinq/');
		$link = new StaffInviteLink(urlGenerator: $urls, lockingProvider: $this->createMock(originalClassName: ILockingProvider::class));

		$this->assertSame(expected: 'https://cloud.example/apps/larpinq/staff-invite/' . self::CODE, actual: $link->url(token: self::CODE));
	}//end testUrl()

	/**
	 * The lock is keyed by the token hash, taken exclusive, released, and false while held elsewhere.
	 *
	 * @return void
	 */
	public function testLockKeyedByHash(): void {
		$key = 'larpinq/staff-invite/' . hash('sha256', self::CODE);
		$this->assertSame(expected: $key, actual: StaffInviteLink::lockKey(token: self::CODE));
		$this->assertStringNotContainsString(needle: self::CODE, haystack: $key);

		$locks = $this->createMock(originalClassName: ILockingProvider::class);
		$locks->expects($this->once())->method('acquireLock')->with($key, ILockingProvider::LOCK_EXCLUSIVE);
		$locks->expects($this->once())->method('releaseLock')->with($key, ILockingProvider::LOCK_EXCLUSIVE);
		$link = new StaffInviteLink(urlGenerator: $this->createMock(originalClassName: IURLGenerator::class), lockingProvider: $locks);
		$this->assertTrue(condition: $link->lock(token: self::CODE));
		$link->unlock(token: self::CODE);

		$held = $this->createMock(originalClassName: ILockingProvider::class);
		$held->method('acquireLock')->willThrowException(new LockedException($key));
		$busy = new StaffInviteLink(urlGenerator: $this->createMock(originalClassName: IURLGenerator::class), lockingProvider: $held);
		$this->assertFalse(condition: $busy->lock(token: self::CODE));
	}//end testLockKeyedByHash()
}//end class
