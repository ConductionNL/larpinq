<?php

/**
 * Larpinq Code Check-in
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
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Throwable;

/**
 * Check a participant in with the code of their registration: once, and only
 * for the code's own event. The caller has checked that a game master asks.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */
class CodeCheckin {

	/**
	 * The attendance status of a checked-in participant.
	 *
	 * @var string
	 */
	private const CHECKED_IN = 'checked-in';

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the register with the app's authority.
	 * @param EventRosterService $roster Writes the attendance.
	 * @param CheckinCodes $codes Reads a typed code.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly EventRosterService $roster,
		private readonly CheckinCodes $codes,
	) {
	}//end __construct()

	/**
	 * Check in the participant a code belongs to.
	 *
	 * @param string $eventId The event at whose gate the code is scanned.
	 * @param string $code The code as scanned or typed.
	 * @param string $actingUid The game master.
	 *
	 * @return array{status: int, body: array<string, mixed>} The HTTP status and the answer.
	 *
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
	 */
	public function checkIn(string $eventId, string $code, string $actingUid): array {
		$registration = $this->registration(eventId: $eventId, code: $this->codes->normalise(code: $code));
		if ($registration === null) {
			return ['status' => 404, 'body' => ['status' => 'unknown']];
		}

		if ((string)($registration['status'] ?? '') !== 'accepted') {
			return ['status' => 409, 'body' => ['status' => 'not-accepted']];
		}

		$characterId = (string)($registration['character'] ?? '');
		if ($characterId === '') {
			return ['status' => 409, 'body' => ['status' => 'no-character']];
		}

		$earlier = $this->attendance(eventId: $eventId, characterId: $characterId);
		if ((string)($earlier['status'] ?? '') === self::CHECKED_IN) {
			return [
				'status' => 200,
				'body' => ['status' => 'already', 'at' => (string)($earlier['checkedInAt'] ?? ''), 'by' => (string)($earlier['checkedInBy'] ?? '')],
			];
		}

		$saved = $this->roster->recordAttendance(eventId: $eventId, characterId: $characterId, status: self::CHECKED_IN, actingUid: $actingUid);
		if ($saved === null) {
			return ['status' => 424, 'body' => ['status' => 'unavailable']];
		}

		$name = $this->name(type: 'player', id: (string)($registration['player'] ?? ''));
		return ['status' => 200, 'body' => ['status' => self::CHECKED_IN, 'name' => $name, 'character' => $this->name(type: 'character', id: $characterId)]];
	}//end checkIn()

	/**
	 * The registration of this event with this code, or null.
	 *
	 * @param string $eventId The event.
	 * @param string $code The normalised code, or ''.
	 *
	 * @return array<string, mixed>|null The registration.
	 */
	private function registration(string $eventId, string $code): ?array {
		if ($code === '' || $eventId === '') {
			return null;
		}

		$rows = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['checkinCode' => $code, 'event' => $eventId], limit: 1);
		if ($rows === []) {
			return null;
		}

		return $rows[0];
	}//end registration()

	/**
	 * The attendance of a character at an event, or [].
	 *
	 * @param string $eventId The event.
	 * @param string $characterId The character.
	 *
	 * @return array<string, mixed> The attendance.
	 */
	private function attendance(string $eventId, string $characterId): array {
		$rows = $this->fetcher->getObjectsWithAppAuthority(objectType: 'attendance', filters: ['event' => $eventId, 'character' => $characterId], limit: 1);
		return ($rows[0] ?? []);
	}//end attendance()

	/**
	 * The name of a player or character, or '' when it cannot be read.
	 *
	 * @param string $type The object type.
	 * @param string $id The id.
	 *
	 * @return string The name.
	 */
	private function name(string $type, string $id): string {
		try {
			return (string)($this->fetcher->getObject(objectType: $type, id: $id)['name'] ?? '');
		} catch (Throwable $e) {
			return '';
		}
	}//end name()
}//end class
