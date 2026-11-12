<?php

/**
 * Golden test for CharacterService::calculateCharacter().
 *
 * Seeded random abilities, effects (cumulative and not, positive and
 * negative), skills, items, conditions and events, and characters that link
 * them (with orphans, duplicates, empty and malformed lists). Each case's
 * result is compared, as JSON text so key order and scalar types count, with
 * fixtures/character-service.golden.json. Set RECORD_GOLDEN=1 to rewrite the
 * fixture.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Pins the stats calculateCharacter() produces.
 *
 * @covers \OCA\Larpinq\Service\CharacterService
 * @uses   \OCA\Larpinq\Service\EffectApplier
 */
class CharacterServiceGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/character-service.golden.json';

	private const KINDS = ['skill', 'item', 'condition', 'event'];

	private const PROPERTIES = ['skills', 'items', 'conditions', 'events'];

	/**
	 * One random world and character per case.
	 *
	 * @param int $seed The seed.
	 *
	 * @return array{0:array<string,array<int,array<string,mixed>>>,1:array<string,mixed>}
	 */
	private function caseFor(int $seed): array {
		$rnd = new Randomizer(new Mt19937($seed));
		$pick = static fn (array $list): mixed => $list[$rnd->getInt(0, (count($list) - 1))];

		$data = ['ability' => [], 'effect' => []];
		for ($i = 0; $i < $rnd->getInt(1, 3); $i++) {
			$data['ability'][] = ['id' => 'a'.$i, 'name' => 'Ability '.$i, 'base' => $rnd->getInt(0, 9)];
		}

		for ($i = 0; $i < 5; $i++) {
			$data['effect'][] = [
				'id'           => 'e'.$i,
				'modifier'     => $rnd->getInt(1, 4),
				'modification' => $pick(['positive', 'negative']),
				'cumulative'   => $pick([true, false, null]),
				'abilities'    => [$pick(['a0', 'a1', 'a2', 'ax'])],
			];
		}

		$character = ['id' => 'char-'.$seed];
		foreach (self::KINDS as $index => $kind) {
			$data[$kind] = [];
			for ($i = 0; $i < 3; $i++) {
				$effects = $pick([[], ['e0'], ['e1', 'e2'], ['e3', 'e9'], null, ['e4', 'e4']]);
				$data[$kind][] = ['id' => $kind[0].$i, 'name' => ucfirst($kind).' '.$i, 'effects' => $effects];
			}

			$character[self::PROPERTIES[$index]] = $pick(
				[
					[$kind[0].'0'],
					[$kind[0].'1', $kind[0].'2', 'missing'],
					[$kind[0].'0', $kind[0].'0'],
					[],
					'not a list',
					null,
				]
			);
		}

		return [$data, $character];
	}//end caseFor()

	/**
	 * A service reading the given world.
	 *
	 * @param array<string,array<int,array<string,mixed>>> $data Objects by type.
	 *
	 * @return CharacterService
	 */
	private function serviceFor(array $data): CharacterService {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			static fn (string $type): array => $data[$type] ?? []
		);

		return new CharacterService(
			objectFetcher: $fetcher,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			effectApplier: new EffectApplier()
		);
	}//end serviceFor()

	/**
	 * Every case's result matches the recorded one.
	 *
	 * @return void
	 */
	public function testMatchesRecordedStats(): void {
		$actual = [];
		for ($seed = 1; $seed <= 160; $seed++) {
			[$data, $character] = $this->caseFor(seed: $seed);
			$actual['case '.$seed] = $this->serviceFor(data: $data)->calculateCharacter(character: $character);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $result) {
			self::assertSame(expected: json_encode($result), actual: json_encode($actual[$name]), message: $name);
		}
	}//end testMatchesRecordedStats()
}//end class
