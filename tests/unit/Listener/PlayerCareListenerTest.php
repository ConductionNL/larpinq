<?php

/**
 * Tests for PlayerCareListener: changed care details refresh the event copies
 * (players-care-details-and-erasure REQ-PCE-002).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/players-care-details-and-erasure/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/InMemoryOpenRegister.php';

use OCA\Larpinq\Listener\PlayerCareListener;
use OCA\Larpinq\Service\EventCareRecords;
use OCA\Larpinq\Tests\Unit\Support\InMemoryObjectEntity;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * The player schema is "player"; the copies are a double.
 *
 * @covers \OCA\Larpinq\Listener\PlayerCareListener
 * @uses \OCA\Larpinq\Service\EventCareRecords
 */
class PlayerCareListenerTest extends TestCase {

	/**
	 * The listener over a refresh double that expects the given calls.
	 *
	 * @param array<int, string> $refreshed The player ids expected to be refreshed.
	 *
	 * @return PlayerCareListener
	 */
	private function listener(array $refreshed): PlayerCareListener {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($key === 'player_schema') ? 'player' : $default
		);
		$care = $this->createMock(EventCareRecords::class);
		$care->expects($this->exactly(count($refreshed)))->method('refreshForPlayer')->willReturnCallback(
			function (string $playerId) use (&$refreshed): int {
				$this->assertSame(array_shift($refreshed), $playerId);
				return 1;
			}
		);

		return new PlayerCareListener($config, $care);
	}//end listener()

	/**
	 * An update of a player.
	 *
	 * @param array<string, mixed> $before The stored player.
	 * @param array<string, mixed> $after The updated player.
	 * @param string $schema The schema.
	 *
	 * @return ObjectUpdatedEvent
	 */
	private function updated(array $before, array $after, string $schema = 'player'): ObjectUpdatedEvent {
		return new ObjectUpdatedEvent(new InMemoryObjectEntity(schema: $schema, data: $after, uuid: 'p-anna'), new InMemoryObjectEntity(schema: $schema, data: $before, uuid: 'p-anna'));
	}//end updated()

	/**
	 * A changed allergy refreshes Anna's copies.
	 *
	 * @return void
	 */
	public function testAChangedCareDetailRefreshesTheCopies(): void {
		$this->listener(refreshed: ['p-anna'])->handle($this->updated(before: ['name' => 'Anna', 'allergies' => 'hazelnuts'], after: ['name' => 'Anna', 'allergies' => 'hazelnuts, peanuts']));
	}//end testAChangedCareDetailRefreshesTheCopies()

	/**
	 * Other changes, other schemas and other events refresh nothing.
	 *
	 * @return void
	 */
	public function testOtherChangesRefreshNothing(): void {
		$listener = $this->listener(refreshed: []);
		$listener->handle($this->updated(before: ['name' => 'Anna', 'allergies' => 'hazelnuts'], after: ['name' => 'Anna de Vries', 'allergies' => 'hazelnuts']));
		$listener->handle($this->updated(before: ['allergies' => 'a'], after: ['allergies' => 'b'], schema: 'character'));
		$listener->handle(new ObjectUpdatingEvent(new InMemoryObjectEntity(schema: 'player', data: ['allergies' => 'b']), new InMemoryObjectEntity(schema: 'player', data: ['allergies' => 'a'])));
	}//end testOtherChangesRefreshNothing()

	/**
	 * Every care field counts, including one that is added or removed.
	 *
	 * @return void
	 */
	public function testEveryCareFieldCounts(): void {
		foreach (EventCareRecords::FIELDS as $field) {
			$this->assertTrue(PlayerCareListener::careChanged(before: [], after: [$field => 'x']), $field);
			$this->assertTrue(PlayerCareListener::careChanged(before: [$field => 'x'], after: []), $field);
		}

		$this->assertFalse(PlayerCareListener::careChanged(before: ['name' => 'a', 'allergies' => 'x'], after: ['name' => 'b', 'allergies' => 'x']));
	}//end testEveryCareFieldCounts()
}//end class
