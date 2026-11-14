<?php

/**
 * The audience of an event message: which registrations it selects.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Normalises an audience as a client sends it and tells whether a
 * registration belongs to it: by registration status, open payment, ticket
 * role and check-in.
 */
class EventAudience {

	/**
	 * Registration statuses a message can be addressed to.
	 */
	public const STATUSES = ['accepted', 'waitlisted', 'pending'];

	/**
	 * Ticket roles a message can be addressed to.
	 */
	public const TICKET_ROLES = ['player', 'crew', 'npc'];

	/**
	 * The audience in its stored shape: only known statuses and roles,
	 * booleans as booleans. Without a status it is the accepted registrations.
	 *
	 * @param array<string, mixed> $raw The audience as sent.
	 *
	 * @return array{statuses: list<string>, checkedIn: bool, paymentOpen: bool, ticketRoles: list<string>}
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-game-masters-write-a-message-to-an-events-participants-req-cmp-001
	 */
	public function normalise(array $raw): array {
		$statuses = $this->knownValues(values: $raw['statuses'] ?? [], known: self::STATUSES);
		if ($statuses === []) {
			$statuses = ['accepted'];
		}

		return [
			'statuses'    => $statuses,
			'checkedIn'   => $this->flag(value: $raw['checkedIn'] ?? false),
			'paymentOpen' => $this->flag(value: $raw['paymentOpen'] ?? false),
			'ticketRoles' => $this->knownValues(values: $raw['ticketRoles'] ?? [], known: self::TICKET_ROLES),
		];
	}//end normalise()

	/**
	 * Whether a registration belongs to the audience.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array{statuses: list<string>, checkedIn: bool, paymentOpen: bool, ticketRoles: list<string>} $audience The normalised audience.
	 * @param array<string, string> $roles The event's ticket roles by ticket type id.
	 * @param array<string, true> $checkedIn The characters checked in at the event.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-game-masters-write-a-message-to-an-events-participants-req-cmp-001
	 */
	public function matches(array $registration, array $audience, array $roles, array $checkedIn): bool {
		if (in_array((string)($registration['status'] ?? ''), $audience['statuses'], true) === false) {
			return false;
		}

		if ($audience['paymentOpen'] === true && ($registration['paymentState'] ?? '') !== 'open') {
			return false;
		}

		return $this->roleMatches(registration: $registration, audience: $audience, roles: $roles)
			&& $this->checkInMatches(registration: $registration, audience: $audience, checkedIn: $checkedIn);
	}//end matches()

	/**
	 * Whether the registration's ticket role is one the audience asks for.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array{ticketRoles: list<string>} $audience The normalised audience.
	 * @param array<string, string> $roles The event's ticket roles by ticket type id.
	 *
	 * @return bool
	 */
	private function roleMatches(array $registration, array $audience, array $roles): bool {
		if ($audience['ticketRoles'] === []) {
			return true;
		}

		$role = $roles[(string)($registration['ticketType'] ?? '')] ?? '';

		return in_array($role, $audience['ticketRoles'], true);
	}//end roleMatches()

	/**
	 * Whether the registration's character is checked in, when the audience asks for that.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array{checkedIn: bool} $audience The normalised audience.
	 * @param array<string, true> $checkedIn The characters checked in at the event.
	 *
	 * @return bool
	 */
	private function checkInMatches(array $registration, array $audience, array $checkedIn): bool {
		if ($audience['checkedIn'] === false) {
			return true;
		}

		return isset($checkedIn[(string)($registration['character'] ?? '')]);
	}//end checkInMatches()

	/**
	 * The values of a list that are among the known ones, once each, in the known order.
	 *
	 * @param mixed $values The values as sent.
	 * @param list<string> $known The allowed values.
	 *
	 * @return list<string>
	 */
	private function knownValues(mixed $values, array $known): array {
		$given = [];
		if (is_array($values) === true) {
			$given = array_map('strval', $values);
		}

		return array_values(array_filter($known, static fn (string $value): bool => in_array($value, $given, true)));
	}//end knownValues()

	/**
	 * A flag from a JSON or query value.
	 *
	 * @param mixed $value The value as sent.
	 *
	 * @return bool
	 */
	private function flag(mixed $value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}//end flag()
}//end class
