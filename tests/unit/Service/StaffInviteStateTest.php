<?php

/**
 * StaffInviteState: the state of a staff invite at a fixed moment.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use DateTime;
use OCA\Larpinq\Service\StaffInviteService;
use OCA\Larpinq\Service\StaffInviteState;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * REQ-ASR-004 invite states, read by the extracted collaborator.
 */
#[CoversClass(StaffInviteState::class)]
class StaffInviteStateTest extends TestCase {

	private const NOW = '2026-11-14T12:00:00+00:00';

	/**
	 * The state reader at NOW.
	 *
	 * @return StaffInviteState
	 */
	private function state(): StaffInviteState {
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getDateTime')->willReturnCallback(static fn (): DateTime => new DateTime(self::NOW));
		return new StaffInviteState(timeFactory: $clock);
	}//end state()

	/**
	 * Revoked wins over expired, expired over used up.
	 *
	 * @return void
	 */
	public function testTheStatesInOrder(): void {
		$state = $this->state();
		$valid = ['expiresAt' => '2026-11-20T00:00:00+00:00', 'maxUses' => 2, 'uses' => [['uid' => 'a']]];

		$this->assertSame(StaffInviteService::STATE_VALID, $state->state(invite: $valid));
		$this->assertSame(StaffInviteService::STATE_USED_UP, $state->state(invite: ['maxUses' => 1] + $valid));
		$this->assertSame(StaffInviteService::STATE_EXPIRED, $state->state(invite: ['expiresAt' => '2026-11-01T00:00:00+00:00', 'maxUses' => 1] + $valid));
		$this->assertSame(StaffInviteService::STATE_REVOKED, $state->state(invite: ['revokedAt' => '2026-11-10T00:00:00+00:00', 'expiresAt' => 'x'] + $valid));
	}//end testTheStatesInOrder()

	/**
	 * An invite without a readable end counts as expired; an empty revokedAt is not a revoke.
	 *
	 * @return void
	 */
	public function testUnreadableEndAndEmptyRevoke(): void {
		$state = $this->state();

		$this->assertTrue($state->isExpired(invite: []));
		$this->assertTrue($state->isExpired(invite: ['expiresAt' => 'not a date']));
		$this->assertFalse($state->isRevoked(invite: ['revokedAt' => '']));
	}//end testUnreadableEndAndEmptyRevoke()

	/**
	 * Uses keeps only array entries and reads a non-array as none.
	 *
	 * @return void
	 */
	public function testUsesAndNow(): void {
		$state = $this->state();

		$this->assertSame([['uid' => 'a']], $state->uses(invite: ['uses' => ['junk', ['uid' => 'a']]]));
		$this->assertSame([], $state->uses(invite: ['uses' => 'junk']));
		$this->assertSame(self::NOW, $state->now()->format(DATE_ATOM));
	}//end testUsesAndNow()
}//end class
