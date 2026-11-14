<?php

/**
 * PreWriteEvent reads the entities of a pre-write event, refuses on errors and flattens an entity.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\PreWriteEvent;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use PHPUnit\Framework\TestCase;

/**
 * An entity with a uuid and a payload.
 */
class PreWriteEntity extends ObjectEntity {
	/**
	 * Build it.
	 *
	 * @param string|null         $uuid The uuid.
	 * @param array<string,mixed> $data The payload.
	 */
	public function __construct(?string $uuid, array $data) {
		$this->uuid = $uuid;
		$this->object = $data;
	}
}

/**
 * @covers \OCA\Larpinq\Listener\PreWriteEvent
 */
class PreWriteEventTest extends TestCase {

	/**
	 * A create event yields its object and no previous version.
	 *
	 * @return void
	 */
	public function testACreateHasNoPreviousVersion(): void {
		$entity = new PreWriteEntity('u-1', ['name' => 'Aria']);

		$this->assertSame(expected: [$entity, null], actual: PreWriteEvent::entitiesOf(event: new ObjectCreatingEvent($entity)));
	}

	/**
	 * An update event yields the new object first and the old one second.
	 *
	 * @return void
	 */
	public function testAnUpdateYieldsNewThenOld(): void {
		$new = new PreWriteEntity('u-1', ['name' => 'New']);
		$old = new PreWriteEntity('u-1', ['name' => 'Old']);

		$this->assertSame(expected: [$new, $old], actual: PreWriteEvent::entitiesOf(event: new ObjectUpdatingEvent($new, $old)));
		$this->assertSame(expected: [$new, null], actual: PreWriteEvent::entitiesOf(event: new ObjectUpdatingEvent($new)));
	}

	/**
	 * Errors stop the event and are handed to it, verbatim.
	 *
	 * @return void
	 */
	public function testErrorsRefuseTheWrite(): void {
		$event = new ObjectCreatingEvent(new PreWriteEntity('u-1', []));

		PreWriteEvent::refuseOnErrors(event: $event, errors: ['name' => 'Required.']);

		$this->assertTrue(condition: $event->isPropagationStopped());
		$this->assertSame(expected: ['name' => 'Required.'], actual: $event->getErrors());
	}

	/**
	 * No errors (null) leaves the event running; an empty list still refuses.
	 *
	 * @return void
	 */
	public function testNullLetsTheWriteThroughButAnEmptyListDoesNot(): void {
		$event = new ObjectCreatingEvent(new PreWriteEntity('u-1', []));
		PreWriteEvent::refuseOnErrors(event: $event, errors: null);
		$this->assertFalse(condition: $event->isPropagationStopped());
		$this->assertSame(expected: [], actual: $event->getErrors());

		$empty = new ObjectCreatingEvent(new PreWriteEntity('u-1', []));
		PreWriteEvent::refuseOnErrors(event: $empty, errors: []);
		$this->assertTrue(condition: $empty->isPropagationStopped());
	}

	/**
	 * The uuid becomes the id, overriding any id in the payload.
	 *
	 * @return void
	 */
	public function testTheUuidIsTheId(): void {
		$data = PreWriteEvent::dataOf(entity: new PreWriteEntity('u-9', ['id' => 'stale', 'name' => 'Aria']));

		$this->assertSame(expected: 'u-9', actual: $data['id']);
		$this->assertSame(expected: 'Aria', actual: $data['name']);
	}

	/**
	 * Without a uuid the payload's own id is kept.
	 *
	 * @return void
	 */
	public function testWithoutAUuidThePayloadIdStays(): void {
		$entity = new class {
			/**
			 * The payload.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return ['id' => 'own', 'name' => 'Bo'];
			}

			/**
			 * An empty uuid.
			 *
			 * @return string
			 */
			public function getUuid(): string {
				return '';
			}
		};

		$this->assertSame(expected: ['id' => 'own', 'name' => 'Bo'], actual: PreWriteEvent::dataOf(entity: $entity));
	}

	/**
	 * An entity without getUuid() is flattened as is.
	 *
	 * @return void
	 */
	public function testAnEntityWithoutUuidAccessorIsFlattenedAsIs(): void {
		$entity = new class {
			/**
			 * The payload.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return ['name' => 'Cy'];
			}
		};

		$this->assertSame(expected: ['name' => 'Cy'], actual: PreWriteEvent::dataOf(entity: $entity));
	}
}
