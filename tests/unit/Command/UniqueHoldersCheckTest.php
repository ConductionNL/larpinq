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
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			fn (string $type, ?int $limit = null, ?int $offset = null): array => ($offset ?? 0) > 0 ? [] : match ($type) {
				'character' => $characters,
				'item' => $items,
				default => [],
			}
		);

		return new CommandTester(new UniqueHoldersCheck(new UniqueHolderConflictFinder(new UniqueHolderService($fetcher, new IdListNormaliser()), new IdListNormaliser())));
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

		self::assertSame(1, $code);
		self::assertStringContainsString('item "Crown of Aldmoor" (crown) is held by: Queen Isolde, Sir Bertram', $tester->getDisplay());
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

		self::assertSame(0, $tester->execute([]));
		self::assertStringContainsString('No unique item or condition has more than one holder.', $tester->getDisplay());
	}//end testNoConflictExitsZero()
}//end class
