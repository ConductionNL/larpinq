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
use OCA\Larpinq\Service\StaffInviteLink;
use OCA\Larpinq\Service\StaffInviteService;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
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
#[UsesClass(StaffInviteService::class)]
#[CoversClass(StaffInviteLink::class)]
class StaffInvitesControllerTest extends TestCase {

	private const CODE = '0123456789abcdef0123456789abcdef';

	private StaffInviteService&MockObject $invites;
	private ILockingProvider&MockObject $locks;

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
		$this->locks = $this->createMock(ILockingProvider::class);
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
			inviteLink: new StaffInviteLink(urlGenerator: $urls, lockingProvider: $this->locks),
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
			->willReturn(['invite' => ['role' => AppIdentity::ROLE_STEWARDS], 'token' => self::CODE]);

		$response = $controller->create();

		$this->assertSame(expected: 201, actual: $response->getStatus());
		$this->assertSame(expected: self::CODE, actual: $response->getData()['token']);
		$this->assertSame(expected: 'https://cloud.example/apps/larpinq/staff-invite/' . self::CODE, actual: $response->getData()['link']);
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
			->willReturn(['invite' => [], 'token' => self::CODE]);

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
		$controller = $this->controllerFor(groups: [], params: ['token' => self::CODE]);
		$this->invites->method('inspect')->with(self::CODE)->willReturn(['role' => AppIdentity::ROLE_STEWARDS, 'state' => 'valid']);

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

