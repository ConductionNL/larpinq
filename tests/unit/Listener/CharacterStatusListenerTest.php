<?php

/**
 * CharacterStatusListenerTest: the real OpenRegister event classes, the real
 * guard (characters-status-and-bulk-edit REQ-CSB-002).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\CharacterStatusListener;
use OCA\Larpinq\Service\CharacterStatusGuard;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * The real OpenRegister entity, with a schema id and a payload.
 */
class StatusObjectEntity extends ObjectEntity {
	/**
	 * Build an entity for a schema id and payload.
	 *
	 * @param string $schema The schema id.
	 * @param array<string,mixed> $data The object payload.
	 */
	public function __construct(string $schema, array $data) {
		$this->schema = $schema;
		$this->object = $data;
	}
}

/**
 * Retired and dead characters join no new events.
 */
class CharacterStatusListenerTest extends TestCase {

	private const SCHEMA_ID = 'char-schema-uuid';

	/**
	 * The listener over the real guard.
	 *
	 * @return CharacterStatusListener The listener.
	 */
	private function listener(): CharacterStatusListener {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturn(self::SCHEMA_ID);

		return new CharacterStatusListener(new CharacterStatusGuard(new IdListNormaliser()), $config);
	}//end listener()

	/**
	 * An update event on a character.
	 *
	 * @param array<string, mixed> $before The stored character.
	 * @param array<string, mixed> $after The character as it will be saved.
	 * @param string $schema The schema id.
	 *
	 * @return ObjectUpdatingEvent The event.
	 */
	private function update(array $before, array $after, string $schema = self::SCHEMA_ID): ObjectUpdatingEvent {
		return new ObjectUpdatingEvent(new StatusObjectEntity($schema, $after), new StatusObjectEntity($schema, $before));
	}//end update()

	/**
	 * Scenario "The API refuses a dead character": adding an event to a dead
	 * or retired character is refused on `events`, also on a create.
	 *
	 * @return void
	 */
	public function testANotActiveCharacterJoinsNoNewEvent(): void {
		foreach (['dead', 'retired'] as $status) {
			$event = $this->update(
				before: ['name' => 'Brother Aldric', 'status' => $status, 'events' => ['ev-summer']],
				after: ['name' => 'Brother Aldric', 'status' => $status, 'events' => ['ev-summer', 'ev-winter']]
			);
			$this->listener()->handle($event);

			$this->assertTrue($event->isPropagationStopped(), "a {$status} character is refused");
			$this->assertSame('character_not_active', $event->getErrors()['code']);
			$this->assertArrayHasKey('events', $event->getErrors()['fields']);
		}

		$create = new ObjectCreatingEvent(new StatusObjectEntity(self::SCHEMA_ID, ['status' => 'dead', 'events' => ['ev-winter']]));
		$this->listener()->handle($create);
		$this->assertTrue($create->isPropagationStopped());
	}//end testANotActiveCharacterJoinsNoNewEvent()

	/**
	 * An active character, or one without a status, joins events; a dead
	 * character may leave one, other edits of it pass, and other schemas are
	 * not looked at.
	 *
	 * @return void
	 */
	public function testEverythingElsePasses(): void {
		$cases = [
			'active joins' => [['status' => 'active', 'events' => []], ['status' => 'active', 'events' => ['ev-winter']], self::SCHEMA_ID],
			'no status joins' => [['events' => []], ['events' => ['ev-winter']], self::SCHEMA_ID],
			'dead leaves' => [['status' => 'dead', 'events' => ['ev-summer']], ['status' => 'dead', 'events' => []], self::SCHEMA_ID],
			'dead renamed' => [['status' => 'dead', 'name' => 'Aldric', 'events' => ['ev-summer']], ['status' => 'dead', 'name' => 'Brother Aldric', 'events' => ['ev-summer']], self::SCHEMA_ID],
			'other schema' => [['status' => 'dead', 'events' => []], ['status' => 'dead', 'events' => ['ev-winter']], 'other-schema'],
		];
		foreach ($cases as $label => [$before, $after, $schema]) {
			$event = $this->update(before: $before, after: $after, schema: $schema);
			$this->listener()->handle($event);
			$this->assertFalse($event->isPropagationStopped(), $label);
		}
	}//end testEverythingElsePasses()
}//end class
