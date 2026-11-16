<?php

/**
 * StaffInviteService: creating, reading, redeeming and revoking staff
 * invites, and listing and trimming the team.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use DateTime;
use DomainException;
use InvalidArgumentException;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\StaffInviteService;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * REQ-ASR-004 and REQ-ASR-005 of admin-staff-roles-and-invites.
 */
#[CoversClass(StaffInviteService::class)]
class StaffInviteServiceTest extends TestCase {

	private const CODE = '0123456789abcdef0123456789abcdef';
	private const ID = '11111111-2222-3333-4444-555555555555';
	private const NOW = '2026-11-14T12:00:00+00:00';

	private RegisterObjectFetcher&MockObject $fetcher;
	private IGroupManager&MockObject $groupManager;
	private IUserManager&MockObject $userManager;
	private ILockingProvider&MockObject $locks;

	/**
	 * Groups by id, as the group manager answers them.
	 *
	 * @var array<string, IGroup&MockObject>
	 */
	private array $groups = [];

	/**
	 * The service at a fixed moment.
	 *
	 * @return StaffInviteService
	 */
	private function service(): StaffInviteService {
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->groupManager->method('get')->willReturnCallback(fn (string $gid): ?IGroup => $this->groups[$gid] ?? null);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->locks = $this->createMock(ILockingProvider::class);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getDateTime')->willReturnCallback(static fn (): DateTime => new DateTime(self::NOW));

		return new StaffInviteService(
			objectFetcher: $this->fetcher,
			groupManager: $this->groupManager,
			userManager: $this->userManager,
			timeFactory: $clock,
			lockingProvider: $this->locks,
		);
	}//end service()

	/**
	 * A group whose members are these uids.
	 *
	 * @param string $gid The group id.
	 * @param list<string> $members The member uids.
	 *
	 * @return IGroup&MockObject
	 */
	private function group(string $gid, array $members = []): IGroup&MockObject {
		$group = $this->createMock(IGroup::class);
		$group->method('inGroup')->willReturnCallback(static fn (IUser $user): bool => in_array($user->getUID(), $members, true));
		$group->method('getUsers')->willReturnCallback(fn (): array => array_map(fn (string $uid): IUser => $this->user(uid: $uid), $members));
		$this->groups[$gid] = $group;
		return $group;
	}//end group()

