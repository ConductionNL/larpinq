<?php

/**
 * Check a participant in by the code of their registration.
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

use OCP\IUserManager;
use Throwable;

/**
 * Finds the registration a check-in code belongs to and checks its character in.
 *
 * The caller has already been confirmed as a game master. The registration is
 * read with the app's authority, scoped by the code itself. A code of another
 * event and an unknown code read the same, so a code says nothing about other
 * events. A registration that is not accepted checks nobody in, and a second
 * use reports when and by whom the first was.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-a-code-is-checked-in-once-and-only-for-its-event-req-eqc-004
 */
class CheckinByCode {

	/**
	 * The attendance status of a checked-in participant.
	 *
	 * @var string
	 */
	public const CHECKED_IN = 'checked-in';

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher The register.
	 * @param EventRosterService    $roster  The guarded attendance write.
	 * @param IUserManager          $users   Display names of players and stewards.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly EventRosterService $roster,
		private readonly IUserManager $users,
	) {
	}//end __construct()

	/**
	 * Check in the participant a code belongs to.
	 *
	 * @param string $eventId   The event at whose door the code is used.
	 * @param string $code      The code as typed or scanned.
	 * @param string $actingUid The game master checking in.
	 *
	 * @return array{0: int, 1: array<string, mixed>} The HTTP status and the answer.
	 *
	 * @spec openspec/changes/events-qr-checkin/tasks.md#2.1
	 */
	public function checkIn(string $eventId, string $code, string $actingUid): array {
		$registration = $this->registrationFor(code: CheckinCode::normalise(code: $code));
		if ($registration === null || (string)($registration['event'] ?? '') !== $eventId) {
			return [404, ['status' => 'unknown']];
		}

		if ((string)($registration['status'] ?? '') !== RegistrationService::ACCEPTED) {
			return [409, ['status' => 'not-accepted']];
		}

		$characterId = (string)($registration['character'] ?? '');
		$earlier = $this->checkedIn(eventId: $eventId, characterId: $characterId);
		if ($earlier !== null) {
			return [
				200,
				[
					'status' => 'already',
					'at' => (string)($earlier['checkedInAt'] ?? ''),
					'by' => $this->displayName(uid: (string)($earlier['checkedInBy'] ?? '')),
				],
			];
		}

		$saved = $this->roster->recordAttendance(eventId: $eventId, characterId: $characterId, status: self::CHECKED_IN, actingUid: $actingUid);
		if ($saved === null) {
			return [424, ['status' => 'unavailable']];
		}

		return [
			200,
			[
				'status' => self::CHECKED_IN,
				'name' => $this->displayName(uid: (string)($registration['playerUid'] ?? '')),
				'character' => $this->characterName(characterId: $characterId),
			],
		];
	}//end checkIn()

	/**
	 * The registration holding a code, or null.
	 *
	 * @param string $code The normalised code.
	 *
	 * @return array<string, mixed>|null The registration.
	 */
	private function registrationFor(string $code): ?array {
		if ($code === '') {
			return null;
		}

		try {
			$found = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['checkinCode' => $code], limit: 1);
		} catch (Throwable $e) {
			return null;
		}

		$first = ($found[0] ?? null);
		if (is_array($first) === false || (string)($first['checkinCode'] ?? '') !== $code) {
			return null;
		}

		return $first;
	}//end registrationFor()

	/**
	 * The character's checked-in attendance at the event, or null.
	 *
	 * @param string $eventId     The event.
	 * @param string $characterId The character.
	 *
	 * @return array<string, mixed>|null The attendance record.
	 */
	private function checkedIn(string $eventId, string $characterId): ?array {
		try {
			$records = $this->fetcher->getObjects(objectType: 'attendance', limit: 1, offset: 0, filters: ['event' => $eventId, 'character' => $characterId]);
		} catch (Throwable $e) {
			return null;
		}

		foreach ($records as $record) {
			if (is_array($record) === true
				&& (string)($record['character'] ?? '') === $characterId
				&& (string)($record['status'] ?? '') === self::CHECKED_IN
			) {
				return $record;
			}
		}

		return null;
	}//end checkedIn()

	/**
	 * A user's display name, else the uid.
	 *
	 * @param string $uid The user id.
	 *
	 * @return string The name.
	 */
	private function displayName(string $uid): string {
		if ($uid === '') {
			return '';
		}

		$name = $this->users->getDisplayName($uid);
		if ($name === null || $name === '') {
			return $uid;
		}

		return $name;
	}//end displayName()

	/**
	 * A character's name, or '' when it cannot be read.
	 *
	 * @param string $characterId The character.
	 *
	 * @return string The name.
	 */
	private function characterName(string $characterId): string {
		try {
			$character = $this->fetcher->getObject(objectType: 'character', id: $characterId);
		} catch (Throwable $e) {
			return '';
		}

		return (string)($character['name'] ?? '');
	}//end characterName()
}//end class
