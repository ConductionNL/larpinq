<?php

/**
 * Unit tests for UniqueHolderService.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\UniqueHolderConflictFinder;
use OCA\Larpinq\Service\UniqueHolderService;
use PHPUnit\Framework\TestCase;

/**
 * Tests the bounded holder lookup and the conflict listing.
 */
class UniqueHolderServiceTest extends TestCase {

	private const CROWN = '11111111-1111-4111-8111-111111111111';

	private const QUEEN = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

	private const BERTRAM = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

	/**
	 * A service over a fetcher whose getObjects answers through $objects.
	 *
	 * @param callable $objects Receives the getObjects arguments, returns rows.
	 * @param array<string,array<string,mixed>> $byId Rows getObject returns by id.
	 * @param array<int,array<string,mixed>> $calls Receives every getObjects call.
	 *
	 * @return UniqueHolderService The service.
	 */
	private function service(callable $objects, array $byId = [], array &$calls = []): UniqueHolderService {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $objectType, ?int $limit = null, ?int $offset = null, ?array $filters = []) use ($objects, &$calls): array {
				$calls[] = ['type' => $objectType, 'limit' => $limit, 'offset' => $offset, 'filters' => $filters];
				return $objects($objectType, $limit, $offset, $filters ?? []);
			}
		);
		$fetcher->method('getObject')->willReturnCallback(
			function (string $objectType, string $id) use ($byId): array {
				if (isset($byId[$id]) === false) {
					throw new \Exception('not found');
				}

				return $byId[$id];
			}
		);

		return new UniqueHolderService($fetcher, new IdListNormaliser());
	}//end service()

	/**
	 * The filtered lookup asks for a bounded page on the character field.
	 *
	 * @return void
	 */
	public function testFilteredLookupIsBoundedAndNamesTheHolder(): void {
		$calls = [];
		$service = $this->service(
			objects: fn (): array => [['id' => self::QUEEN, 'name' => 'Queen Isolde', 'items' => [self::CROWN]]],
			calls: $calls
		);

		$holders = $service->otherHolders(kind: 'item', id: self::CROWN, exceptCharacter: self::BERTRAM, max: 1);

		self::assertSame([['id' => self::QUEEN, 'name' => 'Queen Isolde']], $holders);
		self::assertCount(1, $calls);
		self::assertSame(['items' => self::CROWN], $calls[0]['filters']);
		self::assertSame(2, $calls[0]['limit']);
	}//end testFilteredLookupIsBoundedAndNamesTheHolder()

	/**
	 * The character being written is never its own other holder.
	 *
	 * @return void
	 */
	public function testTheWrittenCharacterIsNotCounted(): void {
		$service = $this->service(
			objects: fn (): array => [['id' => self::QUEEN, 'name' => 'Queen Isolde', 'items' => [self::CROWN]]]
		);

		self::assertSame([], $service->otherHolders(kind: 'item', id: self::CROWN, objectSide: [self::QUEEN], exceptCharacter: self::QUEEN));
	}//end testTheWrittenCharacterIsNotCounted()

	/**
	 * A condition is looked up on `conditions`, and the object side is read by id.
	 *
	 * @return void
	 */
	public function testObjectSideHoldersAreCountedAndNamed(): void {
		$calls = [];
		$service = $this->service(
			objects: fn (): array => [],
			byId: [self::QUEEN => ['id' => self::QUEEN, 'name' => 'Mirela']],
			calls: $calls
		);

		$holders = $service->otherHolders(kind: 'condition', id: self::CROWN, objectSide: [self::QUEEN], exceptCharacter: self::BERTRAM);

		self::assertSame([['id' => self::QUEEN, 'name' => 'Mirela']], $holders);
		self::assertSame(['conditions' => self::CROWN], $calls[0]['filters']);
	}//end testObjectSideHoldersAreCountedAndNamed()

	/**
	 * When the filter is not applied, the scan pages by 100 and stops at the first holder.
	 *
	 * @return void
	 */
	public function testUnfilteredAnswerFallsBackToABoundedScanThatStopsEarly(): void {
		$calls = [];
		$service = $this->service(
			objects: function (string $type, ?int $limit, ?int $offset, array $filters): array {
				if ($filters !== []) {
					// The filter was ignored: rows that do not hold the crown.
					return [['id' => 'x', 'name' => 'Someone', 'items' => []]];
				}

				if ($offset === 0) {
					$rows = [];
					for ($i = 0; $i < UniqueHolderService::BATCH; $i++) {
						$rows[] = ['id' => 'c' . $i, 'name' => 'Filler ' . $i, 'items' => []];
					}

					return $rows;
				}

				if ($offset === UniqueHolderService::BATCH) {
					$rows = [['id' => self::QUEEN, 'name' => 'Queen Isolde', 'items' => [self::CROWN]]];
					for ($i = 0; $i < UniqueHolderService::BATCH - 1; $i++) {
						$rows[] = ['id' => 'd' . $i, 'name' => 'Filler', 'items' => [self::CROWN]];
					}

					return $rows;
				}

				self::fail('The scan read past the page that held the first holder.');
			},
			calls: $calls
		);

		$holders = $service->otherHolders(kind: 'item', id: self::CROWN, max: 1);

		self::assertSame([['id' => self::QUEEN, 'name' => 'Queen Isolde']], $holders);
		self::assertCount(3, $calls, 'one filtered query and two pages of 100');
		self::assertSame(UniqueHolderService::BATCH, $calls[1]['limit']);
		self::assertSame(UniqueHolderService::BATCH, $calls[2]['offset']);
	}//end testUnfilteredAnswerFallsBackToABoundedScanThatStopsEarly()

	/**
	 * The unique flag falls back to the schema default per kind.
	 *
	 * @return void
	 */
	public function testUniqueFollowsTheSchemaDefault(): void {
		$service = $this->service(objects: fn (): array => []);

		self::assertTrue($service->isUnique(kind: 'item', object: []));
		self::assertFalse($service->isUnique(kind: 'condition', object: []));
		self::assertFalse($service->isUnique(kind: 'item', object: ['unique' => false]));
		self::assertTrue($service->isUnique(kind: 'condition', object: ['unique' => true]));
	}//end testUniqueFollowsTheSchemaDefault()

	/**
	 * The conflict listing finds shared unique objects on both sides and skips the rest.
	 *
	 * @return void
	 */
	public function testFindConflictsListsSharedUniqueObjects(): void {
		$service = $this->service(
			objects: function (string $type, ?int $limit, ?int $offset): array {
				if ($offset !== 0) {
					return [];
				}

				return match ($type) {
					'character' => [
						['id' => self::QUEEN, 'name' => 'Queen Isolde', 'items' => [self::CROWN, 'potion'], 'conditions' => ['curse']],
						['id' => self::BERTRAM, 'name' => 'Sir Bertram', 'items' => [self::CROWN, 'potion']],
					],
					'item' => [
						['id' => self::CROWN, 'name' => 'Crown of Aldmoor', 'unique' => true],
						['id' => 'potion', 'name' => 'Healing potion', 'unique' => false],
					],
					'condition' => [
						['id' => 'curse', 'name' => 'Curse of the Ashen King', 'unique' => true, 'characters' => ['tomas']],
						['id' => 'cold', 'name' => 'A cold', 'characters' => ['tomas', self::QUEEN]],
					],
					default => [],
				};
			}
		);

		$conflicts = (new UniqueHolderConflictFinder($service, new IdListNormaliser()))->findConflicts();

		self::assertSame(
			[
				['kind' => 'item', 'id' => self::CROWN, 'name' => 'Crown of Aldmoor', 'holders' => ['Queen Isolde', 'Sir Bertram']],
				['kind' => 'condition', 'id' => 'curse', 'name' => 'Curse of the Ashen King', 'holders' => ['Queen Isolde', 'tomas']],
			],
			$conflicts
		);
	}//end testFindConflictsListsSharedUniqueObjects()
}//end class
