<?php

/**
 * StaffInvitesController: who may create, revoke, list and remove, and what
 * opening and redeeming a link answers.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use DomainException;
use InvalidArgumentException;
use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\StaffInvitesController;
use OCA\Larpinq\Service\StaffInviteService;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;

/**
 * REQ-ASR-004 and REQ-ASR-005 of admin-staff-roles-and-invites.
 */
#[CoversClass(StaffInvitesController::class)]
#[UsesClass(CallerAccess::class)]
class StaffInvitesControllerTest extends TestCase {

	private const TOKEN = '0123456789abcdef0123456789abcdef';

	private StaffInviteService&MockObject $invites;

	/**
	 * The controller for a caller in these groups, or nobody signed in.
	 *
	 * @param list<string>|null $groups The caller's groups; null for no session.
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return StaffInvitesController
	 */
	private function controllerFor(?array $groups, array $params = []): StaffInvitesController {
		$this->invites = $this->createMock(StaffInviteService::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isInGroup')->willReturnCallback(
			static fn (string $uid, string $group): bool => in_array($group, $groups ?? [], true)
		);
		$groupManager->method('isAdmin')->willReturn(false);
		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($groups !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('caller');
		}

		$session->method('getUser')->willReturn($user);
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key, $default = null) => $params[$key] ?? $default);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->with('larpinq.dashboard.page')->willReturn('https://cloud.example/apps/larpinq/');

		return new StaffInvitesController(
			request: $request,
			invites: $this->invites,
			callerAccess: new CallerAccess(userSession: $session, groupManager: $groupManager),
			urlGenerator: $urls,
			logger: new NullLogger(),
		);
	}//end controllerFor()

	/**
	 * A game master creates a steward invite and gets the token and the link once.
	 *
	 * @return void
	 */
	public function testAGameMasterCreatesAnInvite(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP], params: ['role' => AppIdentity::ROLE_STEWARDS, 'days' => '7', 'maxUses' => '3']);
		$this->invites->expects($this->once())->method('create')
			->with(AppIdentity::ROLE_STEWARDS, 7, 3, 'caller')
			->willReturn(['invite' => ['role' => AppIdentity::ROLE_STEWARDS], 'token' => self::TOKEN]);

		$response = $controller->create();

		$this->assertSame(expected: 201, actual: $response->getStatus());
		$this->assertSame(expected: self::TOKEN, actual: $response->getData()['token']);
		$this->assertSame(expected: 'https://cloud.example/apps/larpinq/staff-invite/' . self::TOKEN, actual: $response->getData()['link']);
	}//end testAGameMasterCreatesAnInvite()

	/**
	 * Without days or uses the invite is valid 7 days for one person.
	 *
	 * @return void
	 */
	public function testCreateDefaultsToSevenDaysAndOneUse(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP], params: ['role' => AppIdentity::ROLE_WRITERS]);
		$this->invites->expects($this->once())->method('create')
			->with(AppIdentity::ROLE_WRITERS, 7, 1, 'caller')
			->willReturn(['invite' => [], 'token' => self::TOKEN]);

		$this->assertSame(expected: 201, actual: $controller->create()->getStatus());
	}//end testCreateDefaultsToSevenDaysAndOneUse()

	/**
	 * A bad role is a 400 with the reason.
	 *
	 * @return void
	 */
	public function testABadInviteIsRefused(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP], params: ['role' => 'admin']);
		$this->invites->method('create')->willThrowException(new InvalidArgumentException('Unknown role'));

		$response = $controller->create();

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Unknown role'], actual: $response->getData());
	}//end testABadInviteIsRefused()

	/**
	 * A staff member, even a steward, cannot create, revoke, list or remove: 403; nobody signed in: 401.
	 *
	 * @return void
	 */
	public function testOnlyGameMastersManageTheTeam(): void {
		foreach ([[AppIdentity::ROLE_STEWARDS, 403], [AppIdentity::ROLE_WRITERS, 403], [null, 401]] as [$group, $status]) {
			$controller = $this->controllerFor(groups: $group === null ? null : [$group, AppIdentity::USERS_GROUP], params: ['role' => AppIdentity::ROLE_STEWARDS]);
			$this->invites->expects($this->never())->method('create');
			$this->invites->expects($this->never())->method('revoke');
			$this->invites->expects($this->never())->method('team');
			$this->invites->expects($this->never())->method('removeMember');

			$this->assertSame(expected: $status, actual: $controller->create()->getStatus());
			$this->assertSame(expected: $status, actual: $controller->revoke(id: 'x')->getStatus());
			$this->assertSame(expected: $status, actual: $controller->team()->getStatus());
			$this->assertSame(expected: $status, actual: $controller->removeMember(role: AppIdentity::ROLE_STEWARDS, uid: 'joris')->getStatus());
		}
	}//end testOnlyGameMastersManageTheTeam()

	/**
	 * A signed-in user opens a link and learns its role and state.
	 *
	 * @return void
	 */
	public function testInspect(): void {
		$controller = $this->controllerFor(groups: [], params: ['token' => self::TOKEN]);
		$this->invites->method('inspect')->with(self::TOKEN)->willReturn(['role' => AppIdentity::ROLE_STEWARDS, 'state' => 'valid']);

		$response = $controller->inspect();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['role' => AppIdentity::ROLE_STEWARDS, 'state' => 'valid'], actual: $response->getData());
	}//end testInspect()

	/**
	 * An unknown token is a 404, and nobody signed in is a 401 without a lookup.
	 *
	 * @return void
	 */
	public function testInspectRefusals(): void {
		$controller = $this->controllerFor(groups: [], params: ['token' => 'nope']);
		$this->invites->method('inspect')->willThrowException(new OutOfBoundsException('No such invite'));
		$this->assertSame(expected: 404, actual: $controller->inspect()->getStatus());

		$controller = $this->controllerFor(groups: null, params: ['token' => self::TOKEN]);
		$this->invites->expects($this->never())->method('inspect');
		$this->assertSame(expected: 401, actual: $controller->inspect()->getStatus());
	}//end testInspectRefusals()

	/**
	 * Pieter redeems a valid link.
	 *
	 * @return void
	 */
	public function testRedeem(): void {
		$controller = $this->controllerFor(groups: [], params: ['token' => self::TOKEN]);
		$this->invites->expects($this->once())->method('redeem')
			->with(self::TOKEN, $this->callback(static fn (IUser $user): bool => $user->getUID() === 'caller'))
			->willReturn(['role' => AppIdentity::ROLE_STEWARDS, 'joined' => true]);

		$response = $controller->redeem();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['role' => AppIdentity::ROLE_STEWARDS, 'joined' => true], actual: $response->getData());
	}//end testRedeem()

	/**
	 * Karel opens the used-up link: 410 and he is told why; an unknown link is 404; nobody signed in 401.
	 *
	 * @return void
	 */
	public function testRedeemRefusals(): void {
		$controller = $this->controllerFor(groups: [], params: ['token' => self::TOKEN]);
		$this->invites->method('redeem')->willThrowException(new DomainException(StaffInviteService::STATE_USED_UP));
		$response = $controller->redeem();
		$this->assertSame(expected: 410, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'This invite is used up', 'state' => 'used-up'], actual: $response->getData());

		$controller = $this->controllerFor(groups: [], params: ['token' => self::TOKEN]);
		$this->invites->method('redeem')->willThrowException(new OutOfBoundsException('No such invite'));
		$this->assertSame(expected: 404, actual: $controller->redeem()->getStatus());

		$controller = $this->controllerFor(groups: null, params: ['token' => self::TOKEN]);
		$this->invites->expects($this->never())->method('redeem');
		$this->assertSame(expected: 401, actual: $controller->redeem()->getStatus());
	}//end testRedeemRefusals()

	/**
	 * Opening and redeeming are rate limited per user, redeeming the tighter.
	 *
	 * @return void
	 */
	public function testOpeningAndRedeemingAreRateLimited(): void {
		foreach (['inspect' => 20, 'redeem' => 10] as $method => $limit) {
			$attributes = (new ReflectionMethod(StaffInvitesController::class, $method))->getAttributes(UserRateLimit::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes, message: "{$method} must be rate limited");
			$this->assertSame(expected: ['limit' => $limit, 'period' => 300], actual: $attributes[0]->getArguments());
		}
	}//end testOpeningAndRedeemingAreRateLimited()

	/**
	 * A game master revokes an invite; an unknown one is a 404.
	 *
	 * @return void
	 */
	public function testRevoke(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$this->invites->expects($this->exactly(2))->method('revoke')
			->willReturnCallback(static function (string $id, string $actingUid): array {
				if ($id === 'gone') {
					throw new \RuntimeException('not found');
				}

				return ['id' => $id, 'revokedBy' => $actingUid];
			});

		$this->assertSame(expected: ['id' => 'inv', 'revokedBy' => 'caller'], actual: $controller->revoke(id: 'inv')->getData());
		$this->assertSame(expected: 404, actual: $controller->revoke(id: 'gone')->getStatus());
	}//end testRevoke()

	/**
	 * A game master reads the team.
	 *
	 * @return void
	 */
	public function testTeam(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$this->invites->method('team')->willReturn(['roles' => [], 'invites' => []]);

		$this->assertSame(expected: ['roles' => [], 'invites' => []], actual: $controller->team()->getData());
	}//end testTeam()

	/**
	 * A game master removes Lotte; not a member is 404; removing oneself from the game masters is 400.
	 *
	 * @return void
	 */
	public function testRemoveMember(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$this->invites->method('removeMember')->willReturnCallback(static function (string $role, string $uid, string $actingUid): bool {
			if ($uid === $actingUid) {
				throw new DomainException('You cannot remove yourself from the game masters');
			}

			return $uid === 'lotte';
		});

		$this->assertSame(expected: ['removed' => true, 'role' => AppIdentity::ROLE_TREASURERS, 'uid' => 'lotte'], actual: $controller->removeMember(role: AppIdentity::ROLE_TREASURERS, uid: 'lotte')->getData());
		$this->assertSame(expected: 404, actual: $controller->removeMember(role: AppIdentity::ROLE_TREASURERS, uid: 'anna')->getStatus());
		$this->assertSame(expected: 400, actual: $controller->removeMember(role: AppIdentity::GM_GROUP, uid: 'caller')->getStatus());
	}//end testRemoveMember()
}//end class
