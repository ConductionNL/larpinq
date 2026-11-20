<?php

/**
 * Larpinq Casting Preference Guard
 *
 * Refuses a player's ranking of a character when the event's casting deadline
 * has passed, when the registration is not the player's own on that event,
 * when the character is not open for casting on that event, or when the
 * registration already ranks the most characters allowed. Game masters, and
 * writes without a signed-in user, pass.
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
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeImmutable;
use OCA\Larpinq\Support\AppIdentity;
use OCP\IGroupManager;
use Throwable;

/**
 * The write rule on casting preferences.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */
class CastingPreferenceGuard {

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the event, registration, character and other rankings.
	 * @param IGroupManager         $groups  Who is a game master.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 *
	 * @spec exclude pure framework plumbing: constructor dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly IGroupManager $groups,
	) {
	}//end __construct()

	/**
	 * The refusal for a preference write, or null when it may pass.
	 *
	 * @param array<string, mixed> $candidate The preference as it will be saved (with `id` on an update).
	 * @param string               $uid       The signed-in account, or '' for a system write.
	 * @param DateTimeImmutable    $now       The moment of the write.
	 *
	 * @return array<string, mixed>|null The errors payload, or null.
	 *
	 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
	 */
	public function check(array $candidate, string $uid, DateTimeImmutable $now): ?array {
		if ($uid === '' || $this->isGameMaster(uid: $uid) === true) {
			return null;
		}

		$event = $this->read(objectType: 'event', id: (string)($candidate['event'] ?? ''));
		if ($event === null) {
			return $this->refuse(code: 'casting_event_unknown', field: 'event', message: 'The event of this ranking does not exist.');
		}

		if ($this->deadlinePassed(event: $event, now: $now) === true) {
			return $this->refuse(code: 'casting_closed', field: 'rank', message: 'The casting deadline of this event has passed.');
		}

		return $this->entryRefusal(candidate: $candidate, uid: $uid);
	}//end check()

	/**
	 * Why a player may not make this entry: someone else's registration, a
	 * character not open on the event, or one ranking too many. Null when they may.
	 *
	 * @param array<string, mixed> $candidate The preference as it will be saved.
	 * @param string               $uid       The signed-in player.
	 *
	 * @return array<string, mixed>|null The errors payload, or null.
	 */
	private function entryRefusal(array $candidate, string $uid): ?array {
		$eventId        = (string)($candidate['event'] ?? '');
		$registrationId = (string)($candidate['registration'] ?? '');

		$registration = $this->read(objectType: 'registration', id: $registrationId);
		if ($registration === null
			|| (string)($registration['event'] ?? '') !== $eventId
			|| (string)($registration['playerUid'] ?? '') !== $uid
		) {
			return $this->refuse(
				code: 'casting_registration_not_yours',
				field: 'registration',
				message: 'You can only rank characters on your own registration for this event.'
			);
		}

		$character = $this->read(objectType: 'character', id: (string)($candidate['character'] ?? ''));
		if ($character === null || (string)($character['castingEvent'] ?? '') !== $eventId) {
			return $this->refuse(code: 'casting_character_not_open', field: 'character', message: 'This character is not open for casting on this event.');
		}

		$others = $this->otherRankings(registrationId: $registrationId, ownId: (string)($candidate['id'] ?? ''));
		if ($others >= CastingSolver::MAX_RANK) {
			$message = 'A registration ranks at most ' . CastingSolver::MAX_RANK . ' characters.';
			return $this->refuse(code: 'casting_too_many', field: 'character', message: $message);
		}

		return null;
	}//end entryRefusal()

	/**
	 * Whether the event's casting deadline lies at or before the moment.
	 * An event without a readable deadline is open.
	 *
	 * @param array<string, mixed> $event The event.
	 * @param DateTimeImmutable    $now   The moment.
	 *
	 * @return bool True when ranking has closed.
	 *
	 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
	 */
	public function deadlinePassed(array $event, DateTimeImmutable $now): bool {
		$deadline = $event['castingDeadline'] ?? null;
		if (is_string($deadline) === false || $deadline === '') {
			return false;
		}

		try {
			return $now >= new DateTimeImmutable($deadline);
		} catch (Throwable $e) {
			return false;
		}
	}//end deadlinePassed()

	/**
	 * Whether the account is a game master or an admin.
	 *
	 * @param string $uid The account.
	 *
	 * @return bool True for a game master.
	 */
	private function isGameMaster(string $uid): bool {
		return $this->groups->isInGroup($uid, AppIdentity::GM_GROUP) === true || $this->groups->isAdmin($uid) === true;
	}//end isGameMaster()

	/**
	 * One object of a type, or null when it is missing or unreadable.
	 *
	 * @param string $objectType The object type.
	 * @param string $id         The uuid.
	 *
	 * @return array<string, mixed>|null The object.
	 */
	private function read(string $objectType, string $id): ?array {
		if ($id === '') {
			return null;
		}

		try {
			return $this->fetcher->getObjectOfType(objectType: $objectType, id: $id);
		} catch (Throwable $e) {
			return null;
		}
	}//end read()

	/**
	 * How many rankings the registration holds besides the one being written.
	 *
	 * @param string $registrationId The registration.
	 * @param string $ownId          The id of the ranking being written, or '' on a create.
	 *
	 * A registration whose rankings cannot be read counts as full, so the
	 * limit is refused rather than skipped.
	 *
	 * @return int The count.
	 */
	private function otherRankings(string $registrationId, string $ownId): int {
		try {
			$rows = $this->fetcher->getObjectsWithAppAuthority(
				objectType: 'castingpreference',
				filters: ['registration' => $registrationId],
				limit: (CastingSolver::MAX_RANK + 1)
			);
		} catch (Throwable $e) {
			return CastingSolver::MAX_RANK;
		}

		$others = 0;
		foreach ($rows as $row) {
			$id = (string)($row['id'] ?? ($row['@self']['id'] ?? ''));
			if ($ownId === '' || $id !== $ownId) {
				$others++;
			}
		}

		return $others;
	}//end otherRankings()

	/**
	 * The errors payload of a refusal.
	 *
	 * @param string $code    The error code.
	 * @param string $field   The field the error is on.
	 * @param string $message The message.
	 *
	 * @return array<string, mixed> The payload.
	 */
	private function refuse(string $code, string $field, string $message): array {
		return [
			'code' => $code,
			'message' => $message,
			'fields' => [$field => [$message]],
		];
	}//end refuse()
}//end class
