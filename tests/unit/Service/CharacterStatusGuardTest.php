<?php

/**
 * Unit tests for CharacterStatusGuard.
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

use OCA\Larpinq\Service\CharacterStatusGuard;
use OCA\Larpinq\Service\IdListNormaliser;
use PHPUnit\Framework\TestCase;

/**
 * A character that is not active cannot join new events.
 *
 * @covers \OCA\Larpinq\Service\CharacterStatusGuard
 * @uses   \OCA\Larpinq\Service\IdListNormaliser
 */
class CharacterStatusGuardTest extends TestCase {

	/**
	 * The guard.
	 *
	 * @var CharacterStatusGuard
	 */
	private CharacterStatusGuard $guard;

	/**
	 * Set up the subject and its collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->guard = new CharacterStatusGuard(idList: new IdListNormaliser());
	}

	/**
	 * An active character may join any event.
	 *
	 * @return void
	 */
	public function testAnActiveCharacterMayJoinAnyEvent(): void {
		$this->assertNull(actual: $this->guard->check(candidate: ['status' => 'active', 'events' => ['e1', 'e2']], old: ['events' => []]));
	}

	/**
	 * A character without a status may join any event.
	 *
	 * @return void
	 */
	public function testACharacterWithoutAStatusMayJoinAnyEvent(): void {
		$this->assertNull(actual: $this->guard->check(candidate: ['events' => ['e1']], old: []));
		$this->assertNull(actual: $this->guard->check(candidate: ['status' => '', 'events' => ['e1']], old: []));
	}

	/**
	 * An inactive character keeping its events is allowed.
	 *
	 * @return void
	 */
	public function testAnInactiveCharacterKeepingItsEventsIsAllowed(): void {
		$old = ['events' => ['e1', 'e2']];

		$this->assertNull(actual: $this->guard->check(candidate: ['status' => 'dead', 'events' => ['e1', 'e2']], old: $old));
		$this->assertNull(actual: $this->guard->check(candidate: ['status' => 'dead', 'events' => ['e2']], old: $old));
		$this->assertNull(actual: $this->guard->check(candidate: ['status' => 'dead', 'events' => []], old: $old));
		$this->assertNull(actual: $this->guard->check(candidate: ['status' => 'dead'], old: $old));
	}

	/**
	 * An inactive character joining a new event is refused with its status in the message.
	 *
	 * @return void
	 */
	public function testAnInactiveCharacterJoiningANewEventIsRefusedWithItsStatusInTheMessage(): void {
		$message = 'A retired character cannot join new events. Set the status to active first.';

		$this->assertSame(
			expected: ['code' => 'character_not_active', 'message' => $message, 'fields' => ['events' => [$message]]],
			actual: $this->guard->check(candidate: ['status' => 'retired', 'events' => ['e1', 'e3']], old: ['events' => ['e1']])
		);
	}

	/**
	 * Events given as objects are compared by their ids.
	 *
	 * @return void
	 */
	public function testEventsGivenAsObjectsAreComparedByTheirIds(): void {
		$old = ['events' => [['id' => 'e1']]];

		$this->assertNull(actual: $this->guard->check(candidate: ['status' => 'dead', 'events' => ['e1']], old: $old));
		$this->assertNotNull(actual: $this->guard->check(candidate: ['status' => 'dead', 'events' => [['id' => 'e1'], ['id' => 'e2']]], old: $old));
	}

	/**
	 * A single event value counts as one event.
	 *
	 * @return void
	 */
	public function testASingleEventValueCountsAsOneEvent(): void {
		$this->assertNull(actual: $this->guard->check(candidate: ['status' => 'dead', 'events' => 'e1'], old: ['events' => ['e1']]));
		$refusal = $this->guard->check(candidate: ['status' => 'dead', 'events' => 'e2'], old: ['events' => 'e1']);
		$this->assertSame(expected: 'character_not_active', actual: $refusal['code'] ?? null);
	}
}
