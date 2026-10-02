<?php

/**
 * Larpinq Cancellation Policy
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeImmutable;
use OCA\Larpinq\AppInfo\Application;
use OCP\IGroupManager;
use Throwable;

/**
 * Who may change a registration and until when, under the event's
 * cancellation policy (registration-cancel-transfer-refund REQ-RCT-001 to
 * REQ-RCT-004): the participant (the registration's player account), the
 * person who booked it, and game masters, who may always.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class CancellationPolicy {

	public const REFUND = 'refund';

	public const CREDIT = 'credit';

	public const NONE = 'none';

	public const PLAYER_CHOOSES = 'player-chooses';

	/**
	 * Constructor.
	 *
	 * @param IGroupManager $groups Who is a game master.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IGroupManager $groups,
	) {
	}//end __construct()

	/**
	 * Whether the account is a game master (or an admin).
	 *
	 * @param string $uid The account.
	 *
	 * @return bool True for a game master.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function isGameMaster(string $uid): bool {
		if ($uid === '') {
			return false;
		}

		return $this->groups->isInGroup($uid, Application::GM_GROUP) === true || $this->groups->isAdmin($uid) === true;
	}//end isGameMaster()

	/**
	 * Whether the account is the registration's player.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param string $uid The account.
	 *
	 * @return bool True for the participant.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function isParticipant(array $registration, string $uid): bool {
		return $uid !== '' && (string)($registration['playerUid'] ?? '') === $uid;
	}//end isParticipant()

	/**
	 * Whether the account booked the registration for someone else.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param string $uid The account.
	 *
	 * @return bool True for the booker.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function isBooker(array $registration, string $uid): bool {
		return $uid !== '' && (string)($registration['bookedByUid'] ?? '') === $uid;
	}//end isBooker()

	/**
	 * Until when players cancel themselves: the policy's date, else the start
	 * of the event, else no limit.
	 *
	 * @param array<string, mixed> $event The event.
	 *
	 * @return DateTimeImmutable|null The moment, or null for no limit.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function cancelBy(array $event): ?DateTimeImmutable {
		$policy = (array)($event['cancellationPolicy'] ?? []);
		foreach ([$policy['cancelBy'] ?? null, $event['startDate'] ?? null] as $value) {
			if (is_string($value) === true && $value !== '') {
				try {
					return new DateTimeImmutable($value);
				} catch (Throwable $e) {
					continue;
				}
			}
		}

		return null;
	}//end cancelBy()

	/**
	 * Whether players may still cancel or hand over their registration.
	 *
	 * @param array<string, mixed> $event The event.
	 * @param DateTimeImmutable $now The moment.
	 *
	 * @return bool True before the cancel-by moment.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function isOpen(array $event, DateTimeImmutable $now): bool {
		$cancelBy = $this->cancelBy(event: $event);
		return $cancelBy === null || $now < $cancelBy;
	}//end isOpen()

	/**
	 * Whether the player chooses between a refund and credit.
	 *
	 * @param array<string, mixed> $event The event.
	 *
	 * @return bool True when the policy lets the player choose.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function choosesMoneyBack(array $event): bool {
		return $this->paidCancellation(event: $event) === self::PLAYER_CHOOSES;
	}//end choosesMoneyBack()

	/**
	 * What a cancelled, paid registration gets: the policy, or the player's
	 * choice when the policy lets them choose (a refund when they did not).
	 *
	 * @param array<string, mixed> $event The event.
	 * @param string $choice The player's choice, or ''.
	 *
	 * @return string `refund`, `credit` or `none`.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function paidOutcome(array $event, string $choice): string {
		$policy = $this->paidCancellation(event: $event);
		if ($policy !== self::PLAYER_CHOOSES) {
			return $policy;
		}

		if ($choice === self::CREDIT) {
			return self::CREDIT;
		}

		return self::REFUND;
	}//end paidOutcome()

	/**
	 * The policy's paid cancellation, a refund when the event sets none.
	 *
	 * @param array<string, mixed> $event The event.
	 *
	 * @return string The policy value.
	 */
	private function paidCancellation(array $event): string {
		$value = (string)(((array)($event['cancellationPolicy'] ?? []))['paidCancellation'] ?? '');
		if (in_array($value, [self::REFUND, self::CREDIT, self::NONE, self::PLAYER_CHOOSES], true) === false) {
			return self::REFUND;
		}

		return $value;
	}//end paidCancellation()
}//end class
