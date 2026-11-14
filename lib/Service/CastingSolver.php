<?php

/**
 * The best overall casting of players to characters.
 *
 * Solves the assignment problem with the Hungarian method on a cost matrix
 * built from the players' ranks. The costs are weighted so that one solution
 * is best on three counts, in this order: the lowest total rank, then the most
 * first choices, then the better ranks for earlier sign-ups. A pair the player
 * did not rank costs more than every ranked pair together, so the solver only
 * makes such a pair when it has to fill the square matrix, and those pairs are
 * dropped: that player is listed as unmatched.
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
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use InvalidArgumentException;

/**
 * Pairs players with characters to minimise the total rank.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
 */
class CastingSolver {

	/**
	 * The most players or characters one proposal takes.
	 */
	public const MAX_SIZE = 500;

	/**
	 * The highest rank a player gives.
	 */
	public const MAX_RANK = 5;

	/**
	 * Propose the casting.
	 *
	 * @param list<string>                       $players    The registration ids, earliest sign-up first.
	 * @param list<string>                       $characters The ids of the characters open for casting.
	 * @param array<string, array<string, int>> $ranks      Per registration id, the rank (1 to 5) per character id.
	 *
	 * @return array{pairs: list<array{registration: string, character: string, rank: int}>, unmatched: list<string>}
	 *         The pairs in sign-up order, and the players left without a ranked character.
	 *
	 * @throws InvalidArgumentException When there are more than MAX_SIZE players or characters.
	 *
	 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
	 */
	public function solve(array $players, array $characters, array $ranks): array {
		$players    = array_values(array_unique($players));
		$characters = array_values(array_unique($characters));
		if (count($players) > self::MAX_SIZE || count($characters) > self::MAX_SIZE) {
			throw new InvalidArgumentException('A casting proposal takes at most ' . self::MAX_SIZE . ' players and ' . self::MAX_SIZE . ' characters.');
		}

		if ($players === [] || $characters === []) {
			return ['pairs' => [], 'unmatched' => $players];
		}

		$cost       = $this->costMatrix(players: $players, characters: $characters, ranks: $ranks);
		$assignment = $this->hungarian(cost: $cost);

		$pairs     = [];
		$unmatched = [];
		foreach ($players as $row => $registration) {
			$column = ($assignment[$row] ?? -1);
			$rank   = $this->rankOf(ranks: $ranks, registration: $registration, character: ($characters[$column] ?? ''));
			if ($rank === null) {
				$unmatched[] = $registration;
				continue;
			}

			$pairs[] = ['registration' => $registration, 'character' => $characters[$column], 'rank' => $rank];
		}

		return ['pairs' => $pairs, 'unmatched' => $unmatched];
	}//end solve()

	/**
	 * The square cost matrix: players by characters, padded with unranked cost.
	 *
	 * @param list<string>                       $players    The registration ids, earliest first.
	 * @param list<string>                       $characters The character ids.
	 * @param array<string, array<string, int>> $ranks      The ranks.
	 *
	 * @return list<list<int>> The matrix.
	 */
	private function costMatrix(array $players, array $characters, array $ranks): array {
		$playerCount = count($players);
		$size        = max($playerCount, count($characters));

		// Lexicographic weights: the sign-up term of a whole solution stays below
		// one first choice, and every first-choice difference stays below one rank.
		// The sign-up term rewards a match by how good it is and how early the
		// player signed up, so an earlier sign-up wins a tie on the same rank.
		$signUpTotal = (self::MAX_RANK * $playerCount * $playerCount);
		$firstWeight = ($signUpTotal + 1);
		$rankWeight  = (($playerCount + 1) * $firstWeight + 1);
		$unranked    = ((self::MAX_RANK * $playerCount + 1) * $rankWeight);

		$matrix = [];
		for ($row = 0; $row < $size; $row++) {
			$line = array_fill(0, $size, $unranked);
			for ($column = 0; $column < $size; $column++) {
				$rank = $this->rankOf(ranks: $ranks, registration: ($players[$row] ?? ''), character: ($characters[$column] ?? ''));
				if ($rank !== null) {
					$first         = (int)($rank === 1);
					$line[$column] = ($rank * $rankWeight - $first * $firstWeight - (self::MAX_RANK + 1 - $rank) * ($playerCount - $row));
				}
			}

			$matrix[] = $line;
		}

		return $matrix;
	}//end costMatrix()

	/**
	 * The rank a player gave a character, or null when they did not rank it.
	 *
	 * @param array<string, array<string, int>> $ranks        The ranks.
	 * @param string                             $registration The registration id.
	 * @param string                             $character    The character id.
	 *
	 * @return int|null The rank 1 to 5, or null.
	 */
	private function rankOf(array $ranks, string $registration, string $character): ?int {
		$rank = ($ranks[$registration][$character] ?? null);
		if (is_int($rank) === false || $rank < 1 || $rank > self::MAX_RANK) {
			return null;
		}

		return $rank;
	}//end rankOf()

