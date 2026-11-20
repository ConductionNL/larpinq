<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Larpinq\Service\CastingSolver;
use PHPUnit\Framework\TestCase;

/**
 * REQ-ECC-002: the best overall casting.
 *
 * @covers \OCA\Larpinq\Service\CastingSolver
 */
class CastingSolverTest extends TestCase {

	public function testTheSeedGivesEveryPlayerARankedCharacter(): void {
		$result = (new CastingSolver())->solve(
			['anna', 'pieter', 'sanne'],
			['chancellor', 'spy', 'heir', 'bard'],
			['anna' => ['heir' => 1, 'spy' => 2], 'pieter' => ['heir' => 1, 'bard' => 2], 'sanne' => ['spy' => 1]]
		);

		self::assertSame(
			[
				['registration' => 'anna', 'character' => 'heir', 'rank' => 1],
				['registration' => 'pieter', 'character' => 'bard', 'rank' => 2],
				['registration' => 'sanne', 'character' => 'spy', 'rank' => 1],
			],
			$result['pairs']
		);
		self::assertSame([], $result['unmatched']);
	}

	public function testTheTotalRankIsTheLowestPossible(): void {
		// Greedy by sign-up gives a the x (1) and b the y (3): total 4.
		// The best total gives a the y (2) and b the x (1): total 3.
		$result = (new CastingSolver())->solve(['a', 'b'], ['x', 'y'], ['a' => ['x' => 1, 'y' => 2], 'b' => ['x' => 1, 'y' => 3]]);

		self::assertSame(3, array_sum(array_column($result['pairs'], 'rank')));
		self::assertSame(['a' => 'y', 'b' => 'x'], array_column($result['pairs'], 'character', 'registration'));
	}

	public function testAtEqualTotalsMoreFirstChoicesWin(): void {
		// Both pairings total 4. a y(3) + b x(1) has a first choice; a x(2) + b y(2) has none
		// but suits the earlier sign-up better, so only the first-choice rule picks the first.
		$result = (new CastingSolver())->solve(['a', 'b'], ['x', 'y'], ['a' => ['x' => 2, 'y' => 3], 'b' => ['x' => 1, 'y' => 2]]);

		self::assertSame(['a' => 'y', 'b' => 'x'], array_column($result['pairs'], 'character', 'registration'));
	}

	public function testAtEqualTotalsAndFirstsTheEarlierSignUpWins(): void {
		// Both pairings total 7 with no first choice; the earlier sign-up gets the better rank.
		$ranks = ['one' => ['x' => 2, 'y' => 4], 'two' => ['x' => 3, 'y' => 5]];
		$solver = new CastingSolver();

		self::assertSame(['one' => 'x', 'two' => 'y'], array_column($solver->solve(['one', 'two'], ['x', 'y'], $ranks)['pairs'], 'character', 'registration'));
		self::assertSame(['two' => 'x', 'one' => 'y'], array_column($solver->solve(['two', 'one'], ['x', 'y'], $ranks)['pairs'], 'character', 'registration'));
	}

	public function testTheEarliestSignUpGetsTheBetterRankAmongEqualCastings(): void {
		// p0 z(4) + p1 y(4) + p2 x(2) and p0 y(5) + p1 z(3) + p2 x(2) both total 10 with no first
		// choice; the first sign-up gets its rank 4 rather than its rank 5.
		$ranks  = ['p0' => ['x' => 3, 'y' => 5, 'z' => 4], 'p1' => ['y' => 4, 'z' => 3], 'p2' => ['x' => 2, 'z' => 5]];
		$result = (new CastingSolver())->solve(['p0', 'p1', 'p2'], ['x', 'y', 'z'], $ranks);

		self::assertSame(['p0' => 'z', 'p1' => 'y', 'p2' => 'x'], array_column($result['pairs'], 'character', 'registration'));
	}

	public function testAPlayerWithoutARankedMatchIsListedAndGetsNothingUnranked(): void {
		$result = (new CastingSolver())->solve(['a', 'b'], ['x', 'z'], ['a' => ['x' => 1], 'b' => ['x' => 2]]);

		self::assertSame([['registration' => 'a', 'character' => 'x', 'rank' => 1]], $result['pairs']);
		self::assertSame(['b'], $result['unmatched']);
	}

	public function testNoCharacterOrPlayerIsCastTwice(): void {
		$players = [];
		$ranks   = [];
		for ($i = 0; $i < 30; $i++) {
			$players[] = 'p' . $i;
			$ranks['p' . $i] = ['c' . ($i % 7) => 1, 'c' . (($i + 3) % 11) => 2, 'c' . (($i * 5) % 13) => 3];
		}

		$result = (new CastingSolver())->solve($players, array_map(static fn (int $i): string => 'c' . $i, range(0, 12)), $ranks);

		$cast = array_column($result['pairs'], 'character');
		self::assertSame(count($cast), count(array_unique($cast)));
		self::assertCount(13, $result['pairs']);
		self::assertCount(17, $result['unmatched']);
	}

	public function testFiveHundredByFiveHundredRunsWellWithinTheBound(): void {
		$players    = [];
		$characters = [];
		$ranks      = [];
		for ($i = 0; $i < CastingSolver::MAX_SIZE; $i++) {
			$players[]    = 'p' . $i;
			$characters[] = 'c' . $i;
		}

		foreach ($players as $index => $player) {
			for ($rank = 1; $rank <= CastingSolver::MAX_RANK; $rank++) {
				$ranks[$player]['c' . (($index * 7 + $rank * 13) % CastingSolver::MAX_SIZE)] = $rank;
			}
		}

		$started = microtime(true);
		$result  = (new CastingSolver())->solve($players, $characters, $ranks);

		self::assertLessThan(10.0, (microtime(true) - $started));
		self::assertSame(CastingSolver::MAX_SIZE, count($result['pairs']) + count($result['unmatched']));
	}

	public function testMoreThanFiveHundredIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);

		(new CastingSolver())->solve(array_map(static fn (int $i): string => 'p' . $i, range(0, CastingSolver::MAX_SIZE)), ['c'], []);
	}

	public function testNothingToCastProposesNothing(): void {
		self::assertSame(['pairs' => [], 'unmatched' => ['a']], (new CastingSolver())->solve(['a'], [], []));
	}
}//end class
