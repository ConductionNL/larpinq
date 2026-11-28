<?php

/**
 * Golden test for the unique-holder conflict listing.
 *
 * Every register below runs through UniqueHolderConflictFinder::findConflicts()
 * over the real UniqueHolderService and a fake fetcher. The conflicts and every
 * page read are compared per case, as JSON text so key order and scalar types
 * count, with fixtures/unique-holder-conflicts.golden.json. Set RECORD_GOLDEN=1
 * to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\UniqueHolderConflictFinder;
use OCA\Larpinq\Service\UniqueHolderService;
use PHPUnit\Framework\TestCase;

/**
 * Pins the conflicts UniqueHolderConflictFinder lists and the pages it reads.
 *
 * @covers \OCA\Larpinq\Service\UniqueHolderConflictFinder
 * @uses   \OCA\Larpinq\Service\UniqueHolderService
 * @uses   \OCA\Larpinq\Service\IdListNormaliser
 * @uses   \OCA\Larpinq\Service\CharacterHoldings
 */
class UniqueHolderConflictFinderGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/unique-holder-conflicts.golden.json';

	private const UNIQUENESS_ITEMS = ['i-true', 'i-1', 'i-s1', 'i-strue', 'i-null', 'i-absent', 'i-false', 'i-0', 'i-yes', 'i-s0'];

	private const UNIQUENESS_CONDITIONS = ['c-absent', 'c-null', 'c-true', 'c-strue'];

	/**
	 * Characters enough to need three pages, with the named holders at fixed places.
	 *
	 * @param array<int,mixed> $named Rows placed at their index.
	 *
	 * @return array<int,mixed> The characters.
	 */
	private function paged(array $named): array {
		$rows = [];
		for ($i = 0; $i < 230; $i++) {
			$rows[] = ['id' => sprintf('filler-%03d', $i), 'name' => 'Filler ' . $i, 'items' => ['potion']];
		}

		return array_replace($rows, $named);
	}//end paged()

	/**
	 * The registers: character, item and condition rows per case.
	 *
	 * @return array<string,array{character:array<int,mixed>,item:array<int,mixed>,condition:array<int,mixed>}>
	 */
	private function registers(): array {
		return [
			'both sides count' => [
				'character' => [
					['id' => 'queen', 'name' => 'Queen Isolde', 'items' => ['crown', 'potion'], 'conditions' => ['curse']],
					['id' => 'bertram', 'name' => 'Sir Bertram', 'items' => ['crown', 'potion']],
				],
				'item' => [
					['id' => 'crown', 'name' => 'Crown of Aldmoor', 'unique' => true],
					['id' => 'potion', 'name' => 'Healing potion', 'unique' => false],
				],
				'condition' => [
					['id' => 'curse', 'name' => 'Curse', 'unique' => true, 'characters' => ['tomas']],
					['id' => 'cold', 'name' => 'A cold', 'characters' => ['tomas', 'queen']],
				],
			],
			'empty register' => ['character' => [], 'item' => [], 'condition' => []],
			'one holder on both sides is no conflict' => [
				'character' => [['id' => 'queen', 'name' => 'Queen Isolde', 'items' => ['crown'], 'conditions' => ['curse']]],
				'item' => [['id' => 'crown', 'name' => 'Crown', 'characters' => ['queen']]],
				'condition' => [['id' => 'curse', 'name' => 'Curse', 'unique' => true, 'characters' => [['id' => 'queen']]]],
			],
			'object side only, unknown and known characters' => [
				'character' => [['id' => 'tomas', 'name' => 'Tomas']],
				'item' => [
					['id' => 'mark', 'name' => 'Mark', 'unique' => 1, 'characters' => ['ghost', 'tomas', 'ghost', '', null, ['id' => 'ghost2'], ['name' => 'x']]],
					['id' => 'single', 'name' => 'Single', 'characters' => ['ghost']],
				],
				'condition' => [['id' => 'bond', 'name' => '', 'unique' => '1', 'characters' => 'ghost']],
			],
			'uniqueness values and defaults' => [
				'character' => [
					['id' => 'a', 'name' => 'Ann', 'items' => self::UNIQUENESS_ITEMS, 'conditions' => self::UNIQUENESS_CONDITIONS],
					['id' => 'b', 'name' => 'Ben', 'items' => self::UNIQUENESS_ITEMS, 'conditions' => self::UNIQUENESS_CONDITIONS],
				],
				'item' => [
					['id' => 'i-true', 'unique' => true],
					['id' => 'i-1', 'unique' => 1],
					['id' => 'i-s1', 'unique' => '1'],
					['id' => 'i-strue', 'unique' => 'true'],
					['id' => 'i-null', 'unique' => null],
					['id' => 'i-absent'],
					['id' => 'i-false', 'unique' => false],
					['id' => 'i-0', 'unique' => 0],
					['id' => 'i-yes', 'unique' => 'yes'],
					['id' => 'i-s0', 'unique' => '0'],
				],
				'condition' => [
					['id' => 'c-absent'],
					['id' => 'c-null', 'unique' => null],
					['id' => 'c-true', 'unique' => true],
					['id' => 'c-strue', 'unique' => 'TRUE'],
				],
			],
			'ids, names and relation shapes' => [
				'character' => [
					'not a row',
					['name' => 'No id', 'items' => ['crown']],
					['id' => '', 'name' => 'Blank id', 'items' => ['crown']],
					['@self' => ['id' => 'mirela'], 'name' => '  ', 'items' => 'crown', 'conditions' => [['id' => 'curse']]],
					['id' => 7, '@self' => ['id' => 'seven'], 'name' => 'Seven', 'items' => [['id' => 'crown'], ['nope' => 1]]],
					['id' => 'zed', 'name' => 'Zed', 'items' => ['crown'], 'conditions' => 'curse'],
					['id' => 'amy', 'name' => 'amy', 'items' => ['crown', 'gone']],
					['id' => 'dup', 'name' => 'Zed', 'items' => ['crown']],
				],
				'item' => [
					['@self' => ['id' => 'crown'], 'name' => 'Crown', 'characters' => ['zed', 'outsider', 'mirela']],
					['name' => 'No id', 'unique' => true, 'characters' => ['a', 'b']],
					['id' => '', 'unique' => true, 'characters' => ['a', 'b']],
					['id' => 42, 'unique' => true, 'characters' => ['a', 'b']],
				],
				'condition' => [['id' => 'curse', 'name' => ['not', 'a', 'string'], 'unique' => true]],
			],
			'holders across pages' => [
				'character' => $this->paged(
					named: [
						3 => ['id' => 'queen', 'name' => 'Queen Isolde', 'items' => ['ring']],
						120 => ['id' => 'tomas', 'name' => 'Tomas', 'conditions' => ['curse']],
						229 => ['id' => 'last', 'name' => 'Last', 'items' => ['ring'], 'conditions' => ['curse']],
					]
				),
				'item' => array_merge(
					array_map(static fn (int $i): array => ['id' => 'spare-' . $i, 'unique' => true], range(0, 99)),
					[['id' => 'ring', 'name' => 'Ring', 'characters' => ['ghost']], ['id' => 'potion', 'name' => 'Potion', 'unique' => false]]
				),
				'condition' => [['id' => 'curse', 'name' => 'Curse', 'unique' => 'true']],
			],
		];
	}//end registers()

	/**
	 * Run one register through the finder.
	 *
	 * @param array{character:array<int,mixed>,item:array<int,mixed>,condition:array<int,mixed>} $register The rows.
	 *
	 * @return array{conflicts:mixed,reads:array<int,array<string,mixed>>} The conflicts and the page reads.
	 */
	private function scan(array $register): array {
		$reads = [];
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $objectType, ?int $limit = null, ?int $offset = null) use ($register, &$reads): array {
				$reads[] = ['type' => $objectType, 'limit' => $limit, 'offset' => $offset];
				return array_slice($register[$objectType] ?? [], (int)$offset, (int)$limit);
			}
		);

		$finder = new UniqueHolderConflictFinder(
			holders: new UniqueHolderService(objectFetcher: $fetcher, idNormaliser: new IdListNormaliser()),
			idNormaliser: new IdListNormaliser()
		);

		return ['conflicts' => $finder->findConflicts(), 'reads' => $reads];
	}//end scan()

	/**
	 * Every register gives the recorded conflicts and reads.
	 *
	 * @return void
	 */
	public function testConflictsMatchTheGolden(): void {
		$actual = [];
		foreach ($this->registers() as $case => $register) {
			$actual[$case] = json_encode(value: $this->scan(register: $register), flags: JSON_THROW_ON_ERROR);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: $flags) . "\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true, flags: JSON_THROW_ON_ERROR);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($actual as $case => $json) {
			self::assertSame(expected: $expected[$case], actual: $json, message: $case);
		}
	}//end testConflictsMatchTheGolden()
}//end class