	/**
	 * The Hungarian method with potentials on a square matrix, in O(n^3).
	 *
	 * @param list<list<int>> $cost The square cost matrix.
	 *
	 * @return array<int, int> The column assigned to each row.
	 */
	private function hungarian(array $cost): array {
		$size  = count($cost);
		$state = [
			'row' => array_fill(0, ($size + 1), 0),
			'column' => array_fill(0, ($size + 1), 0),
			'rowOfColumn' => array_fill(0, ($size + 1), 0),
			'way' => array_fill(0, ($size + 1), 0),
		];

		for ($row = 1; $row <= $size; $row++) {
			$this->assignRow(cost: $cost, size: $size, row: $row, state: $state);
		}

		$assignment = [];
		for ($column = 1; $column <= $size; $column++) {
			$assignment[($state['rowOfColumn'][$column] - 1)] = ($column - 1);
		}

		return $assignment;
	}//end hungarian()

	/**
	 * Add one row to the assignment along a shortest augmenting path.
	 *
	 * @param list<list<int>>                 $cost  The square cost matrix.
	 * @param int                             $size  The matrix size.
	 * @param int                             $row   The row to add, 1-based.
	 * @param array<string, array<int, int>> $state The potentials, the row per column and the path, updated in place.
	 *
	 * @return void
	 */
	private function assignRow(array $cost, int $size, int $row, array &$state): void {
		$state['rowOfColumn'][0] = $row;
		$column  = 0;
		$minimum = array_fill(0, ($size + 1), PHP_INT_MAX);
		$used    = array_fill(0, ($size + 1), false);
		do {
			$used[$column] = true;
			$next = $this->closestColumn(cost: $cost, size: $size, from: $column, used: $used, minimum: $minimum, state: $state);
			$this->shiftPotentials(size: $size, delta: $minimum[$next], used: $used, minimum: $minimum, state: $state);
			$column = $next;
		} while ($state['rowOfColumn'][$column] !== 0);

		do {
			$previous = $state['way'][$column];
			$state['rowOfColumn'][$column] = $state['rowOfColumn'][$previous];
			$column = $previous;
		} while ($column !== 0);
	}//end assignRow()

	/**
	 * Relax the reduced costs from one column's row and pick the closest unused column.
	 *
	 * @param list<list<int>>                 $cost    The square cost matrix.
	 * @param int                             $size    The matrix size.
	 * @param int                             $from    The column whose row is relaxed.
	 * @param array<int, bool>                $used    The columns on the tree.
	 * @param array<int, int>                 $minimum The smallest reduced cost per column, updated in place.
	 * @param array<string, array<int, int>> $state   The potentials and the path, updated in place.
	 *
	 * @return int The closest unused column.
	 */
	private function closestColumn(array $cost, int $size, int $from, array $used, array &$minimum, array &$state): int {
		$current = $state['rowOfColumn'][$from];
		$line    = $cost[($current - 1)];
		$delta   = PHP_INT_MAX;
		$next    = 0;
		for ($candidate = 1; $candidate <= $size; $candidate++) {
			if ($used[$candidate] === true) {
				continue;
			}

			$reduced = ($line[($candidate - 1)] - $state['row'][$current] - $state['column'][$candidate]);
			if ($reduced < $minimum[$candidate]) {
				$minimum[$candidate]      = $reduced;
				$state['way'][$candidate] = $from;
			}

			if ($minimum[$candidate] < $delta) {
				$delta = $minimum[$candidate];
				$next  = $candidate;
			}
		}

		return $next;
	}//end closestColumn()

	/**
	 * Move the potentials by delta: rows and columns on the tree, and the slack of the rest.
	 *
	 * @param int                             $size    The matrix size.
	 * @param int                             $delta   The smallest slack.
	 * @param array<int, bool>                $used    The columns on the tree.
	 * @param array<int, int>                 $minimum The smallest reduced cost per column, updated in place.
	 * @param array<string, array<int, int>> $state   The potentials, updated in place.
	 *
	 * @return void
	 */
	private function shiftPotentials(int $size, int $delta, array $used, array &$minimum, array &$state): void {
		for ($candidate = 0; $candidate <= $size; $candidate++) {
			if ($used[$candidate] === true) {
				$state['row'][$state['rowOfColumn'][$candidate]] += $delta;
				$state['column'][$candidate] -= $delta;
				continue;
			}

			$minimum[$candidate] -= $delta;
		}
	}//end shiftPotentials()
}//end class
