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
 * @spec openspec/specs/staff-roles/spec.md
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
		$user = $this->createMock(originalClassName: IUser::class);
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
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getUsers')->willReturn([$this->user(uid: 'z', name: 'zoe'), $this->user(uid: 'a', name: 'Anna')]);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('get')->willReturnMap([['crew', $group], ['gone', null]]);
		$team = new StaffTeam(groupManager: $groups, userManager: $this->createMock(originalClassName: IUserManager::class));

		$expected = [['uid' => 'a', 'displayName' => 'Anna'], ['uid' => 'z', 'displayName' => 'zoe']];
		$this->assertSame(expected: $expected, actual: $team->members(role: 'crew'));
		$this->assertSame(expected: [], actual: $team->members(role: 'gone'));
	}//end testMembersSortedByName()

	/**
	 * Removing takes out a member, and is false for a non-member or a missing user or group.
	 *
	 * @return void
	 */
	public function testRemoveFrom(): void {
		$anna = $this->user(uid: 'anna', name: 'Anna');
		$bob  = $this->user(uid: 'bob', name: 'Bob');
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('inGroup')->willReturnCallback(static fn (IUser $user): bool => $user === $anna);
		$group->expects($this->once())->method('removeUser')->with($anna);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('get')->willReturnMap([['crew', $group], ['gone', null]]);
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturnCallback(static fn (string $uid): ?IUser => ['anna' => $anna, 'bob' => $bob][$uid] ?? null);
		$team = new StaffTeam(groupManager: $groups, userManager: $users);

		$this->assertTrue(condition: $team->removeFrom(role: 'crew', uid: 'anna'));
		$this->assertFalse(condition: $team->removeFrom(role: 'crew', uid: 'bob'));
		$this->assertFalse(condition: $team->removeFrom(role: 'crew', uid: 'nobody'));
		$this->assertFalse(condition: $team->removeFrom(role: 'gone', uid: 'anna'));
	}//end testRemoveFrom()
}//end class
