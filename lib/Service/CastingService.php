<?php

/**
 * Casting: the best overall proposal, and the confirmation that writes it.
 *
 * The proposal reads the characters open for casting on an event and the
 * rankings of its accepted registrations, and writes nothing. Confirming
 * writes each pair through OpenRegister's normal object writes as the game
 * master: the character gets the player as owner and leaves casting, and the
 * registration gets the character.
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
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-game-masters-confirm-the-casting-req-ecc-003
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Proposes and confirms the casting of an event.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
 */
class CastingService {

	/**
	 * The most rows read per object type: one more than the solver takes, so an
	 * event over the limit is refused instead of silently cut short.
	 */
	private const READ_LIMIT = (CastingSolver::MAX_SIZE * CastingSolver::MAX_RANK + 1);

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes OpenRegister objects.
	 * @param CastingSolver         $solver  The assignment solver.
	 * @param LoggerInterface       $logger  Logger.
	 *
	 * @spec exclude pure framework plumbing: constructor dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly CastingSolver $solver,
		private readonly LoggerInterface $logger,
	) {

	}//end __construct()

	/**
	 * Propose the casting of an event. Writes nothing.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array{
	 *     pairs: list<array{registration: string, character: string, characterName: string, rank: int}>,
	 *     unmatched: list<string>,
	 *     characters: list<array{id: string, name: string, ranked: array<int, int>}>
	 * } The pairs, the registrations left without a ranked character, and per open character how often it was ranked at each rank.
	 *
	 * @throws CastingRefusedException When the event has more than 500 players or characters in casting.
	 *
	 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
	 */
	public function propose(string $eventId): array {
		$characters    = $this->openCharacters(eventId: $eventId);
		$registrations = $this->acceptedRegistrations(eventId: $eventId);
		$ranks         = $this->ranks(eventId: $eventId, registrations: $registrations, characters: $characters);

		// Only players who ranked an open character take part, earliest sign-up first.
		$players = array_values(array_filter(array_keys($registrations), static fn (string $id): bool => isset($ranks[$id]) === true));

		try {
			$result = $this->solver->solve(players: $players, characters: array_keys($characters), ranks: $ranks);
		} catch (InvalidArgumentException $e) {
			throw new CastingRefusedException($e->getMessage(), 0, $e);
		}

		$pairs = [];
		foreach ($result['pairs'] as $pair) {
			$pair['characterName'] = (string)($characters[$pair['character']]['name'] ?? '');
			$pairs[] = $pair;
		}

		return [
			'pairs' => $pairs,
			'unmatched' => $result['unmatched'],
			'characters' => $this->rankCounts(characters: $characters, ranks: $ranks),
		];
	}//end propose()

	/**
	 * Confirm the casting the game master kept or changed.
	 *
	 * Each pair is checked against the event: the character must be open for
	 * casting on it, the registration must be accepted on it, and no character
	 * or registration may appear twice. A pair that fails is refused by name and
	 * the others are written.
	 *
	 * @param string                                                $eventId The event.
	 * @param list<array{registration?: mixed, character?: mixed}> $pairs   The pairs to write.
	 *
	 * @return array{
	 *     written: list<array{registration: string, character: string}>,
	 *     refused: list<array{registration: string, character: string, reason: string}>
	 * } The pairs written, and the pairs refused with the reason.
	 *
	 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-game-masters-confirm-the-casting-req-ecc-003
	 */
	public function confirm(string $eventId, array $pairs): array {
		$characters    = $this->openCharacters(eventId: $eventId);
		$registrations = $this->acceptedRegistrations(eventId: $eventId);

		$written = [];
		$refused = [];
		$seen    = ['registration' => [], 'character' => []];
		foreach ($pairs as $pair) {
			$registrationId = (string)($pair['registration'] ?? '');
			$characterId    = (string)($pair['character'] ?? '');
			$reason         = $this->refusal(
				registration: ($registrations[$registrationId] ?? null),
				character: ($characters[$characterId] ?? null),
				twice: (isset($seen['registration'][$registrationId]) === true || isset($seen['character'][$characterId]) === true)
			);
			$seen['registration'][$registrationId] = true;
			$seen['character'][$characterId]       = true;

			if ($reason === null) {
				$reason = $this->write(registration: $registrations[$registrationId], characterId: $characterId, registrationId: $registrationId);
			}

			if ($reason !== null) {
				$refused[] = ['registration' => $registrationId, 'character' => $characterId, 'reason' => $reason];
				continue;
			}

			$written[] = ['registration' => $registrationId, 'character' => $characterId];
		}//end foreach

		return ['written' => $written, 'refused' => $refused];
	}//end confirm()

	/**
	 * Why a pair cannot be confirmed, or null when it can.
	 *
	 * @param array<string, mixed>|null $registration The accepted registration, or null when it is not one of this event.
	 * @param array<string, mixed>|null $character    The open character, or null when it is not open on this event.
	 * @param bool                      $twice        Whether the registration or the character is already in an earlier pair.
	 *
	 * @return string|null The reason, or null.
	 */
	private function refusal(?array $registration, ?array $character, bool $twice): ?string {
		if ($registration === null) {
			return 'registration-not-accepted';
		}

		if ($character === null) {
			return 'character-not-open';
		}

		if ($twice === true) {
			return 'cast-twice';
		}

		if ((string)($registration['player'] ?? '') === '') {
			return 'registration-without-player';
		}

		return null;
	}//end refusal()

	/**
	 * Write one pair: the character to its player, the registration to its character.
	 *
	 * @param array<string, mixed> $registration   The registration.
	 * @param string               $characterId    The character id.
	 * @param string               $registrationId The registration id.
	 *
	 * @return string|null The reason the write was refused, or null when both were written.
	 */
	private function write(array $registration, string $characterId, string $registrationId): ?string {
		try {
			$this->fetcher->saveObject(
				objectType: 'character',
				data: ['ocName' => (string)$registration['player'], 'castingEvent' => null],
				uuid: $characterId
			);
		} catch (\Throwable $e) {
			$this->logger->warning('[CastingService] The character could not be cast: ' . $e->getMessage());
			return 'character-not-written';
		}

		try {
			$this->fetcher->saveObject(objectType: 'registration', data: ['character' => $characterId], uuid: $registrationId);
		} catch (\Throwable $e) {
			$this->logger->warning('[CastingService] The registration could not take the character: ' . $e->getMessage());
			return 'registration-not-written';
		}

		return null;
	}//end write()

	/**
	 * The characters open for casting on the event, keyed by id.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<string, array<string, mixed>> The characters.
	 *
	 * @throws CastingRefusedException When they cannot be read.
	 */
	private function openCharacters(string $eventId): array {
		return $this->keyed(rows: $this->read(objectType: 'character', filters: ['castingEvent' => $eventId]));
	}//end openCharacters()

	/**
	 * The accepted registrations of the event, keyed by id, earliest sign-up first.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<string, array<string, mixed>> The registrations.
	 *
	 * @throws CastingRefusedException When they cannot be read.
	 */
	private function acceptedRegistrations(string $eventId): array {
		$rows = $this->read(objectType: 'registration', filters: ['event' => $eventId, 'status' => 'accepted']);
		usort(
			$rows,
			static fn (array $a, array $b): int => [(string)($a['submittedAt'] ?? ''), (int)($a['submissionId'] ?? 0)]
				<=> [(string)($b['submittedAt'] ?? ''), (int)($b['submissionId'] ?? 0)]
		);

		return $this->keyed(rows: $rows);
	}//end acceptedRegistrations()

	/**
	 * The ranks per registration and character, from the event's preferences.
	 *
	 * Only a preference of an accepted registration for an open character
	 * counts. When a player ranked a character twice, the better rank counts.
	 *
	 * @param string                               $eventId       The event.
	 * @param array<string, array<string, mixed>> $registrations The accepted registrations.
	 * @param array<string, array<string, mixed>> $characters    The open characters.
	 *
	 * @return array<string, array<string, int>> The ranks.
	 *
	 * @throws CastingRefusedException When the preferences cannot be read.
	 */
	private function ranks(string $eventId, array $registrations, array $characters): array {
		$ranks = [];
		foreach ($this->read(objectType: 'castingpreference', filters: ['event' => $eventId]) as $preference) {
			$registration = (string)($preference['registration'] ?? '');
			$character    = (string)($preference['character'] ?? '');
			$rank         = (int)($preference['rank'] ?? 0);
			if (isset($registrations[$registration], $characters[$character]) === false || $rank < 1 || $rank > CastingSolver::MAX_RANK) {
				continue;
			}

			$ranks[$registration][$character] = min($rank, ($ranks[$registration][$character] ?? CastingSolver::MAX_RANK));
		}

		return $ranks;
	}//end ranks()

	/**
	 * Per open character, how many players ranked it at each rank.
	 *
	 * @param array<string, array<string, mixed>> $characters The open characters.
	 * @param array<string, array<string, int>>   $ranks      The ranks.
	 *
	 * @return list<array{id: string, name: string, ranked: array<int, int>}> The counts, rank 1 to 5.
	 */
	private function rankCounts(array $characters, array $ranks): array {
		$counts = [];
		foreach ($characters as $id => $character) {
			$ranked = array_fill(1, CastingSolver::MAX_RANK, 0);
			foreach ($ranks as $byCharacter) {
				if (isset($byCharacter[$id]) === true) {
					$ranked[$byCharacter[$id]]++;
				}
			}

			$counts[] = ['id' => (string)$id, 'name' => (string)($character['name'] ?? ''), 'ranked' => $ranked];
		}

		return $counts;
	}//end rankCounts()

	/**
	 * Read the rows of a type with the app's authority, scoped to the event.
	 *
	 * @param string                    $objectType The object type.
	 * @param array<string, string>     $filters    The scoping filters.
	 *
	 * @return list<array<string, mixed>> The rows.
	 *
	 * @throws CastingRefusedException When they cannot be read.
	 */
	private function read(string $objectType, array $filters): array {
		try {
			return array_values($this->fetcher->getObjectsWithAppAuthority(objectType: $objectType, filters: $filters, limit: self::READ_LIMIT));
		} catch (\Throwable $e) {
			$this->logger->warning('[CastingService] The ' . $objectType . ' rows could not be read: ' . $e->getMessage());
			throw new CastingRefusedException('The casting data could not be read.', 0, $e);
		}
	}//end read()

	/**
	 * Rows keyed by their id.
	 *
	 * @param list<array<string, mixed>> $rows The rows.
	 *
	 * @return array<string, array<string, mixed>> The rows by id.
	 */
	private function keyed(array $rows): array {
		$keyed = [];
		foreach ($rows as $row) {
			$id = (string)($row['id'] ?? ($row['@self']['id'] ?? ($row['uuid'] ?? '')));
			if ($id !== '') {
				$keyed[$id] = $row;
			}
		}

		return $keyed;
	}//end keyed()
}//end class
