<?php

/**
 * Golden test for the XP award stage and the ability bases of CharacterService.
 *
 * Each case runs calculateCharacter() over one world with XP abilities,
 * awards and ability bases, and records the calculated stats, the warnings
 * logged and the awards read with the app's authority. Compared with
 * fixtures/character-service-xp.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CharacterConnectionGuard;
use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The XP stage and the ability bases, case by case.
 *
 * @covers \OCA\Larpinq\Service\CharacterService
 * @uses   \OCA\Larpinq\Service\EffectApplier
 * @uses   \OCA\Larpinq\Service\EffectRun
 */
class CharacterServiceXpGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/character-service-xp.golden.json';

	/**
	 * The cases.
	 *
	 * @return array<string,array<string,mixed>> Case name to case.
	 */
	private static function cases(): array {
		$awards = [
			['id' => 'w1', 'character' => 'c1', 'event' => 'ev1', 'amount' => 5, 'reason' => 'played'],
			['id' => 'w2', 'character' => 'c1', 'amount' => '3'],
			['id' => 'w3', 'character' => 'c1', 'amount' => 'lots'],
			['id' => 'w4', 'character' => 'c2', 'amount' => 7],
			['id' => 'w5', 'character' => '', 'amount' => 9],
			['id' => 'w6', 'amount' => 9],
			'not an award',
			['id' => 'w7', 'character' => 'c1'],
			['id' => 'w8', 'character' => 'c1', 'amount' => 2.9, 'reason' => 'float'],
		];
		$own = [['id' => 'o1', 'character' => 'c1', 'amount' => 11, 'reason' => 'own'], 'junk'];
		$abilities = [
			['id' => 'a1', 'name' => 'Strength', 'base' => 3],
			['id' => 'x1', 'name' => 'XP', 'base' => 10],
		];

		$cases = [
			'awards on the character' => [],
			'no awards schema' => ['awardsThrow' => true],
			'no awards' => ['awards' => []],
			'character without id' => ['character' => ['skills' => []]],
			'character with empty id' => ['character' => ['id' => '']],
			'other character' => ['character' => ['id' => 'c2']],
			'character with no awards' => ['character' => ['id' => 'c9']],
			'no xp ability' => ['abilities' => [['id' => 'a1', 'name' => 'Strength', 'base' => 3]]],
			'experience ability' => ['abilities' => [['id' => 'e1', 'name' => 'Total Experience', 'base' => 1], ['id' => 'x1', 'name' => 'XP']]],
			'first xp ability wins' => ['abilities' => [['id' => 'x1', 'name' => 'xp', 'base' => 1], ['id' => 'x2', 'name' => 'XP', 'base' => 2]]],
			'xp ability without base' => ['abilities' => [['id' => 'x1', 'name' => 'XP']]],
			'non-numeric bases' => [
				'abilities' => [['id' => 'a1', 'name' => 'Strength', 'base' => 'high'], ['id' => 'x1', 'base' => 'x'], ['id' => 'a3', 'name' => 'Luck', 'base' => '4']],
			],
			'null base' => ['abilities' => [['id' => 'a1', 'name' => 'Strength', 'base' => null], ['id' => 'x1', 'name' => 'XP', 'base' => 1]]],
			'player owns the character' => ['owns' => true, 'own' => $own],
			'player owns, own read fails' => ['owns' => true, 'ownThrows' => true],
			'player owns, no own awards' => ['owns' => true, 'own' => []],
			'player does not own' => ['owns' => false, 'own' => $own],
			'signed out with a guard' => ['owns' => true, 'own' => $own, 'uid' => null],
		];

		foreach ($cases as $name => $case) {
			$cases[$name] = $case + [
				'abilities' => $abilities,
				'awards' => $awards,
				'awardsThrow' => false,
				'character' => ['id' => 'c1'],
				'owns' => null,
				'own' => [],
				'ownThrows' => false,
				'uid' => 'player1',
			];
		}

		return $cases;
	}//end cases()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach (self::cases() as $name => $case) {
			$actual[$name] = json_encode(value: $this->outcome(case: $case), flags: JSON_PRETTY_PRINT);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: JSON_PRETTY_PRINT)."\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()

	/**
	 * Run one case.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return array<string,mixed> The stats, the log lines and the reads.
	 */
	private function outcome(array $case): array {
		$trace = ['warnings' => [], 'debug' => 0, 'ownReads' => [], 'ownsAsked' => []];
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $type) use ($case): array {
				if ($type === 'xpAward' && $case['awardsThrow'] === true) {
					throw new \RuntimeException('no schema');
				}

				return match ($type) {
					'ability' => $case['abilities'],
					'xpAward' => $case['awards'],
					default => [],
				};
			}
		);
		$fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $filters) use ($case, &$trace): array {
				$trace['ownReads'][] = [$objectType, $filters];
				if ($case['ownThrows'] === true) {
					throw new \RuntimeException('unreadable');
				}

				return $case['own'];
			}
		);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message, array $context) use (&$trace): void {
				$trace['warnings'][] = [$message, $context];
			}
		);
		$logger->method('debug')->willReturnCallback(
			function () use (&$trace): void {
				$trace['debug']++;
			}
		);

		$guard = null;
		$session = null;
		if ($case['owns'] !== null) {
			$guard = $this->createMock(originalClassName: CharacterConnectionGuard::class);
			$guard->method('owns')->willReturnCallback(
				function (string $objectType, string $id, string $userId) use ($case, &$trace): bool {
					$trace['ownsAsked'][] = [$objectType, $id, $userId];
					return $case['owns'] === true && $userId === 'player1';
				}
			);
			$session = $this->createMock(originalClassName: IUserSession::class);
			$user = null;
			if ($case['uid'] !== null) {
				$user = $this->createMock(originalClassName: IUser::class);
				$user->method('getUID')->willReturn($case['uid']);
			}

			$session->method('getUser')->willReturn($user);
		}

		$service = new CharacterService(
			objectFetcher: $fetcher,
			logger: $logger,
			effectApplier: new EffectApplier(),
			connectionGuard: $guard,
			userSession: $session
		);
		$first = $service->calculateCharacter(character: $case['character']);
		$second = $service->calculateCharacter(character: $case['character']);

		return ['first' => $first, 'second' => $second] + $trace;
	}//end outcome()
}//end class
