<?php

/**
 * EventRosterService for Larpinq
 *
 * Owns the event participation domain: who is cast in an event, what their
 * attendance status is, and the render context for the GM run-sheet. Extracted
 * from EventsController so the controller stays a thin HTTP/authorization
 * boundary and the participation rules have a single home.
 *
 * Every read degrades gracefully: when the `larping_attendance` schema (or
 * OpenRegister itself) is unavailable the participant list is still produced
 * with no per-row status, so the event page never breaks.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\OpenRegister\Mcp\Attribute\McpTool;
use OCP\IUserSession;

/**
 * Resolves event casts, attendance records and run-sheet context.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */
class EventRosterService {
	/**
	 * Constructor for EventRosterService.
	 *
	 * @param RegisterObjectFetcher $objectFetcher The register object fetcher.
	 * @param IUserSession|null     $userSession   The invoking principal, recorded as awardedBy on an agent award.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly ?IUserSession $userSession = null,
	) {
	}//end __construct()

	/**
	 * Fetch an event by UUID, or null when it does not exist / is unreadable.
	 *
	 * Per-object read access is OR-delegated: an event the caller may not read
	 * is indistinguishable from a missing one (both yield null → 404).
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return array<string,mixed>|null The event object, or null.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	public function getEvent(string $eventId): ?array {
		try {
			return $this->objectFetcher->getObject(objectType: 'event', id: $eventId);
		} catch (\Exception $exception) {
			return null;
		}
	}//end getEvent()

	/**
	 * Build the check-in roster for an event.
	 *
	 * Lists every confirmed participant (a character whose `events[]` references
	 * this event) with the player name, character type and current attendance
	 * status, sorted by character name.
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return array{participants: array<int,array<string,mixed>>, attendanceAvailable: bool} The roster.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	public function buildRoster(string $eventId): array {
		[$attendance, $attendanceAvailable] = $this->loadAttendance(eventId: $eventId);
		$lookup = new CastLookup(players: $this->indexPlayers(), attendance: $attendance);

		$participants = [];
		foreach ($this->castCharacters(eventId: $eventId) as $character) {
			$participants[] = $this->buildRosterEntry(character: $character, lookup: $lookup);
		}

		$this->sortByName(rows: $participants);

		return [
			'participants' => $participants,
			'attendanceAvailable' => $attendanceAvailable,
		];
	}//end buildRoster()

	/**
	 * Build the run-sheet render context: event header + cast list.
	 *
	 * Each cast entry carries the character name, type, approval status, linked
	 * player name (when available), stored computed stats (no recalculation
	 * here), condition/item references, attendance status and GM notes.
	 *
	 * @param array<string,mixed> $event The event object.
	 * @param string $eventId The event UUID (for the membership test).
	 *
	 * @return array<string,mixed> The render context.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	public function buildRunsheetContext(array $event, string $eventId): array {
		$lookup = $this->runsheetLookup(eventId: $eventId);
		$characters = $this->castCharacters(eventId: $eventId);
		$cast = array_map(fn (array $character): array => $this->buildCastEntry(character: $character, lookup: $lookup), $characters);
		$this->sortByName(rows: $cast);

		return [
			'event' => $this->runsheetEvent(event: $event, castCount: count($cast)),
			'cast' => $cast,
			'castCount' => count($cast),
			'uniqueItemsInPlay' => $this->uniqueItemIds(characters: $characters),
			'template' => [],
		];
	}//end buildRunsheetContext()

	/**
	 * The players, then the attendance of the event, for the run-sheet.
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return CastLookup The players and attendance.
	 */
	private function runsheetLookup(string $eventId): CastLookup {
		$players = $this->indexPlayers();

		// Attendance is additive: when the schema is absent the cast list is
		// unchanged (each entry's attendanceStatus stays empty).
		[$attendance] = $this->loadAttendance(eventId: $eventId);

		return new CastLookup(players: $players, attendance: $attendance);
	}//end runsheetLookup()

