<?php

/**
 * Larpinq Registration Places
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * How many places of an event and of a ticket type are taken.
 *
 * Read with the app's authority; the caller holds the event's lock when the
 * answer decides a status.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationPlaces {

	/**
	 * Upper bound of the rows one event is read with.
	 *
	 * @var integer
	 */
	private const MAX_ROWS = 2000;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the event's ticket types and registrations.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
	) {
	}//end __construct()

	/**
	 * Whether the registration's ticket type has no place left for it.
	 *
	 * @param array<string, mixed> $registration The registration as it will be.
	 *
	 * @return bool True when the ticket type has a place limit and every place is taken by another registration.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function ticketTypeFull(array $registration): bool {
		$limit = $this->ticketLimit(registration: $registration);
		if ($limit === null) {
			return false;
		}

		$taken = 0;
		$ticketId = (string)($registration['ticketType'] ?? '');
		foreach ($this->accepted(eventId: (string)($registration['event'] ?? '')) as $other) {
			if ((string)($other['ticketType'] ?? '') === $ticketId && (string)($other['id'] ?? '') !== (string)($registration['id'] ?? '')) {
				$taken++;
			}
		}

		return $taken >= $limit;
	}//end ticketTypeFull()

	/**
	 * The place limit of the registration's ticket type, or null when it has none.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return int|null The limit.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function ticketLimit(array $registration): ?int {
		$ticketId = (string)($registration['ticketType'] ?? '');
		$eventId = (string)($registration['event'] ?? '');
		if ($ticketId === '' || $eventId === '') {
			return null;
		}

		$rows = $this->fetcher->getObjectsWithAppAuthority(objectType: 'tickettype', filters: ['event' => $eventId], limit: self::MAX_ROWS);
		foreach ($rows as $ticket) {
			if ((string)($ticket['id'] ?? '') === $ticketId && is_numeric($ticket['placeLimit'] ?? null) === true) {
				return (int)$ticket['placeLimit'];
			}
		}

		return null;
	}//end ticketLimit()

	/**
	 * Accepted registrations plus characters in the event that no accepted registration brings.
	 *
	 * @param array<string, mixed> $event The event.
	 * @param string $excludeId A registration not to count.
	 *
	 * @return int The places taken.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function placesTaken(array $event, string $excludeId): int {
		$accepted = $this->accepted(eventId: (string)($event['id'] ?? ''));
		$brought = [];
		$count = 0;
		foreach ($accepted as $registration) {
			if ((string)($registration['id'] ?? '') === $excludeId) {
				continue;
			}

			$count++;
			$brought[(string)($registration['character'] ?? '')] = true;
		}

		foreach ((array)($event['players'] ?? []) as $character) {
			if (isset($brought[(string)$character]) === false) {
				$count++;
			}
		}

		return $count;
	}//end placesTaken()

	/**
	 * Whether an option with a place limit has no place left for this registration.
	 *
	 * @param array<string, mixed> $option The option.
	 * @param array<string, mixed> $registration The registration (not counted).
	 *
	 * @return bool True when full.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function optionFull(array $option, array $registration): bool {
		if (is_numeric($option['placeLimit'] ?? null) === false) {
			return false;
		}

		$optionId = (string)($option['id'] ?? '');
		$taken = 0;
		foreach ($this->accepted(eventId: (string)($option['event'] ?? '')) as $other) {
			$chose = in_array($optionId, array_map('strval', (array)($other['options'] ?? [])), true);
			if ($chose === true && (string)($other['id'] ?? '') !== (string)($registration['id'] ?? '')) {
				$taken++;
			}
		}

		return $taken >= (int)$option['placeLimit'];
	}//end optionFull()

	/**
	 * The event's accepted registrations.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<int, array<string, mixed>> The registrations.
	 */
	private function accepted(string $eventId): array {
		return $this->fetcher->getObjectsWithAppAuthority(
			objectType: 'registration',
			filters: ['event' => $eventId, 'status' => RegistrationService::ACCEPTED],
			limit: self::MAX_ROWS
		);
	}//end accepted()
}//end class
