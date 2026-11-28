<?php

/**
 * Larpinq Feedback Participants
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
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/event-feedback/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Throwable;

/**
 * The checked-in participants of an event, their accounts and their
 * characters' names, read with the app's authority.
 *
 * Kept out of EventFeedbackService so that service stays under the phpmd
 * class complexity limit.
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md
 */
class FeedbackParticipants {

	/**
	 * The most attendance records read per event.
	 *
	 * @var int
	 */
	private const PAGE = 200;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the register with the app's authority.
	 *
	 * @spec openspec/specs/event-feedback/spec.md
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
	) {
	}//end __construct()

	/**
	 * The checked-in attendances of an event.
	 *
	 * The filter already asks for checked-in; a no-show is never included even if a store ignores it.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<int, array> The attendance records.
	 * @spec openspec/specs/event-feedback/spec.md
	 */
	public function checkedIn(string $eventId): array {
		$rows = $this->fetcher->getObjectsWithAppAuthority(
			objectType: 'attendance',
			filters: ['event' => $eventId, 'status' => EventFeedbackService::CHECKED_IN],
			limit: self::PAGE
		);

		return array_values(array_filter($rows, static fn (array $row): bool => (string)($row['status'] ?? '') === EventFeedbackService::CHECKED_IN));
	}//end checkedIn()

	/**
	 * The participants of an event who were invited, by user id, with the name of their character.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<string, string> Character name by user id.
	 * @spec openspec/specs/event-feedback/spec.md
	 */
	public function invitees(string $eventId): array {
		$invited = [];
		foreach ($this->checkedIn(eventId: $eventId) as $attendance) {
			if ((string)($attendance['feedbackInvitedAt'] ?? '') === '') {
				continue;
			}

			$owner = $this->ownerOf(attendance: $attendance);
			if ($owner !== '') {
				$invited[$owner] = $this->characterName(attendance: $attendance);
			}
		}

		return $invited;
	}//end invitees()

	/**
	 * The account that plays the attendance's character, or ''.
	 *
	 * @param array $attendance The attendance record.
	 *
	 * @return string The user id.
	 * @spec openspec/specs/event-feedback/spec.md
	 */
	public function ownerOf(array $attendance): string {
		return $this->characterField(attendance: $attendance, field: 'ownerUid');
	}//end ownerOf()

	/**
	 * The name of the attendance's character, or ''.
	 *
	 * @param array $attendance The attendance record.
	 *
	 * @return string The name.
	 * @spec openspec/specs/event-feedback/spec.md
	 */
	public function characterName(array $attendance): string {
		$character = $attendance['character'] ?? '';
		if (is_array($character) === true && (string)($character['name'] ?? '') !== '') {
			return (string)$character['name'];
		}

		return $this->characterField(attendance: $attendance, field: 'name');
	}//end characterName()

	/**
	 * One field of the attendance's character, read from the register, or ''.
	 *
	 * @param array  $attendance The attendance record.
	 * @param string $field      The character field.
	 *
	 * @return string The value.
	 * @spec openspec/specs/event-feedback/spec.md
	 */
	private function characterField(array $attendance, string $field): string {
		$character = $attendance['character'] ?? '';
		if (is_array($character) === true) {
			$character = ($character['id'] ?? ($character['@self']['id'] ?? ''));
		}

		if (is_string($character) === false || $character === '') {
			return '';
		}

		try {
			return (string)($this->fetcher->getObject(objectType: 'character', id: $character)[$field] ?? '');
		} catch (Throwable $e) {
			return '';
		}
	}//end characterField()
}//end class
