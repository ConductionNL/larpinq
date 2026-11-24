<?php

/**
 * StaffInviteLink for Larpinq
 *
 * The link of a staff invite, and the lock that runs the redeems of one
 * invite one at a time.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/staff-roles/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * Builds an invite's link and holds the per-invite redeem lock.
 *
 * Kept out of StaffInvitesController so the controller stays under the
 * phpmd coupling limit.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */
class StaffInviteLink {

	/**
	 * Constructor.
	 *
	 * @param IURLGenerator    $urlGenerator    Builds the invite link.
	 * @param ILockingProvider $lockingProvider Runs the redeems of one invite one at a time.
	 */
	public function __construct(
		private readonly IURLGenerator $urlGenerator,
		private readonly ILockingProvider $lockingProvider,
	) {
	}//end __construct()

	/**
	 * The absolute link a user opens to join with this token.
	 *
	 * @param string $token The plain token.
	 *
	 * @return string The link.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	public function url(string $token): string {
		return $this->urlGenerator->linkToRouteAbsolute('larpinq.dashboard.page') . 'staff-invite/' . $token;
	}//end url()

	/**
	 * Take the redeem lock of the invite this token opens.
	 *
	 * @param string $token The plain token.
	 *
	 * @return bool True when taken; false while another redeem of it holds it.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	public function lock(string $token): bool {
		try {
			$this->lockingProvider->acquireLock(self::lockKey(token: $token), ILockingProvider::LOCK_EXCLUSIVE);
		} catch (LockedException) {
			return false;
		}

		return true;
	}//end lock()

	/**
	 * Release the redeem lock of the invite this token opens.
	 *
	 * @param string $token The plain token.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	public function unlock(string $token): void {
		$this->lockingProvider->releaseLock(self::lockKey(token: $token), ILockingProvider::LOCK_EXCLUSIVE);
	}//end unlock()

	/**
	 * The lock key of an invite: by its token hash, never the plain token.
	 *
	 * @param string $token The plain token.
	 *
	 * @return string The key.
	 *
	 * @spec openspec/specs/staff-roles/spec.md
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) StaffInviteService::hashToken() is a pure hash helper.
	 */
	public static function lockKey(string $token): string {
		return 'larpinq/staff-invite/' . StaffInviteService::hashToken(token: $token);
	}//end lockKey()
}//end class
