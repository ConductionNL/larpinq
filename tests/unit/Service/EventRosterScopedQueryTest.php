<?php

/**
 * The run-sheet and roster read one event's cast, not the whole register
 * (larpinq-runsheet-scoped-cast-query).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/larpinq-runsheet-scoped-cast-query/tasks.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The cast read carries the event filter, players are read by id, and the
 * membership check still drops a character a filter let through.
 */
class EventRosterScopedQueryTest extends TestCase {

	private const EVENT = '11111111-1111-4111-8111-111111111111';

	private const ALICE = '22222222-2222-4222-8222-222222222222';

	/** @var list<array<int,mixed>> The reads, in order. */
	private array $reads = [];

	/**
	 * A service over a fetcher that answers the character read with these rows, whatever the filter.
	 *
	 * @param list<array<string,mixed>> $characters The rows the character read answers.
	 *
	 * @return EventRosterService
	 */
	private function service(array $characters): EventRosterService {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $objectType, ?int $limit = null, ?int $offset = null, ?array $filters = []) use ($characters): array {
				$this->reads[] = ['getObjects', $objectType, $limit, $filters];
				if ($objectType === 'character') {
					return $characters;
				}

				return [];
			}
		);
		$fetcher->method('getObjectOfType')->willReturnCallback(
			function (string $objectType, string $id): array {
				$this->reads[] = ['getObjectOfType', $objectType, $id];
				if ($objectType === 'player' && $id === self::ALICE) {
					return ['id' => self::ALICE, 'name' => 'Alice de Vries'];
				}

				throw new RuntimeException(message: 'not found');
			}
		);

		return new EventRosterService(objectFetcher: $fetcher);
	}//end service()

	/**
	 * The character read is scoped to the event and no player register is read whole.
	 *
	 * @return void
	 */
	public function testTheCastReadIsScopedToTheEvent(): void {
		$this->service(characters: [['id' => 'c1', 'name' => 'Lancelot', 'events' => [self::EVENT], 'ocName' => self::ALICE]])
			->buildRunsheetContext(event: ['name' => 'Winter Court'], eventId: self::EVENT);

		$this->assertContains(needle: ['getObjects', 'character', null, ['events' => self::EVENT]], haystack: $this->reads);
		$this->assertNotContains(needle: ['getObjects', 'character', null, []], haystack: $this->reads, message: 'no unfiltered character read');
		$playerReads = array_values(array_filter($this->reads, static fn (array $read): bool => $read[1] === 'player'));
		$this->assertSame(expected: [['getObjectOfType', 'player', self::ALICE]], actual: $playerReads);
	}//end testTheCastReadIsScopedToTheEvent()

	/**
	 * A character the filter let through but not linked to the event stays out of the cast and its player is not read.
	 *
	 * @return void
	 */
	public function testACharacterOfAnotherEventStaysOutOfTheCast(): void {
		$context = $this->service(characters: [
			['id' => 'c1', 'name' => 'Lancelot', 'events' => [self::EVENT], 'ocName' => self::ALICE],
			['id' => 'c2', 'name' => 'Stranger', 'events' => ['33333333-3333-4333-8333-333333333333'], 'ocName' => 'Bob'],
			['id' => 'c3', 'name' => 'Nobody', 'events' => 'not a list'],
		])->buildRunsheetContext(event: ['name' => 'Winter Court'], eventId: self::EVENT);

		$this->assertSame(expected: 1, actual: $context['castCount']);
		$this->assertSame(expected: 'Lancelot', actual: $context['cast'][0]['name']);
		$this->assertSame(expected: 'Alice de Vries', actual: $context['cast'][0]['playerName']);
		$this->assertNotContains(needle: ['getObjectOfType', 'player', 'Bob'], haystack: $this->reads);
	}//end testACharacterOfAnotherEventStaysOutOfTheCast()

	/**
	 * A player reference that cannot be read keeps the stored text as the player name; the roster reads the same scoped cast.
	 *
	 * @return void
	 */
	public function testAnUnreadablePlayerKeepsTheStoredName(): void {
		$roster = $this->service(characters: [['id' => 'c1', 'name' => 'Mira', 'events' => [self::EVENT], 'ocName' => 'Bob the player']])
			->buildRoster(eventId: self::EVENT);

		$this->assertSame(expected: 'Bob the player', actual: $roster['participants'][0]['playerName']);
		$this->assertContains(needle: ['getObjects', 'character', null, ['events' => self::EVENT]], haystack: $this->reads);
	}//end testAnUnreadablePlayerKeepsTheStoredName()
}//end class
