<?php

/**
 * StaffTeam: the members of a role and removing one.
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

use OCA\Larpinq\Service\StaffTeam;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * REQ-ASR-005 team listing and removal, by the extracted collaborator.
 */
#[CoversClass(StaffTeam::class)]
class StaffTeamTest extends TestCase {

	/**
	 * A user mock.
	 *
	 * @param string $uid The uid.
	 * @param string $name The display name.
	 *
	 * @return IUser
	 */
	private function user(string $uid, string $name): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($name);
		return $user;
	}//end user()

	/**
	 * Members come back sorted by display name, case-insensitively; a missing group has none.
	 *
	 * @return void
	 */
	public function testMembersSortedByName(): void {
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$this->user(uid: 'z', name: 'zoe'), $this->user(uid: 'a', name: 'Anna')]);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->willReturnCallback(static fn (string $gid): ?IGroup => $gid === 'crew' ? $group : null);
		$team = new StaffTeam(groupManager: $groups, userManager: $this->createMock(IUserManager::class));

		$this->assertSame([['uid' => 'a', 'displayName' => 'Anna'], ['uid' => 'z', 'displayName' => 'zoe']], $team->members(role: 'crew'));
		$this->assertSame([], $team->members(role: 'gone'));
	}//end testMembersSortedByName()

	/**
	 * Removing takes out a member, and is false for a non-member or a missing user or group.
	 *
	 * @return void
	 */
	public function testRemoveFrom(): void {
		$anna = $this->user(uid: 'anna', name: 'Anna');
		$bob  = $this->user(uid: 'bob', name: 'Bob');
		$group = $this->createMock(IGroup::class);
		$group->method('inGroup')->willReturnCallback(static fn (IUser $user): bool => $user === $anna);
		$group->expects($this->once())->method('removeUser')->with($anna);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->willReturnCallback(static fn (string $gid): ?IGroup => $gid === 'crew' ? $group : null);
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(static fn (string $uid): ?IUser => ['anna' => $anna, 'bob' => $bob][$uid] ?? null);
		$team = new StaffTeam(groupManager: $groups, userManager: $users);

		$this->assertTrue($team->removeFrom(role: 'crew', uid: 'anna'));
		$this->assertFalse($team->removeFrom(role: 'crew', uid: 'bob'));
		$this->assertFalse($team->removeFrom(role: 'crew', uid: 'nobody'));
		$this->assertFalse($team->removeFrom(role: 'gone', uid: 'anna'));
	}//end testRemoveFrom()
}//end class
