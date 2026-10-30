<?php

/**
 * BackfillCharacterStatusTest.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Repair
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Repair;

use OCA\Larpinq\Repair\BackfillCharacterStatus;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Characters stored before the status existed become active, so the event
 * participant picker (status = active) keeps offering them.
 */
class BackfillCharacterStatusTest extends TestCase {

	/**
	 * Only characters without a status are written, each once, with status
	 * active and the rest of the character unchanged; pages are read to the end.
	 *
	 * @return void
	 */
	public function testCharactersWithoutAStatusBecomeActive(): void {
		$stored = [];
		for ($i = 0; $i < 201; $i++) {
			$stored[] = ['id' => "c{$i}", 'name' => "Character {$i}", '@self' => ['id' => "c{$i}"]];
		}

		$stored[5]['status'] = 'dead';
		$stored[200]['status'] = 'retired';

		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			static fn (string $objectType, ?int $limit = null, ?int $offset = null): array => array_slice($stored, (int)$offset, (int)$limit)
		);
		$saved = [];
		$fetcher->method('saveObject')->willReturnCallback(
			static function (string $objectType, array $data, ?string $uuid = null) use (&$saved): array {
				$saved[$uuid] = $data;
				return $data;
			}
		);

		(new BackfillCharacterStatus($fetcher, $this->createMock(LoggerInterface::class)))->run($this->createMock(IOutput::class));

		$this->assertCount(199, $saved);
		$this->assertArrayNotHasKey('c5', $saved);
		$this->assertArrayNotHasKey('c200', $saved);
		$this->assertSame(['id' => 'c7', 'name' => 'Character 7', 'status' => 'active'], $saved['c7']);
	}//end testCharactersWithoutAStatusBecomeActive()

	/**
	 * A failed save is logged and the step goes on; a failed read writes nothing.
	 *
	 * @return void
	 */
	public function testFailuresNeverStopTheUpgrade(): void {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturn([['id' => 'a'], ['id' => 'b']]);
		$fetcher->expects($this->exactly(2))->method('saveObject')->willThrowException(new \RuntimeException('locked'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->exactly(2))->method('warning');
		(new BackfillCharacterStatus($fetcher, $logger))->run($this->createMock(IOutput::class));

		$broken = $this->createMock(RegisterObjectFetcher::class);
		$broken->method('getObjects')->willThrowException(new \RuntimeException('no register'));
		$broken->expects($this->never())->method('saveObject');
		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('warning');
		(new BackfillCharacterStatus($broken, $this->createMock(LoggerInterface::class)))->run($output);
	}//end testFailuresNeverStopTheUpgrade()
}//end class