	/**
	 * The event header of the run-sheet.
	 *
	 * @param array<string,mixed> $event The event object.
	 * @param int $castCount The number of cast entries.
	 *
	 * @return array<string,mixed> The header.
	 */
	private function runsheetEvent(array $event, int $castCount): array {
		return [
			'name' => (string)($event['name'] ?? ''),
			'description' => (string)($event['description'] ?? ''),
			'startDate' => (string)($event['startDate'] ?? ''),
			'endDate' => (string)($event['endDate'] ?? ''),
			'location' => (string)($event['location'] ?? ''),
			'effects' => ($event['effects'] ?? []),
			'castCount' => $castCount,
		];
	}//end runsheetEvent()

	/**
	 * The characters cast in an event, in register order.
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return list<array<string,mixed>> The cast characters.
	 */
	private function castCharacters(string $eventId): array {
		$cast = [];
		foreach ($this->fetchCharacters() as $character) {
			if ($this->attendsEvent(character: $character, eventId: $eventId) === true) {
				$cast[] = $character;
			}
		}

		return $cast;
	}//end castCharacters()

	/**
	 * One check-in roster row for a character.
	 *
	 * @param array<string,mixed> $character The character object.
	 * @param CastLookup $lookup The event's players and attendance.
	 *
	 * @return array<string,mixed> The roster row.
	 */
	private function buildRosterEntry(array $character, CastLookup $lookup): array {
		$record = $lookup->attendanceOf(character: $character);

		return [
			'character' => (string)($character['id'] ?? ''),
			'name' => (string)($character['name'] ?? ''),
			'type' => (string)($character['type'] ?? ''),
			'playerName' => $this->resolvePlayerName(character: $character, players: $lookup->players),
			'status' => (string)($record['status'] ?? 'registered'),
			'checkedInAt' => (string)($record['checkedInAt'] ?? ''),
			'checkedInBy' => (string)($record['checkedInBy'] ?? ''),
		];
	}//end buildRosterEntry()

