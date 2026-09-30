<?php

/**
 * The events a player was checked in at (players-attendance-history).
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/events-players/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Lists a player's check-ins across characters and years, newest first.
 *
 * Attendance is read by game masters and the record's owner only (DECISIONS
 * row 30), so this service reads it with the app's authority, one character
 * at a time. The caller MUST have decided first that the signed-in user may
 * see this player's history (a game master, or the player themself through
 * CharacterConnectionGuard::ownsPlayer). The characters and events are read as
 * the signed-in user.
 *
 * @spec openspec/specs/events-players/spec.md
 */
class PlayerAttendanceService {

	/**
	 * The most characters of one player, and check-ins of one character, read.
	 *
	 * @var integer
	 */
	private const LIMIT = 500;

	/**
	 * Events read so far, by id; null when unreadable.
	 *
	 * @var array<string, array<string, mixed>|null>
	 */
	private array $events = [];

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher Reads characters, events and attendance.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
	) {
	}//end __construct()

	/**
	 * The player's check-ins, newest event first, and how many events they attended.
	 *
	 * @param string $playerId The player UUID.
	 *
	 * @return array{count: int, events: list<array<string, mixed>>} The history.
	 *
	 * @spec openspec/specs/events-players/spec.md
	 */
	public function history(string $playerId): array {
		$rows = [];
		foreach ($this->objectFetcher->getObjects(objectType: 'character', limit: self::LIMIT, filters: ['ocName' => $playerId]) as $character) {
			$characterId = $this->idOf(value: ($character['id'] ?? null));
			if ($characterId === '') {
				continue;
			}

			$records = $this->objectFetcher->getObjectsWithAppAuthority(
				objectType: 'attendance',
				filters: ['character' => $characterId, 'status' => 'checked-in'],
				limit: self::LIMIT
			);
			foreach ($records as $record) {
				$rows[] = $this->row(record: $record, character: $character);
			}
		}

		usort(
			$rows,
			static fn (array $left, array $right): int => strcmp((string)$right['eventStartDate'], (string)$left['eventStartDate'])
		);

		$events = array_unique(array_map(static fn (array $row): string => (string)$row['event']['id'], $rows));

		return ['count' => count($events), 'events' => $rows];
	}//end history()

	/**
	 * One line of the history.
	 *
	 * @param array<string, mixed> $record The attendance record.
	 * @param array<string, mixed> $character The character it is about.
	 *
	 * @return array<string, mixed> The line.
	 */
	private function row(array $record, array $character): array {
		$eventId = $this->idOf(value: ($record['event'] ?? null));
		$event = $this->event(eventId: $eventId);

		return [
			'id' => $this->idOf(value: ($record['id'] ?? null)),
			'event' => ['id' => $eventId, 'name' => (string)($event['name'] ?? '')],
			'character' => ['id' => $this->idOf(value: ($character['id'] ?? null)), 'name' => (string)($character['name'] ?? '')],
			'eventStartDate' => (string)($event['startDate'] ?? ''),
			'checkedInAt' => (string)($record['checkedInAt'] ?? ''),
		];
	}//end row()

	/**
	 * An event read as the signed-in user, once per request.
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return array<string, mixed>|null The event, or null when it cannot be read.
	 */
	private function event(string $eventId): ?array {
		if (array_key_exists($eventId, $this->events) === false) {
			try {
				$this->events[$eventId] = $this->objectFetcher->getObject(objectType: 'event', id: $eventId);
			} catch (\Throwable $e) {
				$this->events[$eventId] = null;
			}
		}

		return $this->events[$eventId];
	}//end event()

	/**
	 * The UUID of a relation value, which OpenRegister may hand over as a
	 * string or as an object with an id.
	 *
	 * @param mixed $value The relation value.
	 *
	 * @return string The UUID, or ''.
	 */
	private function idOf(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? $value['uuid'] ?? '');
		}

		return is_string($value) === true ? $value : '';
	}//end idOf()
}//end class
