<?php

/**
 * Who writes in the help desk (communication-help-desk, REQ-CHD-002,
 * REQ-CHD-003): the signed-in user's id, and whether they answer questions as
 * a game master.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\HelpDeskCaller;
use OCA\Larpinq\Listener\HelpMessageListener;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HelpDeskCaller::class)]
class HelpDeskCallerTest extends TestCase {

	public function testTheSignedInUsersIdIsTheCaller(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('anna');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$caller = new HelpDeskCaller(userSession: $session, groupManager: $this->createMock(IGroupManager::class));

		self::assertSame('anna', $caller->uid());
	}

	public function testNobodySignedInIsAnEmptyCaller(): void {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);

		$caller = new HelpDeskCaller(userSession: $session, groupManager: $this->createMock(IGroupManager::class));

		self::assertSame('', $caller->uid());
	}

	public function testAMemberOfTheGameMastersGroupIsAnOrganiser(): void {
		$groups = $this->createMock(IGroupManager::class);
		$groups->expects(self::once())
			->method('isInGroup')
			->with('joris', HelpMessageListener::ORGANISERS)
			->willReturn(true);

		$caller = new HelpDeskCaller(userSession: $this->createMock(IUserSession::class), groupManager: $groups);

		self::assertTrue($caller->isOrganiser('joris'));
	}

	public function testSomeoneOutsideTheGameMastersGroupIsNoOrganiser(): void {
		$groups = $this->createMock(IGroupManager::class);
		$groups->expects(self::once())
			->method('isInGroup')
			->with('anna', 'gamemasters')
			->willReturn(false);

		$caller = new HelpDeskCaller(userSession: $this->createMock(IUserSession::class), groupManager: $groups);

		self::assertFalse($caller->isOrganiser('anna'));
	}

	public function testAnEmptyCallerIsNeverAnOrganiserAndTheGroupsAreNotAsked(): void {
		$groups = $this->createMock(IGroupManager::class);
		$groups->expects(self::never())->method('isInGroup');

		$caller = new HelpDeskCaller(userSession: $this->createMock(IUserSession::class), groupManager: $groups);

		self::assertFalse($caller->isOrganiser(''));
	}
}
