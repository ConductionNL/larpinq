<?php

/**
 * AttendeeXpAward for Larpinq
 *
 * The XP award an agent makes for one event (hermiq-ai-tooling REQ-001): one
 * award per character checked in at the event, and none for a registered or
 * no-show row. The recipients are decided here, never by the caller. Each
 * award is written with the caller's rights, so OpenRegister's `gamemasters`
 * create rule on `xpAward` stays the enforcement point.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-001--a-curated-event-scoped-xp-award-tool-exists
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Awards XP to the checked-in characters of one event.
 *
 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-001--a-curated-event-scoped-xp-award-tool-exists
 */
class AttendeeXpAward {

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher Reads attendance and writes awards with the caller's rights.
	 * @param EventRosterService    $roster        Reads the event.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly EventRosterService $roster,
	) {
	}//end __construct()

	/**
	 * Award the XP and report each character.
	 *
	 * `awardedAt` and `awardedBy` are stamped here and `awardedVia` marks the
	 * award as made through an agent tool. The writes are not one transaction,
	 * so every character is reported `created` or `failed`.
	 *
	 * @param string $eventId   The event UUID.
	 * @param int    $amount    The XP per character, at least 1.
	 * @param string $reason    Why the XP is awarded.
	 * @param string $awardedBy The invoking principal.
	 *
	 * @return array<string, mixed> `{event, amount, reason, results}`.
	 *
	 * @throws InvalidArgumentException When the amount is not positive, the event cannot be read, or nobody is checked in.
	 *
	 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-001--a-curated-event-scoped-xp-award-tool-exists
	 */
	public function award(string $eventId, int $amount, string $reason, string $awardedBy): array {
		if ($amount < 1) {
			throw new InvalidArgumentException('The amount has to be at least 1 XP.');
		}

		if ($this->roster->getEvent(eventId: $eventId) === null) {
			throw new InvalidArgumentException('Event not found: ' . $eventId);
		}

		$checkedIn = $this->checkedIn(eventId: $eventId);
		if ($checkedIn === []) {
			throw new InvalidArgumentException(
				'Nobody is checked in at event ' . $eventId . ', so no XP was awarded. Check characters in first.'
			);
		}

		$results = [];
		foreach ($checkedIn as $characterId) {
			$results[] = $this->awardOne(
				award: [
					'event' => $eventId,
					'character' => $characterId,
					'amount' => $amount,
					'reason' => $reason,
					'awardedBy' => $awardedBy,
					'awardedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
					'awardedVia' => 'mcp',
				]
			);
		}

		return ['event' => $eventId, 'amount' => $amount, 'reason' => $reason, 'results' => $results];
	}//end award()

	/**
	 * The characters with a `checked-in` attendance row for the event, in row order, once each.
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return list<string> The character ids.
	 */
	private function checkedIn(string $eventId): array {
		try {
			$rows = $this->objectFetcher->getObjects(objectType: 'attendance', filters: ['event' => $eventId]);
		} catch (\Exception $exception) {
			return [];
		}

		$ids = [];
		foreach ($rows as $row) {
			$characterId = (string)($row['character'] ?? '');
			if ($characterId !== '' && ($row['status'] ?? '') === 'checked-in' && in_array($characterId, $ids, true) === false) {
				$ids[] = $characterId;
			}
		}

		return $ids;
	}//end checkedIn()

	/**
	 * Write one award and report it.
	 *
	 * @param array<string, mixed> $award The award object.
	 *
	 * @return array{character: string, status: string, award?: string, error?: string} The result.
	 */
	private function awardOne(array $award): array {
		$characterId = (string)$award['character'];
		try {
			$saved = $this->objectFetcher->saveObject(objectType: 'xpAward', data: $award);
		} catch (\Exception $exception) {
			return ['character' => $characterId, 'status' => 'failed', 'error' => $exception->getMessage()];
		}

		return ['character' => $characterId, 'status' => 'created', 'award' => (string)($saved['id'] ?? ($saved['uuid'] ?? ''))];
	}//end awardOne()
}//end class
