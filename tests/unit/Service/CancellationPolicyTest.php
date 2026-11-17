<?php

/**
 * Unit tests for CancellationPolicy.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Larpinq\Service\CancellationPolicy;
use OCA\Larpinq\Support\AppIdentity;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Who may change a registration, until when, and what a paid cancellation gets.
 *
 * @covers \OCA\Larpinq\Service\CancellationPolicy
 */
class CancellationPolicyTest extends TestCase {

	/** @var IGroupManager&MockObject */
	private IGroupManager $groups;

	/**
	 * The policy.
	 *
	 * @var CancellationPolicy
	 */
	private CancellationPolicy $policy;

	/**
	 * Set up the subject and its collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->groups = $this->createMock(originalClassName: IGroupManager::class);
		$this->policy = new CancellationPolicy(groups: $this->groups);
	}

	/**
	 * An empty uid is never a game master and asks no group.
	 *
	 * @return void
	 */
	public function testAnEmptyUidIsNeverAGameMasterAndAsksNoGroup(): void {
		$this->groups->expects($this->never())->method('isInGroup');
		$this->groups->expects($this->never())->method('isAdmin');

		$this->assertFalse(condition: $this->policy->isGameMaster(uid: ''));
	}

	/**
	 * A member of the game master group is a game master.
	 *
	 * @return void
	 */
	public function testAMemberOfTheGameMasterGroupIsAGameMaster(): void {
		$this->groups->expects($this->once())->method('isInGroup')
			->with('gm1', AppIdentity::GM_GROUP)->willReturn(true);
		$this->groups->expects($this->never())->method('isAdmin');

		$this->assertTrue(condition: $this->policy->isGameMaster(uid: 'gm1'));
	}

	/**
	 * An administrator is a game master.
	 *
	 * @return void
	 */
	public function testAnAdministratorIsAGameMaster(): void {
		$this->groups->method('isInGroup')->willReturn(false);
		$this->groups->expects($this->once())->method('isAdmin')->with('root')->willReturn(true);

		$this->assertTrue(condition: $this->policy->isGameMaster(uid: 'root'));
	}

	/**
	 * Someone in neither group is no game master.
	 *
	 * @return void
	 */
	public function testSomeoneInNeitherGroupIsNoGameMaster(): void {
		$this->groups->method('isInGroup')->willReturn(false);
		$this->groups->method('isAdmin')->willReturn(false);

		$this->assertFalse(condition: $this->policy->isGameMaster(uid: 'player'));
	}

	/**
	 * The participant is the player account.
	 *
	 * @return void
	 */
	public function testTheParticipantIsThePlayerAccount(): void {
		$registration = ['playerUid' => 'alice', 'bookedByUid' => 'bob'];

		$this->assertTrue(condition: $this->policy->isParticipant(registration: $registration, uid: 'alice'));
		$this->assertFalse(condition: $this->policy->isParticipant(registration: $registration, uid: 'bob'));
		$this->assertFalse(condition: $this->policy->isParticipant(registration: $registration, uid: ''));
		$this->assertFalse(condition: $this->policy->isParticipant(registration: [], uid: ''));
	}

	/**
	 * The booker is the account that booked.
	 *
	 * @return void
	 */
	public function testTheBookerIsTheAccountThatBooked(): void {
		$registration = ['playerUid' => 'alice', 'bookedByUid' => 'bob'];

		$this->assertTrue(condition: $this->policy->isBooker(registration: $registration, uid: 'bob'));
		$this->assertFalse(condition: $this->policy->isBooker(registration: $registration, uid: 'alice'));
		$this->assertFalse(condition: $this->policy->isBooker(registration: ['bookedByUid' => ''], uid: ''));
	}

	/**
	 * Cancel by is the policy date first.
	 *
	 * @return void
	 */
	public function testCancelByIsThePolicyDateFirst(): void {
		$event = ['cancellationPolicy' => ['cancelBy' => '2026-05-01T12:00:00+00:00'], 'startDate' => '2026-06-01T10:00:00+00:00'];

		$this->assertSame(expected: '2026-05-01T12:00:00+00:00', actual: $this->policy->cancelBy(event: $event)?->format(DATE_ATOM));
	}

	/**
	 * Cancel by falls back to the start of the event.
	 *
	 * @return void
	 */
	public function testCancelByFallsBackToTheStartOfTheEvent(): void {
		$event = ['cancellationPolicy' => ['cancelBy' => ''], 'startDate' => '2026-06-01T10:00:00+00:00'];

		$this->assertSame(expected: '2026-06-01T10:00:00+00:00', actual: $this->policy->cancelBy(event: $event)?->format(DATE_ATOM));
	}