		$controller = $this->controllerFor(groups: null, params: ['token' => self::CODE]);
		$this->invites->expects($this->never())->method('inspect');
		$this->assertSame(expected: 401, actual: $controller->inspect()->getStatus());
	}//end testInspectRefusals()

	/**
	 * Pieter redeems a valid link.
	 *
	 * @return void
	 */
	public function testRedeem(): void {
		$controller = $this->controllerFor(groups: [], params: ['token' => self::CODE]);
		$this->invites->expects($this->once())->method('redeem')
			->with(self::CODE, $this->callback(static fn (IUser $user): bool => $user->getUID() === 'caller'))
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
		$controller = $this->controllerFor(groups: [], params: ['token' => self::CODE]);
		$this->invites->method('redeem')->willThrowException(new DomainException(StaffInviteService::STATE_USED_UP));
		$response = $controller->redeem();
		$this->assertSame(expected: 410, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'This invite is used up', 'state' => 'used-up'], actual: $response->getData());

		$controller = $this->controllerFor(groups: [], params: ['token' => self::CODE]);
		$this->invites->method('redeem')->willThrowException(new OutOfBoundsException('No such invite'));
		$this->assertSame(expected: 404, actual: $controller->redeem()->getStatus());

		$controller = $this->controllerFor(groups: null, params: ['token' => self::CODE]);
		$this->invites->expects($this->never())->method('redeem');
		$this->assertSame(expected: 401, actual: $controller->redeem()->getStatus());
	}//end testRedeemRefusals()

	/**
	 * A redeem runs under an exclusive lock on the invite's token hash, taken
	 * before the service reads the invite and released after, also when it is refused.
	 *
	 * @return void
	 */
	public function testRedeemRunsUnderTheInviteLock(): void {
		$controller = $this->controllerFor(groups: [], params: ['token' => self::CODE]);
		$key = 'larpinq/staff-invite/' . hash('sha256', self::CODE);
		$order = [];
		$this->locks->expects($this->once())->method('acquireLock')->with($key, ILockingProvider::LOCK_EXCLUSIVE)
			->willReturnCallback(static function () use (&$order): void {
				$order[] = 'lock';
			});
		$this->locks->expects($this->once())->method('releaseLock')->with($key, ILockingProvider::LOCK_EXCLUSIVE)
			->willReturnCallback(static function () use (&$order): void {
				$order[] = 'unlock';
			});
		$this->invites->method('redeem')->willReturnCallback(static function () use (&$order): array {
			$order[] = 'redeem';
			throw new DomainException(StaffInviteService::STATE_USED_UP);
		});

		$this->assertSame(expected: 410, actual: $controller->redeem()->getStatus());
		$this->assertSame(expected: ['lock', 'redeem', 'unlock'], actual: $order);
	}//end testRedeemRunsUnderTheInviteLock()

	/**
	 * While another redeem of the invite holds the lock, a redeem is answered 409 and adds nobody.
	 *
	 * @return void
	 */
	public function testARedeemWhileAnotherRunsIsAConflict(): void {
		$controller = $this->controllerFor(groups: [], params: ['token' => self::CODE]);
		$this->locks->method('acquireLock')->willThrowException(new LockedException('larpinq/staff-invite/x'));
		$this->locks->expects($this->never())->method('releaseLock');
		$this->invites->expects($this->never())->method('redeem');

		$response = $controller->redeem();

		$this->assertSame(expected: 409, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'The invite is being redeemed, try again'], actual: $response->getData());
	}//end testARedeemWhileAnotherRunsIsAConflict()

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
	 * A game master revokes an invite; an unknown or malformed one is a 404, and a
	 * failure to save is a 500, never reported as "not found".
	 *
	 * @return void
	 */
	public function testRevoke(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$this->invites->expects($this->exactly(4))->method('revoke')
			->willReturnCallback(static function (string $id, string $actingUid): array {
				if ($id === 'gone') {
					throw new \OCP\AppFramework\Db\DoesNotExistException('not found');
				}

				if ($id === 'not-a-uuid') {
					throw new \InvalidArgumentException('Invalid object ID: expected a UUID');
				}

				if ($id === 'broken') {
					throw new \RuntimeException('save failed');
				}

				return ['id' => $id, 'revokedBy' => $actingUid];
			});

		$this->assertSame(expected: ['id' => 'inv', 'revokedBy' => 'caller'], actual: $controller->revoke(id: 'inv')->getData());
		$this->assertSame(expected: 404, actual: $controller->revoke(id: 'gone')->getStatus());
		$this->assertSame(expected: 404, actual: $controller->revoke(id: 'not-a-uuid')->getStatus());
		$this->assertSame(expected: 500, actual: $controller->revoke(id: 'broken')->getStatus());
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

	/**
	 * Every refused redeem names its own reason, and a state without one still adds nobody.
	 *
	 * @return void
	 */
	public function testEachRefusedRedeemSaysWhy(): void {
		$cases = [
			StaffInviteService::STATE_EXPIRED => 'This invite has expired',
			StaffInviteService::STATE_REVOKED => 'This invite was revoked',
			'odd' => 'This invite adds nobody',
		];
		foreach ($cases as $state => $error) {
			$controller = $this->controllerFor(groups: [], params: ['token' => self::CODE]);
			$this->invites->method('redeem')->willThrowException(new DomainException($state));

			$response = $controller->redeem();

			$this->assertSame(expected: 410, actual: $response->getStatus(), message: $state);
			$this->assertSame(expected: ['error' => $error, 'state' => $state], actual: $response->getData());
		}
	}//end testEachRefusedRedeemSaysWhy()

	/**
	 * A failure behind create, inspect, redeem or team is a 500 with a neutral message, never the exception text.
	 *
	 * @return void
	 */
	public function testAFailureBehindAnEndpointIsAServerError(): void {
		$failure = new \RuntimeException('database is gone');

		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP], params: ['role' => AppIdentity::ROLE_STEWARDS]);
		$this->invites->method('create')->willThrowException($failure);
		$response = $controller->create();
		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'The invite could not be created'], actual: $response->getData());

		$controller = $this->controllerFor(groups: [], params: ['token' => self::CODE]);
		$this->invites->method('inspect')->willThrowException($failure);
		$response = $controller->inspect();
		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'The invite could not be read'], actual: $response->getData());

		$controller = $this->controllerFor(groups: [], params: ['token' => self::CODE]);
		$this->invites->method('redeem')->willThrowException($failure);
		$response = $controller->redeem();
		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'The invite could not be redeemed'], actual: $response->getData());

		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$this->invites->method('team')->willThrowException($failure);
		$response = $controller->team();
		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'The team could not be read'], actual: $response->getData());
	}//end testAFailureBehindAnEndpointIsAServerError()

	/**
	 * Create hands the service the acting game master and the request's values, cast to their types.
	 *
	 * @return void
	 */
	public function testCreatePassesTheRequestOn(): void {
		$controller = $this->controllerFor(
			groups: [AppIdentity::GM_GROUP],
			params: ['role' => AppIdentity::ROLE_RULES, 'days' => '14', 'maxUses' => '5']
		);
		$this->invites->expects($this->once())->method('create')
			->with(AppIdentity::ROLE_RULES, 14, 5, 'caller')
			->willReturn(['invite' => ['id' => 'i9'], 'token' => self::CODE]);

		$response = $controller->create();

		$this->assertSame(expected: 201, actual: $response->getStatus());
		$this->assertSame(expected: 'https://cloud.example/apps/larpinq/staff-invite/' . self::CODE, actual: $response->getData()['link']);
		$this->assertSame(expected: ['id' => 'i9'], actual: $response->getData()['invite']);
	}//end testCreatePassesTheRequestOn()

	/**
	 * An unknown role in a removal is a 400 with the service's reason.
	 *
	 * @return void
	 */
	public function testRemovingFromAnUnknownRoleIsRefused(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$this->invites->expects($this->once())->method('removeMember')
			->with('admin', 'lotte', 'caller')
			->willThrowException(new InvalidArgumentException('Unknown role'));

		$response = $controller->removeMember(role: 'admin', uid: 'lotte');

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Unknown role'], actual: $response->getData());
	}//end testRemovingFromAnUnknownRoleIsRefused()

	/**
	 * A request without a token or role hands the service empty strings, which it refuses itself.
	 *
	 * @return void
	 */
	public function testMissingParametersReachTheServiceAsEmptyStrings(): void {
		$controller = $this->controllerFor(groups: []);
		$this->invites->expects($this->once())->method('inspect')->with('')->willThrowException(new OutOfBoundsException('No such invite'));
		$this->invites->expects($this->once())->method('redeem')->with('', $this->isInstanceOf(\OCP\IUser::class))->willThrowException(new OutOfBoundsException('No such invite'));

		$this->assertSame(expected: ['error' => 'No such invite'], actual: $controller->inspect()->getData());
		$this->assertSame(expected: ['error' => 'No such invite'], actual: $controller->redeem()->getData());

		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$this->invites->expects($this->once())->method('create')
			->with('', StaffInviteService::DEFAULT_DAYS, 1, 'caller')
			->willThrowException(new InvalidArgumentException('Unknown role'));

		$response = $controller->create();

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Unknown role'], actual: $response->getData());
	}//end testMissingParametersReachTheServiceAsEmptyStrings()
}//end class
