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

use OCA\Larpinq\Kernel\Contracts\CheckinByCodeInterface;
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
class CheckinByCode implements CheckinByCodeInterface {

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
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) CheckinCode is a pure helper.
	 */
	public function checkIn(string $eventId, string $code, string $actingUid): array {
		$registration = $this->registrationFor(code: CheckinCode::normalise(code: $code));
		$refusal      = $this->refusal(registration: $registration, eventId: $eventId);
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->admit(registration: $registration, actingUid: $actingUid);
	}//end checkIn()

	/**
	 * Why a registration cannot be checked in at an event, or null when it can.
	 *
	 * @param array<string, mixed>|null $registration The registration holding the code.
	 * @param string                    $eventId      The event at whose door the code is used.
	 *
	 * @return array{0: int, 1: array<string, mixed>}|null The HTTP status and the answer.
	 *
	 * @spec openspec/changes/events-qr-checkin/tasks.md#2.1
	 */
	private function refusal(array|null $registration, string $eventId): array|null {
		if ($registration === null || self::text(row: $registration, key: 'event') !== $eventId) {
			return [404, ['status' => 'unknown']];
		}

		if (self::text(row: $registration, key: 'status') !== RegistrationService::ACCEPTED) {
			return [409, ['status' => 'not-accepted']];
		}

		return null;
	}//end refusal()

	/**
	 * Check in the character of an accepted registration, once.
	 *
	 * @param array<string, mixed> $registration The accepted registration of this event.
	 * @param string               $actingUid    The game master checking in.
	 *
	 * @return array{0: int, 1: array<string, mixed>} The HTTP status and the answer.
	 *
	 * @spec openspec/changes/events-qr-checkin/tasks.md#2.1
	 */
	private function admit(array $registration, string $actingUid): array {
		$eventId     = self::text(row: $registration, key: 'event');
		$characterId = self::text(row: $registration, key: 'character');
		$earlier     = $this->checkedIn(eventId: $eventId, characterId: $characterId);
		if ($earlier !== null) {
			return [200, $this->alreadyAnswer(earlier: $earlier)];
		}

		return $this->record(registration: $registration, actingUid: $actingUid);
	}//end admit()

	/**
	 * Record the check-in and answer with who was checked in.
	 *
	 * @param array<string, mixed> $registration The accepted registration of this event.
	 * @param string               $actingUid    The game master checking in.
	 *
	 * @return array{0: int, 1: array<string, mixed>} The HTTP status and the answer.
	 */
	private function record(array $registration, string $actingUid): array {
		$characterId = self::text(row: $registration, key: 'character');
		$saved       = $this->roster->recordAttendance(
			eventId: self::text(row: $registration, key: 'event'),
			characterId: $characterId,
			status: self::CHECKED_IN,
			actingUid: $actingUid
		);
		if ($saved === null) {
			return [424, ['status' => 'unavailable']];
		}

		return [200, $this->checkedInAnswer(registration: $registration)];
	}//end record()

	/**
	 * The answer for a participant who was just checked in: player and character.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return array<string, mixed> The answer.
	 */
	private function checkedInAnswer(array $registration): array {
		return [
			'status' => self::CHECKED_IN,
			'name' => $this->displayName(uid: self::text(row: $registration, key: 'playerUid')),
			'character' => $this->characterName(characterId: self::text(row: $registration, key: 'character')),
		];
	}//end checkedInAnswer()

	/**
	 * The answer for a character that was already checked in.
	 *
	 * @param array<string, mixed> $earlier The earlier attendance record.
	 *
	 * @return array<string, mixed> The answer: when and by whom.
	 */
	private function alreadyAnswer(array $earlier): array {
		return [
			'status' => 'already',
			'at' => self::text(row: $earlier, key: 'checkedInAt'),
			'by' => $this->displayName(uid: self::text(row: $earlier, key: 'checkedInBy')),
		];
	}//end alreadyAnswer()

	/**
	 * The registration holding a code, or null.
	 *
	 * @param string $code The normalised code.
	 *
	 * @return array<string, mixed>|null The registration.
	 */
	private function registrationFor(string $code): array|null {
		if ($code === '') {
			return null;
		}

		try {
			$found = $this->fetcher->getObjectsByRevealedValue(objectType: 'registration', filters: ['checkinCode' => $code], limit: 1);
		} catch (Throwable $e) {
			return null;
		}

		$first = ($found[0] ?? null);
		if (is_array($first) === false || self::text(row: $first, key: 'checkinCode') !== $code) {
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
	private function checkedIn(string $eventId, string $characterId): array|null {
		try {
			$records = $this->fetcher->getObjects(objectType: 'attendance', limit: 1, offset: 0, filters: ['event' => $eventId, 'character' => $characterId]);
		} catch (Throwable $e) {
			return null;
		}

		foreach ($records as $record) {
			if ($this->isCheckIn(record: $record, characterId: $characterId) === true) {
				return $record;
			}
		}

		return null;
	}//end checkedIn()

	/**
	 * Whether an attendance record is a check-in of the character.
	 *
	 * @param mixed  $record      The record as the register returned it.
	 * @param string $characterId The character.
	 *
	 * @return bool True for a checked-in record of that character.
	 */
	private function isCheckIn(mixed $record, string $characterId): bool {
		return is_array($record) === true
			&& self::text(row: $record, key: 'character') === $characterId
			&& self::text(row: $record, key: 'status') === self::CHECKED_IN;
	}//end isCheckIn()

	/**
	 * A field of a register row as a string, '' when absent.
	 *
	 * @param array<string, mixed> $row The row.
	 * @param string               $key The field.
	 *
	 * @return string The value.
	 */
	private static function text(array $row, string $key): string {
		return (string)($row[$key] ?? '');
	}//end text()

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
