<?php

/**
 * XpAwardBatchService for Larpinq
 *
 * Awards XP to a whole event in one save (events-xp-batch-award): the roster
 * with each participant's attendance and existing awards, and the batch write
 * that creates one xpAward per row, each through OpenRegister's RBAC.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * The award roster of an event and the batch award.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
class XpAwardBatchService {

	/**
	 * The object type (and config key prefix) of an XP award.
	 *
	 * @var string
	 */
	public const OBJECT_TYPE = 'xpAward';

	/**
	 * The attendance states that decide a default tick; anything else is
	 * "no check-in recorded".
	 *
	 * @var string[]
	 */
	private const RECORDED = ['checked-in', 'no-show'];

	/**
	 * Constructor.
	 *
	 * @param EventRosterService $rosterService The event roster.
	 * @param RegisterObjectFetcher $objectFetcher The register object fetcher.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly EventRosterService $rosterService,
		private readonly RegisterObjectFetcher $objectFetcher,
	) {
	}//end __construct()

	/**
	 * The award roster: every participant with attendance, existing awards
	 * and whether the row starts ticked (checked in and not yet awarded).
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return array{rows: array<int,array<string,mixed>>, attendanceAvailable: bool} The roster.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public function awardRoster(string $eventId): array {
		$roster = $this->rosterService->buildRoster(eventId: $eventId);
		$awards = $this->awardsByCharacter(eventId: $eventId);

		$rows = [];
		foreach ($roster['participants'] as $participant) {
			$characterId = (string)($participant['character'] ?? '');
			$attendance = (string)($participant['status'] ?? '');
			if (in_array($attendance, self::RECORDED, true) === false) {
				$attendance = '';
			}

			$existing = ($awards[$characterId] ?? []);
			$rows[] = [
				'character' => $characterId,
				'name' => (string)($participant['name'] ?? ''),
				'playerName' => (string)($participant['playerName'] ?? ''),
				'attendance' => $attendance,
				'awards' => $existing,
				'ticked' => $attendance === 'checked-in' && $existing === [],
			];
		}

		return ['rows' => $rows, 'attendanceAvailable' => $roster['attendanceAvailable']];
	}//end awardRoster()

	/**
	 * Create one award per row. Each row stands or falls on its own: a row
	 * for a character off the roster, without a positive amount, or for a
	 * character who already has an award for the event (unless it is marked
	 * extra with a reason) is refused, and the others are still created.
	 *
	 * @param string $eventId The event UUID.
	 * @param array<int,mixed> $rows The rows: {character, amount, reason?, extra?}.
	 * @param string $actingUid The game master making the awards.
	 *
	 * @return array{created: array<int,array<string,mixed>>, refused: array<int,array{character: string, reason: string}>} The outcome.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public function award(string $eventId, array $rows, string $actingUid): array {
		$onRoster = [];
		foreach ($this->rosterService->buildRoster(eventId: $eventId)['participants'] as $participant) {
			$onRoster[(string)($participant['character'] ?? '')] = true;
		}

		$awarded = array_map('count', $this->awardsByCharacter(eventId: $eventId));
		$created = [];
		$refused = [];

		foreach ($rows as $row) {
			$row = $this->normaliseRow(row: $row);
			$refusal = $this->refusalOf(row: $row, onRoster: $onRoster, awarded: $awarded);
			if ($refusal !== null) {
				$refused[] = ['character' => $row['character'], 'reason' => $refusal];
				continue;
			}

			try {
				$created[] = $this->objectFetcher->saveObject(
					objectType: self::OBJECT_TYPE,
					data: $this->payload(eventId: $eventId, row: $row, actingUid: $actingUid)
				);
				$awarded[$row['character']] = (($awarded[$row['character']] ?? 0) + 1);
			} catch (\Exception $exception) {
				$refused[] = ['character' => $row['character'], 'reason' => 'not-saved'];
			}
		}

		return ['created' => $created, 'refused' => $refused];
	}//end award()

	/**
	 * The xpAward object written for a row. Provenance is the server's: the
	 * acting game master and the time of the write.
	 *
	 * @param string $eventId The event UUID.
	 * @param array{character: string, amount: int|float|null, reason: string, extra: bool} $row The row.
	 * @param string $actingUid The acting game master.
	 *
	 * @return array<string,mixed> The payload.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public function payload(string $eventId, array $row, string $actingUid): array {
		$payload = [
			'event' => $eventId,
			'character' => $row['character'],
			'amount' => $row['amount'],
			'awardedBy' => $actingUid,
			'awardedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
		];
		if ($row['reason'] !== '') {
			$payload['reason'] = $row['reason'];
		}

		return $payload;
	}//end payload()

	/**
	 * Read one request row into a fixed shape.
	 *
	 * @param mixed $row The raw row.
	 *
	 * @return array{character: string, amount: int|float|null, reason: string, extra: bool} The row.
	 */
	private function normaliseRow(mixed $row): array {
		if (is_array($row) === false) {
			$row = [];
		}

		$amount = ($row['amount'] ?? null);
		if (is_string($amount) === true && is_numeric($amount) === true) {
			$amount = ($amount + 0);
		}

		if (is_int($amount) === false && is_float($amount) === false) {
			$amount = null;
		}

		$character = '';
		if (is_string($row['character'] ?? null) === true) {
			$character = $row['character'];
		}

		$reason = '';
		if (is_string($row['reason'] ?? null) === true) {
			$reason = trim($row['reason']);
		}

		return [
			'character' => $character,
			'amount' => $amount,
			'reason' => $reason,
			'extra' => ($row['extra'] ?? false) === true,
		];
	}//end normaliseRow()

	/**
	 * Why a row is refused, or null when it may be written.
	 *
	 * @param array{character: string, amount: int|float|null, reason: string, extra: bool} $row The row.
	 * @param array<string,bool> $onRoster The roster's character ids.
	 * @param array<string,int> $awarded Awards so far per character for this event.
	 *
	 * @return string|null The refusal code: not-on-roster, invalid-amount, duplicate, extra-needs-reason.
	 */
	private function refusalOf(array $row, array $onRoster, array $awarded): ?string {
		if (isset($onRoster[$row['character']]) === false) {
			return 'not-on-roster';
		}

		if ($row['amount'] === null || $row['amount'] <= 0) {
			return 'invalid-amount';
		}

		if (($awarded[$row['character']] ?? 0) === 0) {
			return null;
		}

		if ($row['extra'] === false) {
			return 'duplicate';
		}

		if ($row['reason'] === '') {
			return 'extra-needs-reason';
		}

		return null;
	}//end refusalOf()

	/**
	 * The event's existing awards, per character: {id, amount, reason, awardedBy, awardedAt}.
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return array<string,array<int,array<string,mixed>>> Awards by character id.
	 */
	private function awardsByCharacter(string $eventId): array {
		try {
			$awards = $this->objectFetcher->getObjects(objectType: self::OBJECT_TYPE, filters: ['event' => $eventId]);
		} catch (\Exception $exception) {
			return [];
		}

		$byCharacter = [];
		foreach ($awards as $award) {
			if (is_array($award) === false || (string)($award['event'] ?? '') !== $eventId) {
				continue;
			}

			$byCharacter[(string)($award['character'] ?? '')][] = [
				'id' => (string)($award['id'] ?? ''),
				'amount' => ($award['amount'] ?? 0),
				'reason' => (string)($award['reason'] ?? ''),
				'awardedBy' => (string)($award['awardedBy'] ?? ''),
				'awardedAt' => (string)($award['awardedAt'] ?? ''),
			];
		}

		return $byCharacter;
	}//end awardsByCharacter()
}//end class
