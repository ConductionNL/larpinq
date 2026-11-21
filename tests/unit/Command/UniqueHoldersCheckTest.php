<?php

/**
 * Unit tests for the unique-holders check command.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Command
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

namespace OCA\Larpinq\Tests\Unit\Command;

use OCA\Larpinq\Command\UniqueHoldersCheck;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\UniqueHolderConflictFinder;
use OCA\Larpinq\Service\UniqueHolderService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * REQ-UHE-005: an administrator finds an old conflict.
 *
 * @covers \OCA\Larpinq\Command\UniqueHoldersCheck
 * @uses   \OCA\Larpinq\Service\UniqueHolderConflictFinder
 * @uses   \OCA\Larpinq\Service\UniqueHolderService
 * @uses   \OCA\Larpinq\Service\IdListNormaliser
 */
class UniqueHoldersCheckTest extends TestCase {

	/**
	 * A command over a register with the given characters and items.
	 *
	 * @param array<int,array<string,mixed>> $characters The characters.
	 * @param array<int,array<string,mixed>> $items The items.
	 *
	 * @return CommandTester The tester.
	 */
	private function tester(array $characters, array $items): CommandTester {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			fn (string $type, ?int $limit = null, ?int $offset = null): array => match (true) {
				($offset ?? 0) > 0 => [],
				$type === 'character' => $characters,
				$type === 'item' => $items,
				default => [],
			}
		);

		return new CommandTester(
			command: new UniqueHoldersCheck(
				finder: new UniqueHolderConflictFinder(
					holders: new UniqueHolderService(objectFetcher: $fetcher, idNormaliser: new IdListNormaliser()),
					idNormaliser: new IdListNormaliser()
				)
			)
		);
	}//end tester()

	/**
	 * The shared crown is listed with both holders and the exit code is non-zero.
	 *
	 * @return void
	 */
	public function testAConflictIsListedAndExitsNonZero(): void {
		$tester = $this->tester(
			characters: [
				['id' => 'q', 'name' => 'Queen Isolde', 'items' => ['crown']],
				['id' => 'b', 'name' => 'Sir Bertram', 'items' => ['crown']],
			],
			items: [['id' => 'crown', 'name' => 'Crown of Aldmoor', 'unique' => true]]
		);

		$code = $tester->execute([]);

		self::assertSame(expected: 1, actual: $code);
		self::assertStringContainsString(needle: 'item "Crown of Aldmoor" (crown) is held by: Queen Isolde, Sir Bertram', haystack: $tester->getDisplay());
	}//end testAConflictIsListedAndExitsNonZero()

	/**
	 * Without a conflict the command says so and exits zero.
	 *
	 * @return void
	 */
	public function testNoConflictExitsZero(): void {
		$tester = $this->tester(
			characters: [['id' => 'q', 'name' => 'Queen Isolde', 'items' => ['crown']]],
			items: [['id' => 'crown', 'name' => 'Crown of Aldmoor', 'unique' => true]]
		);

		self::assertSame(expected: 0, actual: $tester->execute([]));
		self::assertStringContainsString(needle: 'No unique item or condition has more than one holder.', haystack: $tester->getDisplay());
	}//end testNoConflictExitsZero()

	/**
	 * The command registers under its occ name with a description.
	 *
	 * @return void
	 */
	public function testNameAndDescription(): void {
		$command = new UniqueHoldersCheck(finder: $this->createMock(originalClassName: UniqueHolderConflictFinder::class));

		self::assertSame(expected: 'larpinq:unique-holders:check', actual: $command->getName());
		self::assertSame(
			expected: 'List unique items and conditions that more than one character holds',
			actual: $command->getDescription()
		);
	}//end testNameAndDescription()

	/**
	 * Every conflict gets its own line in finder order, followed by the conflict count.
	 *
	 * @return void
	 */
	public function testEveryConflictIsListedWithTheCount(): void {
		$finder = $this->createMock(originalClassName: UniqueHolderConflictFinder::class);
		$finder->expects($this->once())->method('findConflicts')->willReturn(
			[
				['kind' => 'item', 'id' => 'crown', 'name' => 'Crown', 'holders' => ['Isolde', 'Bertram']],
				['kind' => 'condition', 'id' => 'curse', 'name' => 'Old Curse', 'holders' => ['Mara', 'Tobin', 'Wren']],
			]
		);
		$tester = new CommandTester(command: new UniqueHoldersCheck(finder: $finder));

		$code = $tester->execute([]);

		self::assertSame(expected: 1, actual: $code);
		self::assertSame(
			expected: 'item "Crown" (crown) is held by: Isolde, Bertram' . PHP_EOL
				. 'condition "Old Curse" (curse) is held by: Mara, Tobin, Wren' . PHP_EOL
				. '2 conflict(s). Remove all but one holder from each.' . PHP_EOL,
			actual: $tester->getDisplay()
		);
	}//end testEveryConflictIsListedWithTheCount()

	/**
	 * Without a conflict only the all-clear line is written.
	 *
	 * @return void
	 */
	public function testNoConflictWritesOnlyTheAllClearLine(): void {
		$finder = $this->createMock(originalClassName: UniqueHolderConflictFinder::class);
		$finder->method('findConflicts')->willReturn([]);
		$tester = new CommandTester(command: new UniqueHoldersCheck(finder: $finder));

		self::assertSame(expected: 0, actual: $tester->execute([]));
		self::assertSame(
			expected: 'No unique item or condition has more than one holder.' . PHP_EOL,
			actual: $tester->getDisplay()
		);
	}//end testNoConflictWritesOnlyTheAllClearLine()
}//end class
