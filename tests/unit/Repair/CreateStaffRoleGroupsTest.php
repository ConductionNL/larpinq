<?php

/**
 * Unit tests for the CreateStaffRoleGroups repair step.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-four-staff-roles-exist-next-to-the-game-masters-req-asr-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Repair;

use OCA\Larpinq\Repair\CreateStaffRoleGroups;
use OCA\Larpinq\Support\AppIdentity;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The four role groups are created when missing, and nothing else happens.
 */
class CreateStaffRoleGroupsTest extends TestCase {
	/**
	 * On a fresh install the four groups are created, and no game master group.
	 *
	 * @return void
	 */
	public function testAFreshInstallGetsTheFourGroups(): void {
		$created = [];
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('groupExists')->willReturn(false);
		$groups->method('createGroup')->willReturnCallback(
			function (string $gid) use (&$created): IGroup {
				$created[] = $gid;
				return $this->createMock(IGroup::class);
			}
		);
		$groups->expects(self::never())->method('get');

		(new CreateStaffRoleGroups($groups, $this->createMock(LoggerInterface::class)))->run($this->createMock(IOutput::class));

		self::assertSame(['larpinq-writers', 'larpinq-rules', 'larpinq-treasurers', 'larpinq-stewards'], $created);
		self::assertNotContains(AppIdentity::GM_GROUP, $created);
	}

	/**
	 * Groups that exist are left alone.
	 *
	 * @return void
	 */
	public function testExistingGroupsAreReused(): void {
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('groupExists')->willReturnCallback(static fn (string $gid): bool => $gid !== AppIdentity::ROLE_STEWARDS);
		$groups->expects(self::once())->method('createGroup')->with(AppIdentity::ROLE_STEWARDS)->willReturn($this->createMock(IGroup::class));

		(new CreateStaffRoleGroups($groups, $this->createMock(LoggerInterface::class)))->run($this->createMock(IOutput::class));
	}

	/**
	 * A group that cannot be created is logged and the step goes on with the next one.
	 *
	 * @return void
	 */
	public function testAFailureIsLoggedAndTheStepContinues(): void {
		$created = [];
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('groupExists')->willReturn(false);
		$groups->method('createGroup')->willReturnCallback(
			function (string $gid) use (&$created): ?IGroup {
				if ($gid === AppIdentity::ROLE_WRITERS) {
					throw new RuntimeException('backend down');
				}

				if ($gid === AppIdentity::ROLE_RULES) {
					return null;
				}

				$created[] = $gid;
				return $this->createMock(IGroup::class);
			}
		);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(2))->method('warning');

		(new CreateStaffRoleGroups($groups, $logger))->run($this->createMock(IOutput::class));

		self::assertSame([AppIdentity::ROLE_TREASURERS, AppIdentity::ROLE_STEWARDS], $created);
	}
}