	/**
	 * An unreadable policy date falls back to the start.
	 *
	 * @return void
	 */
	public function testAnUnreadablePolicyDateFallsBackToTheStart(): void {
		$event = ['cancellationPolicy' => ['cancelBy' => 'not a date at all'], 'startDate' => '2026-06-01T10:00:00+00:00'];

		$this->assertSame(expected: '2026-06-01T10:00:00+00:00', actual: $this->policy->cancelBy(event: $event)?->format(DATE_ATOM));
	}

	/**
	 * Without any date there is no limit.
	 *
	 * @return void
	 */
	public function testWithoutAnyDateThereIsNoLimit(): void {
		$this->assertNull(actual: $this->policy->cancelBy(event: []));
		$this->assertNull(actual: $this->policy->cancelBy(event: ['startDate' => 42]));
		$this->assertTrue(condition: $this->policy->isOpen(event: [], now: new DateTimeImmutable(datetime: '2099-01-01T00:00:00+00:00')));
	}

	/**
	 * It is open until the cancel by moment and closed from it.
	 *
	 * @return void
	 */
	public function testItIsOpenUntilTheCancelByMomentAndClosedFromIt(): void {
		$event = ['startDate' => '2026-06-01T10:00:00+00:00'];

		$this->assertTrue(condition: $this->policy->isOpen(event: $event, now: new DateTimeImmutable(datetime: '2026-06-01T09:59:59+00:00')));
		$this->assertFalse(condition: $this->policy->isOpen(event: $event, now: new DateTimeImmutable(datetime: '2026-06-01T10:00:00+00:00')));
		$this->assertFalse(condition: $this->policy->isOpen(event: $event, now: new DateTimeImmutable(datetime: '2026-06-02T00:00:00+00:00')));
	}

	/**
	 * The player chooses only under the player chooses policy.
	 *
	 * @return void
	 */
	public function testThePlayerChoosesOnlyUnderThePlayerChoosesPolicy(): void {
		$this->assertTrue(condition: $this->policy->choosesMoneyBack(event: ['cancellationPolicy' => ['paidCancellation' => 'player-chooses']]));
		$this->assertFalse(condition: $this->policy->choosesMoneyBack(event: ['cancellationPolicy' => ['paidCancellation' => 'credit']]));
		$this->assertFalse(condition: $this->policy->choosesMoneyBack(event: []));
	}

	/**
	 * A fixed policy ignores the players choice.
	 *
	 * @return void
	 */
	public function testAFixedPolicyIgnoresThePlayersChoice(): void {
		foreach (['refund', 'credit', 'none'] as $fixed) {
			$event = ['cancellationPolicy' => ['paidCancellation' => $fixed]];
			$this->assertSame(expected: $fixed, actual: $this->policy->paidOutcome(event: $event, choice: 'credit'));
			$this->assertSame(expected: $fixed, actual: $this->policy->paidOutcome(event: $event, choice: 'refund'));
		}
	}

	/**
	 * An unknown or missing policy is a refund.
	 *
	 * @return void
	 */
	public function testAnUnknownOrMissingPolicyIsARefund(): void {
		$this->assertSame(expected: 'refund', actual: $this->policy->paidOutcome(event: [], choice: 'credit'));
		$bogus = ['cancellationPolicy' => ['paidCancellation' => 'bogus']];
		$this->assertSame(expected: 'refund', actual: $this->policy->paidOutcome(event: $bogus, choice: 'credit'));
	}

	/**
	 * Under player chooses credit is kept and anything else is a refund.
	 *
	 * @return void
	 */
	public function testUnderPlayerChoosesCreditIsKeptAndAnythingElseIsARefund(): void {
		$event = ['cancellationPolicy' => ['paidCancellation' => 'player-chooses']];

		$this->assertSame(expected: 'credit', actual: $this->policy->paidOutcome(event: $event, choice: 'credit'));
		$this->assertSame(expected: 'refund', actual: $this->policy->paidOutcome(event: $event, choice: 'refund'));
		$this->assertSame(expected: 'refund', actual: $this->policy->paidOutcome(event: $event, choice: ''));
		$this->assertSame(expected: 'refund', actual: $this->policy->paidOutcome(event: $event, choice: 'none'));
	}
}
