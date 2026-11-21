<?php

/**
 * StaffTeam for Larpinq
 *
 * The members of a staff role, and taking a member out of one.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Reads and trims the membership of a role group.
 *
 * Kept out of StaffInviteService so that service stays under the phpmd
 * class complexity and coupling limits.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */
class StaffTeam {

	/**
	 * Constructor.
	 *
	 * @param IGroupManager $groupManager The Nextcloud groups.
	 * @param IUserManager  $userManager  The Nextcloud users.
	 */
	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}//end __construct()

	/**
	 * A role's members, sorted by display name.
	 *
	 * @param string $role The group id.
	 *
	 * @return list<array{uid: string, displayName: string}>
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 */
	public function members(string $role): array {
		$members = [];
		foreach (($this->groupManager->get($role)?->getUsers() ?? []) as $member) {
			$members[] = ['uid' => $member->getUID(), 'displayName' => $member->getDisplayName()];
		}

		usort($members, static fn (array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));
		return $members;
	}//end members()

	/**
	 * Take a user out of a role's group; false when either is gone or they are not in it.
	 *
	 * @param string $role The group id.
	 * @param string $uid  The user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 */
	public function removeFrom(string $role, string $uid): bool {
		$group = $this->groupManager->get($role);
		$user  = $this->userManager->get($uid);
		if ($group === null || $user === null) {
			return false;
		}

		if ($group->inGroup($user) === false) {
			return false;
		}

		$group->removeUser($user);
		return true;
	}//end removeFrom()
}//end class
