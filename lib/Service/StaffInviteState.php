<?php

/**
 * StaffInviteState for Larpinq
 *
 * Reads the state of a staff invite: revoked, expired, used up or valid.
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

use DateTimeImmutable;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * The state of a staff invite at the current moment, and the clock it reads.
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
class StaffInviteState {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory $timeFactory The clock.
	 */
	public function __construct(
		private readonly ITimeFactory $timeFactory,
	) {
	}//end __construct()

	/**
	 * Whether an invite still adds people: revoked first, then expired, then used up.
	 *
	 * @param array<string, mixed> $invite The invite.
	 *
	 * @return string One of the StaffInviteService::STATE_* values.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 */
	public function state(array $invite): string {
		if ($this->isRevoked(invite: $invite) === true) {
			return StaffInviteService::STATE_REVOKED;
		}

		if ($this->isExpired(invite: $invite) === true) {
			return StaffInviteService::STATE_EXPIRED;
		}

		if (count($this->uses(invite: $invite)) >= max(1, (int)($invite['maxUses'] ?? 1))) {
			return StaffInviteService::STATE_USED_UP;
		}

		return StaffInviteService::STATE_VALID;
	}//end state()

	/**
	 * Whether the invite was revoked.
	 *
	 * @param array<string, mixed> $invite The invite.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 */
	public function isRevoked(array $invite): bool {
		return is_string($invite['revokedAt'] ?? null) === true && $invite['revokedAt'] !== '';
	}//end isRevoked()

	/**
	 * Whether the invite's end has passed; one without a readable end has.
	 *
	 * @param array<string, mixed> $invite The invite.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 */
	public function isExpired(array $invite): bool {
		$expires = false;
		if (is_string($invite['expiresAt'] ?? null) === true) {
			$expires = strtotime($invite['expiresAt']);
		}

		return $expires === false || $expires <= $this->now()->getTimestamp();
	}//end isExpired()

	/**
	 * The recorded uses of an invite.
	 *
	 * @param array<string, mixed> $invite The invite.
	 *
	 * @return list<array<string, mixed>> The uses.
	 *
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 */
	public function uses(array $invite): array {
		$uses = $invite['uses'] ?? [];
		if (is_array($uses) === false) {
			return [];
		}

		return array_values(array_filter($uses, 'is_array'));
	}//end uses()

	/**
	 * Now, from the clock.
	 *
	 * @return DateTimeImmutable The current moment.
	 *
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) DateTimeImmutable's named constructor.
	 */
	public function now(): DateTimeImmutable {
		return DateTimeImmutable::createFromMutable($this->timeFactory->getDateTime());
	}//end now()
}//end class