	/**
	 * A user.
	 *
	 * @param string $uid The uid, also the display name, capitalised.
	 *
	 * @return IUser&MockObject
	 */
	private function user(string $uid): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn(ucfirst($uid));
		return $user;
	}//end user()

	/**
	 * A stored invite whose token is CODE.
	 *
	 * @param array<string, mixed> $fields Fields over the valid default.
	 *
	 * @return array<string, mixed>
	 */
	private static function invite(array $fields = []): array {
		return array_merge(
			[
				'id' => self::ID,
				'@self' => ['id' => self::ID],
				'role' => AppIdentity::ROLE_STEWARDS,
				'tokenHash' => hash('sha256', self::CODE),
				'expiresAt' => '2026-11-21T12:00:00+00:00',
				'maxUses' => 1,
				'uses' => [],
				'createdBy' => 'gm',
			],
			$fields
		);
	}//end invite()

	/**
	 * The token lookup answers with these invites.
	 *
	 * @param list<array<string, mixed>> $found The invites.
	 *
	 * @return void
	 */
	private function lookupFinds(array $found): void {
		$this->fetcher->method('getObjectsWithAppAuthority')
			->with('staffInvite', ['tokenHash' => hash('sha256', self::CODE)], 2)
			->willReturn($found);
	}//end lookupFinds()

	/**
	 * A created invite stores the hash, the expiry and the uses, and returns the plain token once.
	 *
	 * @return void
	 */
	public function testCreateStoresOnlyTheHashAndReturnsTheToken(): void {
		$service = $this->service();
		$stored = null;
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')
			->willReturnCallback(static function (string $type, array $data) use (&$stored): array {
				$stored = $data;
				return $data + ['id' => self::ID];
			});

		$created = $service->create(role: AppIdentity::ROLE_STEWARDS, days: 7, maxUses: 3, actingUid: 'gm');

		$this->assertMatchesRegularExpression(pattern: '/^[0-9a-f]{32}$/', string: $created['token']);
		$this->assertSame(expected: hash('sha256', $created['token']), actual: $stored['tokenHash']);
		$this->assertSame(expected: '2026-11-21T12:00:00+00:00', actual: $stored['expiresAt']);
		$this->assertSame(expected: 3, actual: $stored['maxUses']);
		$this->assertSame(expected: [], actual: $stored['uses']);
		$this->assertSame(expected: 'gm', actual: $stored['createdBy']);
		$this->assertArrayNotHasKey(key: 'tokenHash', array: $created['invite']);
		$this->assertStringNotContainsString(needle: $created['token'], haystack: (string)json_encode($stored));
	}//end testCreateStoresOnlyTheHashAndReturnsTheToken()

	/**
	 * An unknown role, or days or uses out of range, create nothing.
	 *
	 * @return void
	 */
	public function testCreateRefusesWhatIsOutOfRange(): void {
		$service = $this->service();
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		foreach ([['admin', 7, 1], [AppIdentity::ROLE_RULES, 0, 1], [AppIdentity::ROLE_RULES, 91, 1], [AppIdentity::ROLE_RULES, 7, 0], [AppIdentity::ROLE_RULES, 7, 101]] as [$role, $days, $uses]) {
			try {
				$service->create(role: $role, days: $days, maxUses: $uses, actingUid: 'gm');
				$this->fail("{$role}/{$days}/{$uses} must be refused");
			} catch (InvalidArgumentException $exception) {
				$this->assertNotSame(expected: '', actual: $exception->getMessage());
			}
		}
	}//end testCreateRefusesWhatIsOutOfRange()

	/**
	 * The state is revoked before expired before used up.
	 *
	 * @return void
	 */
	public function testTheStateOfAnInvite(): void {
		$service = $this->service();
		$used = ['uses' => [['uid' => 'pieter', 'at' => self::NOW]]];

		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite()));
		$this->assertSame(expected: 'used-up', actual: $service->state(invite: self::invite(fields: $used)));
		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite(fields: $used + ['maxUses' => 2])));
		$this->assertSame(expected: 'expired', actual: $service->state(invite: self::invite(fields: $used + ['expiresAt' => self::NOW])));
		$this->assertSame(expected: 'expired', actual: $service->state(invite: self::invite(fields: ['expiresAt' => null])));
		$this->assertSame(expected: 'revoked', actual: $service->state(invite: self::invite(fields: ['revokedAt' => '2026-11-13T00:00:00+00:00', 'expiresAt' => self::NOW])));
	}//end testTheStateOfAnInvite()

	/**
	 * The landing page learns only the role and the state.
	 *
	 * @return void
	 */
	public function testInspectAnswersRoleAndState(): void {
		$service = $this->service();
		$this->lookupFinds(found: [self::invite()]);

		$this->assertSame(expected: ['role' => AppIdentity::ROLE_STEWARDS, 'state' => 'valid'], actual: $service->inspect(token: self::CODE));
	}//end testInspectAnswersRoleAndState()

	/**
	 * A malformed token is not looked up; a token whose hash does not match is no invite.
	 *
	 * @return void
	 */
	public function testAnUnknownTokenOpensNothing(): void {
		$service = $this->service();
		$this->fetcher->expects($this->once())->method('getObjectsWithAppAuthority')
			->willReturn([self::invite(fields: ['tokenHash' => hash('sha256', 'something else')])]);

		try {
			$service->inspect(token: 'not-a-token');
			$this->fail('a malformed token must open nothing');
		} catch (OutOfBoundsException) {
			$this->addToAssertionCount(1);
		}

		$this->expectException(OutOfBoundsException::class);
		$service->inspect(token: self::CODE);
	}//end testAnUnknownTokenOpensNothing()

	/**
	 * Pieter redeems a steward invite: he joins the stewards and larpers and the use is recorded.
	 *
	 * @return void
	 */
	public function testRedeemAddsTheUserAndRecordsTheUse(): void {
		$service = $this->service();
		$stewards = $this->group(gid: AppIdentity::ROLE_STEWARDS);
		$larpers = $this->group(gid: AppIdentity::USERS_GROUP);
		$this->lookupFinds(found: [self::invite()]);
		$pieter = $this->user(uid: 'pieter');

		$stewards->expects($this->once())->method('addUser')->with($pieter);
		$larpers->expects($this->once())->method('addUser')->with($pieter);
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')
			->with(
				'staffInvite',
				$this->callback(static fn (array $data): bool => $data['uses'] === [['uid' => 'pieter', 'at' => self::NOW]]
					&& $data['tokenHash'] === hash('sha256', self::CODE)
					&& isset($data['@self'], $data['id']) === false),
				self::ID
			)
			->willReturnArgument(1);

		$this->assertSame(expected: ['role' => AppIdentity::ROLE_STEWARDS, 'joined' => true], actual: $service->redeem(token: self::CODE, user: $pieter));
	}//end testRedeemAddsTheUserAndRecordsTheUse()

	/**
	 * Karel opens the used-up invite and joins nothing.
	 *
	 * @return void
	 */
	public function testAUsedUpInviteAddsNobody(): void {
		$service = $this->service();
		$stewards = $this->group(gid: AppIdentity::ROLE_STEWARDS);
		$this->lookupFinds(found: [self::invite(fields: ['uses' => [['uid' => 'pieter', 'at' => self::NOW]]])]);

		$stewards->expects($this->never())->method('addUser');
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$this->expectException(DomainException::class);
		$this->expectExceptionMessage('used-up');
		$service->redeem(token: self::CODE, user: $this->user(uid: 'karel'));
	}//end testAUsedUpInviteAddsNobody()

	/**
	 * A revoked or expired invite adds nobody either.
	 *
	 * @return void
	 */
	public function testARevokedOrExpiredInviteAddsNobody(): void {
		foreach (['revoked' => ['revokedAt' => '2026-11-13T00:00:00+00:00'], 'expired' => ['expiresAt' => '2026-11-14T11:59:59+00:00']] as $state => $fields) {
			$service = $this->service();
			$stewards = $this->group(gid: AppIdentity::ROLE_STEWARDS);
			$this->lookupFinds(found: [self::invite(fields: $fields)]);
			$stewards->expects($this->never())->method('addUser');

			try {
				$service->redeem(token: self::CODE, user: $this->user(uid: 'karel'));
				$this->fail("a {$state} invite must add nobody");
			} catch (DomainException $exception) {
				$this->assertSame(expected: $state, actual: $exception->getMessage());
			}
		}
	}//end testARevokedOrExpiredInviteAddsNobody()

	/**
	 * Two redeems of a one-use link at once add one user: the second one reads
	 * the invite again under the lock, sees the use the first one recorded and
	 * adds nobody. The lock is taken on the invite and released after.
	 *
	 * @return void
	 */
	public function testARedeemReadsTheInviteAgainUnderTheLock(): void {
		$service = $this->service();
		$stewards = $this->group(gid: AppIdentity::ROLE_STEWARDS);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnOnConsecutiveCalls(
			[self::invite()],
			[self::invite(fields: ['uses' => [['uid' => 'pieter', 'at' => self::NOW]]])]
		);
		$key = 'larpinq/staff-invite/' . self::ID;
		$this->locks->expects($this->once())->method('acquireLock')->with($key, ILockingProvider::LOCK_EXCLUSIVE);
		$this->locks->expects($this->once())->method('releaseLock')->with($key, ILockingProvider::LOCK_EXCLUSIVE);

		$stewards->expects($this->never())->method('addUser');
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$this->expectException(DomainException::class);
		$this->expectExceptionMessage('used-up');
		$service->redeem(token: self::CODE, user: $this->user(uid: 'karel'));
	}//end testARedeemReadsTheInviteAgainUnderTheLock()

	/**
	 * A redeem that cannot take the lock records no use and adds nobody.
	 *
	 * @return void
	 */
	public function testARedeemThatCannotLockAddsNobody(): void {
		$service = $this->service();
		$stewards = $this->group(gid: AppIdentity::ROLE_STEWARDS);
		$this->lookupFinds(found: [self::invite()]);
		$this->locks->method('acquireLock')->willThrowException(new \OCP\Lock\LockedException('larpinq/staff-invite/' . self::ID));
		$this->locks->expects($this->never())->method('releaseLock');

		$stewards->expects($this->never())->method('addUser');
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$this->expectException(\OCP\Lock\LockedException::class);
		$service->redeem(token: self::CODE, user: $this->user(uid: 'karel'));
	}//end testARedeemThatCannotLockAddsNobody()

	/**
	 * A member of the role already joins nothing new and takes no use.
	 *
	 * @return void
	 */
	public function testAMemberTakesNoUse(): void {
		$service = $this->service();
		$stewards = $this->group(gid: AppIdentity::ROLE_STEWARDS, members: ['joris']);
		$this->lookupFinds(found: [self::invite()]);

		$stewards->expects($this->never())->method('addUser');
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$this->assertSame(expected: ['role' => AppIdentity::ROLE_STEWARDS, 'joined' => false], actual: $service->redeem(token: self::CODE, user: $this->user(uid: 'joris')));
	}//end testAMemberTakesNoUse()

	/**
	 * An invite whose group is missing adds nobody.
	 *
	 * @return void
	 */
	public function testAMissingGroupAddsNobody(): void {
		$service = $this->service();
		$this->lookupFinds(found: [self::invite()]);
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$this->expectException(OutOfBoundsException::class);
		$service->redeem(token: self::CODE, user: $this->user(uid: 'pieter'));
	}//end testAMissingGroupAddsNobody()

	/**
	 * Revoking stamps who and when, keeps the hash in storage and returns the invite without it.
	 *
	 * @return void
	 */
	public function testRevokeStampsTheInvite(): void {
		$service = $this->service();
		$this->fetcher->method('getObjectOfType')->with('staffInvite', self::ID)->willReturn(self::invite());
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')
			->with(
				'staffInvite',
				$this->callback(static fn (array $data): bool => $data['revokedAt'] === self::NOW && $data['revokedBy'] === 'gm' && isset($data['tokenHash']) === true),
				self::ID
			)
			->willReturnCallback(static fn (string $type, array $data): array => $data);

		$revoked = $service->revoke(id: self::ID, actingUid: 'gm');

		$this->assertSame(expected: self::NOW, actual: $revoked['revokedAt']);
		$this->assertArrayNotHasKey(key: 'tokenHash', array: $revoked);
	}//end testRevokeStampsTheInvite()

	/**
	 * An invite revoked already is left as it is.
	 *
	 * @return void
	 */
	public function testRevokingTwiceChangesNothing(): void {
		$service = $this->service();
		$this->fetcher->method('getObjectOfType')->willReturn(self::invite(fields: ['revokedAt' => '2026-11-13T00:00:00+00:00', 'revokedBy' => 'other']));
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$this->assertSame(expected: 'other', actual: $service->revoke(id: self::ID, actingUid: 'gm')['revokedBy']);
	}//end testRevokingTwiceChangesNothing()

	/**
	 * The team lists every role's members by name and every invite with its state, without hashes.
	 *
	 * @return void
	 */
	public function testTheTeam(): void {
		$service = $this->service();
		$this->group(gid: AppIdentity::GM_GROUP, members: ['gm']);
		$this->group(gid: AppIdentity::ROLE_TREASURERS, members: ['lotte', 'anna']);
		$this->fetcher->method('getObjects')->with('staffInvite')->willReturn([self::invite(), self::invite(fields: ['revokedAt' => self::NOW])]);

		$team = $service->team();

		$this->assertSame(expected: StaffInviteService::roles(), actual: array_column($team['roles'], 'role'));
		$this->assertSame(expected: [['uid' => 'gm', 'displayName' => 'Gm']], actual: $team['roles'][0]['members']);
		$this->assertSame(expected: ['anna', 'lotte'], actual: array_column($team['roles'][3]['members'], 'uid'));
		$this->assertSame(expected: [], actual: $team['roles'][1]['members'], message: 'a missing group has no members');
		$this->assertSame(expected: ['valid', 'revoked'], actual: array_column($team['invites'], 'state'));
		foreach ($team['invites'] as $invite) {
			$this->assertArrayNotHasKey(key: 'tokenHash', array: $invite);
		}
	}//end testTheTeam()

	/**
	 * A game master removes Lotte from the treasurers.
	 *
	 * @return void
	 */
	public function testRemoveMember(): void {
		$service = $this->service();
		$treasurers = $this->group(gid: AppIdentity::ROLE_TREASURERS, members: ['lotte']);
		$lotte = $this->user(uid: 'lotte');
		$this->userManager->method('get')->willReturnMap([['lotte', $lotte], ['nobody', null]]);

		$treasurers->expects($this->once())->method('removeUser')->with($lotte);

		$this->assertTrue(condition: $service->removeMember(role: AppIdentity::ROLE_TREASURERS, uid: 'lotte', actingUid: 'gm'));
		$this->assertFalse(condition: $service->removeMember(role: AppIdentity::ROLE_TREASURERS, uid: 'nobody', actingUid: 'gm'));
	}//end testRemoveMember()

	/**
	 * Another group is not a role, and a game master cannot remove themself from the game masters.
	 *
	 * @return void
	 */
	public function testRemoveMemberRefusals(): void {
		$service = $this->service();
		$gms = $this->group(gid: AppIdentity::GM_GROUP, members: ['gm']);
		$gms->expects($this->never())->method('removeUser');

		try {
			$service->removeMember(role: 'admin', uid: 'someone', actingUid: 'gm');
			$this->fail('admin is not a role');
		} catch (InvalidArgumentException) {
			$this->addToAssertionCount(1);
		}

		$this->expectException(DomainException::class);
		$service->removeMember(role: AppIdentity::GM_GROUP, uid: 'gm', actingUid: 'gm');
	}//end testRemoveMemberRefusals()

	/**
	 * Odd stored fields read the safe way: an unreadable expiry is expired, an
	 * empty revokedAt is not a revocation, and a maxUses below one still allows one use.
	 *
	 * @return void
	 */
	public function testTheStateOfAnInviteWithOddFields(): void {
		$service = $this->service();

		$this->assertSame(expected: 'expired', actual: $service->state(invite: self::invite(fields: ['expiresAt' => 'not a date'])));
		$this->assertSame(expected: 'expired', actual: $service->state(invite: self::invite(fields: ['expiresAt' => 1795000000])));
		$this->assertSame(expected: 'expired', actual: $service->state(invite: self::invite(fields: ['expiresAt' => '2026-11-14T11:59:59+00:00'])));
		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite(fields: ['expiresAt' => '2026-11-14T12:00:01+00:00'])));
		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite(fields: ['revokedAt' => ''])));
		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite(fields: ['revokedAt' => true])));
		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite(fields: ['maxUses' => 0])));
		$this->assertSame(expected: 'used-up', actual: $service->state(invite: self::invite(fields: ['maxUses' => 0, 'uses' => [['uid' => 'pieter']]])));
		$twoUses = ['uses' => [['uid' => 'a'], ['uid' => 'b']]];
		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite(fields: $twoUses + ['maxUses' => '3'])));
		$this->assertSame(expected: 'used-up', actual: $service->state(invite: self::invite(fields: $twoUses + ['maxUses' => '2'])));
	}//end testTheStateOfAnInviteWithOddFields()

	/**
	 * Uses that are not records count for nothing, and a uses field that is no list is no use.
	 *
	 * @return void
	 */
	public function testOnlyRecordedUsesCount(): void {
		$service = $this->service();

		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite(fields: ['uses' => 'pieter'])));
		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite(fields: ['uses' => ['pieter', 7, null]])));
		$this->assertSame(expected: 'used-up', actual: $service->state(invite: self::invite(fields: ['uses' => ['pieter', ['uid' => 'pieter']]])));
	}//end testOnlyRecordedUsesCount()

	/**
	 * The stored hash is the SHA-256 of the token, never the token itself.
	 *
	 * @return void
	 */
	public function testHashTokenIsSha256(): void {
		$this->assertSame(expected: hash('sha256', self::CODE), actual: StaffInviteService::hashToken(token: self::CODE));
		$this->assertSame(expected: 64, actual: strlen(StaffInviteService::hashToken(token: self::CODE)));
		$reversed = StaffInviteService::hashToken(token: strrev(self::CODE));
		$this->assertNotSame(expected: StaffInviteService::hashToken(token: self::CODE), actual: $reversed);
	}//end testHashTokenIsSha256()

	/**
	 * Only 32 lowercase hex characters are looked up; anything else opens nothing without a lookup.
	 *
	 * @return void
	 */
	public function testAMalformedTokenIsNeverLookedUp(): void {
		$service = $this->service();
		$this->fetcher->expects($this->never())->method('getObjectsWithAppAuthority');

		$refused = 0;
		foreach ([strtoupper(self::CODE), substr(self::CODE, 1), self::CODE . '0', '', ' ' . self::CODE] as $token) {
			try {
				$service->inspect(token: $token);
			} catch (OutOfBoundsException $e) {
				$this->assertSame(expected: 'No such invite', actual: $e->getMessage());
				$refused++;
			}
		}

		$this->assertSame(expected: 5, actual: $refused);
	}//end testAMalformedTokenIsNeverLookedUp()

	/**
	 * A store that ignores the hash filter never opens another invite: the matching one is picked.
	 *
	 * @return void
	 */
	public function testTheLookupRechecksTheHash(): void {
		$service = $this->service();
		$this->lookupFinds(found: [
			self::invite(fields: ['tokenHash' => hash('sha256', 'another invite'), 'role' => AppIdentity::GM_GROUP]),
			self::invite(fields: ['tokenHash' => null, 'role' => AppIdentity::GM_GROUP]),
			self::invite(),
		]);

		$this->assertSame(expected: ['role' => AppIdentity::ROLE_STEWARDS, 'state' => 'valid'], actual: $service->inspect(token: self::CODE));
	}//end testTheLookupRechecksTheHash()

	/**
	 * The edges of the ranges are allowed: 1 and 90 days, 1 and 100 uses, and the expiry follows the days.
	 *
	 * @return void
	 */
	public function testCreateAcceptsTheEdgesOfTheRanges(): void {
		$service = $this->service();
		$stored = [];
		$this->fetcher->expects($this->exactly(2))->method('saveObjectWithAppAuthority')
			->willReturnCallback(static function (string $type, array $data) use (&$stored): array {
				$stored[] = $data;
				return $data + ['id' => self::ID];
			});

		$service->create(role: AppIdentity::ROLE_WRITERS, days: 1, maxUses: 1, actingUid: 'gm');
		$service->create(role: AppIdentity::GM_GROUP, days: StaffInviteService::MAX_DAYS, maxUses: StaffInviteService::MAX_USES, actingUid: 'gm');

		$this->assertSame(expected: '2026-11-15T12:00:00+00:00', actual: $stored[0]['expiresAt']);
		$this->assertSame(expected: 1, actual: $stored[0]['maxUses']);
		$this->assertSame(expected: AppIdentity::ROLE_WRITERS, actual: $stored[0]['role']);
		$this->assertSame(expected: '2027-02-12T12:00:00+00:00', actual: $stored[1]['expiresAt']);
		$this->assertSame(expected: 100, actual: $stored[1]['maxUses']);
		$this->assertSame(expected: AppIdentity::GM_GROUP, actual: $stored[1]['role']);
	}//end testCreateAcceptsTheEdgesOfTheRanges()

	/**
	 * A refused range says what the range is.
	 *
	 * @return void
	 */
	public function testARefusedRangeNamesTheRange(): void {
		$service = $this->service();
		$messages = [];
		foreach ([['admin', 7, 1], [AppIdentity::ROLE_RULES, 91, 1], [AppIdentity::ROLE_RULES, 7, 101]] as [$role, $days, $uses]) {
			try {
				$service->create(role: $role, days: $days, maxUses: $uses, actingUid: 'gm');
			} catch (InvalidArgumentException $exception) {
				$messages[] = $exception->getMessage();
			}
		}

		$this->assertSame(
			expected: ['Unknown role', 'An invite is valid for 1 to 90 days', 'An invite has 1 to 100 uses'],
			actual: $messages
		);
	}//end testARefusedRangeNamesTheRange()

	/**
	 * An invite expires at its expiry, not a second later.
	 *
	 * @return void
	 */
	public function testAnInviteExpiresAtItsExpiry(): void {
		$service = $this->service();

		$this->assertSame(expected: 'expired', actual: $service->state(invite: self::invite(fields: ['expiresAt' => self::NOW])));
		$this->assertSame(expected: 'valid', actual: $service->state(invite: self::invite(fields: ['expiresAt' => '2026-11-14T12:00:01+00:00'])));
		$this->assertSame(expected: 'expired', actual: $service->state(invite: self::invite(fields: ['expiresAt' => null])));
	}//end testAnInviteExpiresAtItsExpiry()

	/**
	 * Someone who is not in the role, or a role whose group is missing, removes nobody.
	 *
	 * @return void
	 */
	public function testRemovingSomeoneWhoIsNotThereRemovesNobody(): void {
		$service = $this->service();
		$stewards = $this->group(gid: AppIdentity::ROLE_STEWARDS, members: ['joris']);
		$stewards->expects($this->never())->method('removeUser');
		$this->userManager->method('get')->willReturnCallback(fn (string $uid): IUser => $this->user(uid: $uid));

		$this->assertFalse(condition: $service->removeMember(role: AppIdentity::ROLE_STEWARDS, uid: 'lotte', actingUid: 'gm'));
		$this->assertFalse(condition: $service->removeMember(role: AppIdentity::ROLE_RULES, uid: 'joris', actingUid: 'gm'));
	}//end testRemovingSomeoneWhoIsNotThereRemovesNobody()

	/**
	 * A game master may remove another game master, and themself from any other role.
	 *
	 * @return void
	 */
	public function testOnlyLeavingTheGameMastersYourselfIsRefused(): void {
		$service = $this->service();
		$gms = $this->group(gid: AppIdentity::GM_GROUP, members: ['gm', 'anna']);
		$writers = $this->group(gid: AppIdentity::ROLE_WRITERS, members: ['gm']);
		$users = ['gm' => $this->user(uid: 'gm'), 'anna' => $this->user(uid: 'anna')];
		$this->userManager->method('get')->willReturnCallback(static fn (string $uid): ?IUser => $users[$uid] ?? null);

		$gms->expects($this->once())->method('removeUser')->with($users['anna']);
		$writers->expects($this->once())->method('removeUser')->with($users['gm']);

		$this->assertTrue(condition: $service->removeMember(role: AppIdentity::GM_GROUP, uid: 'anna', actingUid: 'gm'));
		$this->assertTrue(condition: $service->removeMember(role: AppIdentity::ROLE_WRITERS, uid: 'gm', actingUid: 'gm'));
	}//end testOnlyLeavingTheGameMastersYourselfIsRefused()

	/**
	 * The team lists members by display name regardless of case, and no invites when none are stored.
	 *
	 * @return void
	 */
	public function testTheTeamSortsMembersByNameAndMayHaveNoInvites(): void {
		$service = $this->service();
		$this->group(gid: AppIdentity::ROLE_STEWARDS, members: ['zoe', 'bram', 'Anna']);
		$this->fetcher->method('getObjects')->with('staffInvite')->willReturn([]);

		$team = $service->team();

		$stewards = array_values(array_filter($team['roles'], static fn (array $role): bool => $role['role'] === AppIdentity::ROLE_STEWARDS));
		$this->assertSame(expected: ['Anna', 'bram', 'zoe'], actual: array_column($stewards[0]['members'], 'uid'));
		$this->assertSame(expected: [], actual: $team['invites']);
	}//end testTheTeamSortsMembersByNameAndMayHaveNoInvites()

	/**
	 * A redeemer already among the larpinq users is not added there twice, and earlier uses are kept.
	 *
	 * @return void
	 */
	public function testRedeemKeepsEarlierUsesAndAddsAUserOnce(): void {
		$service = $this->service();
		$writers = $this->group(gid: AppIdentity::ROLE_WRITERS);
		$larpers = $this->group(gid: AppIdentity::USERS_GROUP, members: ['pieter']);
		$pieter = $this->user(uid: 'pieter');
		$earlier = ['uid' => 'anna', 'at' => '2026-11-13T09:00:00+00:00'];
		$this->lookupFinds(found: [self::invite(fields: ['role' => AppIdentity::ROLE_WRITERS, 'maxUses' => 2, 'uses' => [$earlier]])]);

		$writers->expects($this->once())->method('addUser')->with($pieter);
		$larpers->expects($this->never())->method('addUser');
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')
			->with(
				'staffInvite',
				$this->callback(static fn (array $data): bool => $data['uses'] === [$earlier, ['uid' => 'pieter', 'at' => self::NOW]]),
				self::ID
			);

		$this->assertSame(expected: ['role' => AppIdentity::ROLE_WRITERS, 'joined' => true], actual: $service->redeem(token: self::CODE, user: $pieter));
	}//end testRedeemKeepsEarlierUsesAndAddsAUserOnce()

	/**
	 * An invite that names a group which is not a staff role adds nobody, even when that group exists.
	 *
	 * @return void
	 */
	public function testAnInviteForAnotherGroupAddsNobody(): void {
		$service = $this->service();
		$admins = $this->group(gid: 'admin');
		$this->lookupFinds(found: [self::invite(fields: ['role' => 'admin'])]);

		$admins->expects($this->never())->method('addUser');
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$this->expectException(OutOfBoundsException::class);
		$service->redeem(token: self::CODE, user: $this->user(uid: 'pieter'));
	}//end testAnInviteForAnotherGroupAddsNobody()

	/**
	 * Opening a link answers only the role and state, never the hash or who made it.
	 *
	 * @return void
	 */
	public function testInspectAnswersNothingElse(): void {
		$service = $this->service();
		$this->lookupFinds(found: [self::invite(fields: ['role' => AppIdentity::ROLE_TREASURERS, 'revokedAt' => self::NOW])]);

		$this->assertSame(
			expected: ['role' => AppIdentity::ROLE_TREASURERS, 'state' => 'revoked'],
			actual: $service->inspect(token: self::CODE)
		);
	}//end testInspectAnswersNothingElse()
}//end class