	/**
	 * Persist an attendance record for a participant.
	 *
	 * Provenance is server-stamped here — `checkedInAt` comes from the server
	 * clock and `checkedInBy` from the caller-supplied acting uid, never from a
	 * request body. An existing record for the same (event, character) pair is
	 * updated in place rather than duplicated.
	 *
	 * @param string $eventId The event UUID.
	 * @param string $characterId The character UUID.
	 * @param string $status The attendance status (already validated).
	 * @param string $actingUid The acting game master's uid.
	 *
	 * @return array<string,mixed>|null The persisted record, or null when
	 *                                  attendance storage is unavailable.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	public function recordAttendance(
		string $eventId,
		string $characterId,
		string $status,
		string $actingUid,
	): ?array {
		// Server-stamped provenance — any client-supplied checkedInAt/checkedInBy
		// is discarded by the caller (never read) and re-derived here.
		$payload = [
			'event' => $eventId,
			'character' => $characterId,
			'status' => $status,
			'checkedInAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
			'checkedInBy' => $actingUid,
		];

		try {
			[$existing, $available] = $this->loadAttendance(eventId: $eventId);
			if ($available === false) {
				return null;
			}

			return $this->objectFetcher->saveObject(
				objectType: 'attendance',
				data: $payload,
				uuid: $this->existingRecordId(existing: $existing, characterId: $characterId)
			);
		} catch (\Exception $exception) {
			return null;
		}
	}//end recordAttendance()

	/**
	 * Award XP to every character checked in at an event (hermiq-ai-tooling REQ-001).
	 *
	 * The recipients are decided here, never by the caller: one award per
	 * attendance row of the event whose status is `checked-in`, and none for a
	 * registered or no-show row. Each award is written through OpenRegister with
	 * the caller's rights, so the `gamemasters` create rule on `xpAward` stays
	 * the enforcement point; `awardedAt` and `awardedBy` are stamped here and
	 * `awardedVia` marks the award as made through an agent tool. The writes are
	 * not one transaction, so the answer lists every character with `created`
	 * or `failed`, and a retry shows the game master who already has an award.
	 *
	 * @param string $eventId The event UUID.
	 * @param int    $amount  The XP each checked-in character receives, at least 1.
	 * @param string $reason  Why the XP is awarded, shown on every award.
	 *
	 * @return array<string, mixed> `{event, amount, reason, results}`, a result per checked-in character.
	 *
	 * @throws \InvalidArgumentException When the amount is not positive, the event cannot be read, or nobody is checked in.
	 *
	 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-001--a-curated-event-scoped-xp-award-tool-exists
	 */
	#[McpTool(
		name: 'awardXpToAttendees',
		description: 'Award the same XP, with a reason, to every character checked in at one event. '
			. 'The server looks up who checked in. Needs a game master\'s approval.',
		readOnlyHint: false,
		destructiveHint: false,
		idempotentHint: false,
		scope: 'create',
	)]
	public function awardXpToAttendees(string $eventId, int $amount, string $reason): array {
		return (new AttendeeXpAward(objectFetcher: $this->objectFetcher, roster: $this))->award(
			eventId: $eventId,
			amount: $amount,
			reason: $reason,
			awardedBy: (string)$this->userSession?->getUser()?->getUID()
		);
	}//end awardXpToAttendees()

	/**
	 * Whether a character is a confirmed participant of an event.
	 *
	 * A participant is a character present in the Event `players[]` OR one whose
	 * `character.events[]` references the event. A GM cannot check in a
	 * character that is not part of the event.
	 *
	 * @param string $eventId The event UUID.
	 * @param string $characterId The character UUID.
	 * @param array<string,mixed> $event The event object.
	 *
	 * @return bool True when the character participates in the event.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	public function isParticipant(string $eventId, string $characterId, array $event): bool {
		$players = $event['players'] ?? [];
		if (is_array($players) === true && in_array($characterId, array_map('strval', $players), true) === true) {
			return true;
		}

		try {
			$character = $this->objectFetcher->getObject(objectType: 'character', id: $characterId);
		} catch (\Exception $exception) {
			return false;
		}

		return $this->attendsEvent(character: $character, eventId: $eventId);
	}//end isParticipant()

	/**
	 * The UUID of an existing attendance record for a character, if any.
	 *
	 * @param array<string,array<string,mixed>> $existing Records indexed by character id.
	 * @param string $characterId The character UUID.
	 *
	 * @return string|null The record UUID, or null to create a new one.
	 */
	private function existingRecordId(array $existing, string $characterId): ?string {
		$prior = $existing[$characterId] ?? null;
		if (is_array($prior) === true && isset($prior['id']) === true) {
			return (string)$prior['id'];
		}

		return null;
	}//end existingRecordId()

	/**
	 * Build one run-sheet cast entry for a character.
	 *
	 * @param array<string,mixed> $character The character object.
	 * @param CastLookup $lookup The event's players and attendance.
	 *
	 * @return array<string,mixed> The cast entry.
	 */
	private function buildCastEntry(array $character, CastLookup $lookup): array {
		$record = $lookup->attendanceOf(character: $character);

		return [
			'name' => (string)($character['name'] ?? ''),
			'type' => (string)($character['type'] ?? ''),
			'approved' => (string)($character['approved'] ?? ''),
			'playerName' => $this->resolvePlayerName(character: $character, players: $lookup->players),
			'stats' => ($character['stats'] ?? []),
			'conditions' => ($character['conditions'] ?? []),
			'items' => ($character['items'] ?? []),
			'attendanceStatus' => (string)($record['status'] ?? ''),
			'slNotesPublic' => (string)($character['slNotesPublic'] ?? ''),
			'slNotesPrivate' => (string)($character['slNotesPrivate'] ?? ''),
		];
	}//end buildCastEntry()

	/**
	 * The ids of every item the cast carries, first seen first.
	 *
	 * @param list<array<string,mixed>> $characters The cast characters, in register order.
	 *
	 * @return list<int|string> The item ids.
	 */
	private function uniqueItemIds(array $characters): array {
		$uniqueItems = [];
		foreach ($characters as $character) {
			$items = $character['items'] ?? [];
			if (is_array($items) === false) {
				continue;
			}

			foreach ($items as $itemId) {
				$uniqueItems[(string)$itemId] = true;
			}
		}

		return array_keys($uniqueItems);
	}//end uniqueItemIds()

	/**
	 * Whether a character's `events[]` references the given event.
	 *
	 * @param array<string,mixed> $character The character object.
	 * @param string $eventId The event UUID.
	 *
	 * @return bool True when the character is cast in the event.
	 */
	private function attendsEvent(array $character, string $eventId): bool {
		$events = $character['events'] ?? [];

		return is_array($events) === true
			&& in_array($eventId, array_map('strval', $events), true) === true;
	}//end attendsEvent()

	/**
	 * Fetch all characters, tolerating OpenRegister absence.
	 *
	 * Non-array rows are filtered out here so callers never re-check.
	 *
	 * @return array<int,array<string,mixed>> The character objects.
	 */
	private function fetchCharacters(): array {
		try {
			$characters = $this->objectFetcher->getObjects('character');
		} catch (\Exception $exception) {
			return [];
		}

		return array_values(array_filter($characters, 'is_array'));
	}//end fetchCharacters()

	/**
	 * Index players by their id for cast player-name resolution.
	 *
	 * @return array<string,array<string,mixed>> Players indexed by id.
	 */
	private function indexPlayers(): array {
		$indexed = [];
		try {
			$players = $this->objectFetcher->getObjects('player');
		} catch (\Exception $exception) {
			return $indexed;
		}

		foreach ($players as $player) {
			if (is_array($player) === true && isset($player['id']) === true) {
				$indexed[(string)$player['id']] = $player;
			}
		}

		return $indexed;
	}//end indexPlayers()

	/**
	 * Load the attendance records for an event, indexed by character id.
	 *
	 * Degrades gracefully: when the `larping_attendance` schema (or OpenRegister)
	 * is unavailable this returns an empty map and `false` availability rather
	 * than throwing, so both the roster read and the run-sheet keep working.
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return array{0: array<string,array<string,mixed>>, 1: bool} The [records-by-character, available] tuple.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	private function loadAttendance(string $eventId): array {
		try {
			$records = $this->objectFetcher->getObjects(
				objectType: 'attendance',
				filters: ['event' => $eventId]
			);
		} catch (\Exception $exception) {
			return [[], false];
		}

		return [$this->indexByCharacter(records: $records), true];
	}//end loadAttendance()

	/**
	 * Index attendance records by their character id; a later record wins.
	 *
	 * @param array<int,mixed> $records The attendance records.
	 *
	 * @return array<string,array<string,mixed>> The records by character id.
	 */
	private function indexByCharacter(array $records): array {
		$byCharacter = [];
		foreach ($records as $record) {
			if (is_array($record) === false) {
				continue;
			}

			$characterId = (string)($record['character'] ?? '');
			if ($characterId !== '') {
				$byCharacter[$characterId] = $record;
			}
		}

		return $byCharacter;
	}//end indexByCharacter()

	/**
	 * Resolve a character's player display name.
	 *
	 * Falls back to the character's `ocName` (the player's name in this data
	 * model) when the linked player object cannot be resolved.
	 *
	 * @param array<string,mixed> $character The character object.
	 * @param array<string,array<string,mixed>> $players Players indexed by id.
	 *
	 * @return string The player display name.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	private function resolvePlayerName(array $character, array $players): string {
		$playerName = (string)($character['ocName'] ?? '');
		$playerId = (string)($character['player'] ?? ($character['ocName'] ?? ''));
		if ($playerId !== '' && isset($players[$playerId]) === true) {
			$playerName = (string)($players[$playerId]['name'] ?? '');
		}

		return $playerName;
	}//end resolvePlayerName()

	/**
	 * Sort a list of name-bearing rows case-insensitively by name, in place.
	 *
	 * @param array<int,array<string,mixed>> $rows The rows to sort.
	 *
	 * @return void
	 */
	private function sortByName(array &$rows): void {
		usort(
			$rows,
			static function (array $a, array $b): int {
				return strcasecmp((string)$a['name'], (string)$b['name']);
			}
		);
	}//end sortByName()
}//end class
