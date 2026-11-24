<?php

	/**
 * StaffInviteService for Larpinq
 *
 * Staff invites: a game master creates a link for one role, a signed-in user
 * who opens it joins that role, and game masters see and manage the team.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeInterface;
use DomainException;
use InvalidArgumentException;
use OCA\Larpinq\Support\AppIdentity;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OutOfBoundsException;

	/**
 * Creates, reads, redeems and revokes staff invites, and lists and trims the team.
 *
 * Only a SHA-256 hash of an invite's token is stored; the plain token is
 * returned once, by create(). Invites are written with the app's authority
 * after the caller's own check, so the token hash never passes the object API.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */
class StaffInviteService {

	/**
	 * The object type of a staff invite.
	 */
	public const OBJECT_TYPE = 'staffInvite';

	/**
	 * Days an invite is valid when the game master names none.
	 */
	public const DEFAULT_DAYS = 7;

	/**
	 * The longest an invite may be valid, in days.
	 */
	public const MAX_DAYS = 90;

	/**
	 * The most people one invite may add.
	 */
	public const MAX_USES = 100;

	/**
	 * An invite that still adds people.
	 */
	public const STATE_VALID = 'valid';

	/**
	 * An invite past its expiry.
	 */
	public const STATE_EXPIRED = 'expired';

	/**
	 * An invite whose uses are all taken.
	 */
	public const STATE_USED_UP = 'used-up';

	/**
	 * An invite a game master revoked.
	 */
	public const STATE_REVOKED = 'revoked';

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher The OpenRegister object access.
	 * @param IGroupManager $groupManager The Nextcloud groups.
	 * @param StaffInviteState $inviteState The state of an invite and the clock.
	 * @param StaffTeam $team The members of each role.
	 *
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly IGroupManager $groupManager,
		private readonly StaffInviteState $inviteState,
		private readonly StaffTeam $team,
	) {
	}//end __construct()

	/**
	 * The roles an invite may name: the game master group and the four staff roles.
	 *
	 * @return list<string> The group ids.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 */
	public static function roles(): array {
		return [AppIdentity::GM_GROUP, ...AppIdentity::STAFF_ROLES];
	}//end roles()

	/**
	 * Create an invite for one role and return it with its plain token, once.
	 *
	 * @param string $role The group the invite joins.
	 * @param int $days How many days the invite is valid, 1 to MAX_DAYS.
	 * @param int $maxUses How many people may join with it, 1 to MAX_USES.
	 * @param string $actingUid The game master creating it.
	 *
	 * @return array{invite: array<string, mixed>, token: string} The saved invite and the token.
	 *
	 * @throws InvalidArgumentException When the role, days or uses are out of range.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 */
	public function create(string $role, int $days, int $maxUses, string $actingUid): array {
		self::assertKnownRole(role: $role);
		self::assertDays(days: $days);
		self::assertMaxUses(maxUses: $maxUses);

		return $this->issue(invite: ['role' => $role, 'maxUses' => $maxUses, 'createdBy' => $actingUid], days: $days);
	}//end create()

	/**
	 * Store a new invite with a fresh token that ends after `$days`.
	 *
	 * @param array{role: string, maxUses: int, createdBy: string} $invite What the invite grants and who made it.
	 * @param int                                                  $days   How many days it is valid.
	 *
	 * @return array{invite: array<string, mixed>, token: string} The invite, and its token shown once.
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	private function issue(array $invite, int $days): array {
		$token = bin2hex(random_bytes(16));
		$saved = $this->objectFetcher->saveObjectWithAppAuthority(
			objectType: self::OBJECT_TYPE,
			data: [
				'role' => $invite['role'],
				'tokenHash' => self::hashToken(token: $token),
				'expiresAt' => $this->inviteState->now()->modify('+' . $days . ' days')->format(DateTimeInterface::ATOM),
				'maxUses' => $invite['maxUses'],
				'uses' => [],
				'createdBy' => $invite['createdBy'],
			]
		);

		return ['invite' => self::withoutHash(invite: $saved), 'token' => $token];
	}//end issue()

	/**
	 * The role and state of the invite a token opens, for the landing page.
	 *
	 * @param string $token The plain token from the link.
	 *
	 * @return array{role: string, state: string} The role and the state.
	 *
	 * @throws OutOfBoundsException When no invite has this token.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 */
	public function inspect(string $token): array {
		$invite = $this->findByToken(token: $token);
		return ['role' => (string)($invite['role'] ?? ''), 'state' => $this->state(invite: $invite)];
	}//end inspect()

	/**
	 * Join the role of a valid invite, and the app's user group, and record the use.
	 *
	 * A user already in the role joins nothing new and takes no use.
	 *
	 * @param string $token The plain token from the link.
	 * @param IUser $user The signed-in user.
	 *
	 * @return array{role: string, joined: bool} The role and whether the user was added now.
	 *
	 * @throws OutOfBoundsException When no invite has this token, or its group is missing.
	 * @throws DomainException When the invite is expired, used up or revoked; the message is the state.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 */
	public function redeem(string $token, IUser $user): array {
		$invite = $this->findByToken(token: $token);
		$group = $this->redeemableGroup(invite: $invite);
		$role  = (string)($invite['role'] ?? '');
		if ($group->inGroup($user) === true) {
			return ['role' => $role, 'joined' => false];
		}

		$this->recordUse(invite: $invite, user: $user);
		$group->addUser($user);
		$users = $this->groupManager->get(AppIdentity::USERS_GROUP);
		if ($users !== null && $users->inGroup($user) === false) {
			$users->addUser($user);
		}

		return ['role' => $role, 'joined' => true];
	}//end redeem()

	/**
	 * The group a valid invite adds to.
	 *
	 * @param array<string, mixed> $invite The invite.
	 *
	 * @return IGroup
	 *
	 * @throws DomainException     When the invite is not valid; the message is its state.
	 * @throws OutOfBoundsException When its role is not a staff role or the group is gone.
	 */
	private function redeemableGroup(array $invite): IGroup {
		$state = $this->state(invite: $invite);
		if ($state !== self::STATE_VALID) {
			throw new DomainException($state);
		}

		$role  = (string)($invite['role'] ?? '');
		$group = $this->groupManager->get($role);
		if (in_array($role, self::roles(), true) === false || $group === null) {
			throw new OutOfBoundsException('The role of this invite does not exist');
		}

		return $group;
	}//end redeemableGroup()

	/**
	 * Write the invite back with one more use by this user.
	 *
	 * @param array<string, mixed> $invite The invite.
	 * @param IUser                $user   Who redeemed it.
	 *
	 * @return void
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	private function recordUse(array $invite, IUser $user): void {
		$uses   = $this->inviteState->uses(invite: $invite);
		$uses[] = ['uid' => $user->getUID(), 'at' => $this->inviteState->now()->format(DateTimeInterface::ATOM)];
		$invite['uses'] = $uses;
		// The whole invite is written back, token hash included: a save replaces the object.
		$this->objectFetcher->saveObjectWithAppAuthority(
			objectType: self::OBJECT_TYPE,
			data: self::payload(invite: $invite),
			uuid: self::idOf(invite: $invite)
		);
	}//end recordUse()

	/**
	 * Revoke an invite: from now on it adds nobody.
	 *
	 * @param string $id The invite id.
	 * @param string $actingUid The game master revoking it.
	 *
	 * @return array<string, mixed> The revoked invite.
	 *
	 * @throws \Exception When the invite cannot be read.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	public function revoke(string $id, string $actingUid): array {
		$invite = $this->objectFetcher->getObjectOfType(objectType: self::OBJECT_TYPE, id: $id);
		if ($this->inviteState->isRevoked(invite: $invite) === true) {
			return self::withoutHash(invite: $invite);
		}

		$invite['revokedAt'] = $this->inviteState->now()->format(DateTimeInterface::ATOM);
		$invite['revokedBy'] = $actingUid;
		$saved = $this->objectFetcher->saveObjectWithAppAuthority(
			objectType: self::OBJECT_TYPE,
			data: self::payload(invite: $invite),
			uuid: self::idOf(invite: $invite)
		);

		return self::withoutHash(invite: $saved);
	}//end revoke()

	/**
	 * The members of each role and every invite with its state, for the Staff page.
	 *
	 * @return array{roles: list<array{role: string, members: list<array{uid: string, displayName: string}>}>, invites: list<array<string, mixed>>}
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	public function team(): array {
		$roles = [];
		foreach (self::roles() as $role) {
			$roles[] = ['role' => $role, 'members' => $this->team->members(role: $role)];
		}

		$invites = [];
		foreach ($this->objectFetcher->getObjects(objectType: self::OBJECT_TYPE) as $invite) {
			$invite = self::withoutHash(invite: $invite);
			$invite['state'] = $this->state(invite: $invite);
			$invites[] = $invite;
		}

		return ['roles' => $roles, 'invites' => $invites];
	}//end team()


	/**
	 * Remove a member from a role.
	 *
	 * A game master cannot remove themself from the game master group, so a
	 * team never locks itself out by accident.
	 *
	 * @param string $role The role group.
	 * @param string $uid The member to remove.
	 * @param string $actingUid The game master removing them.
	 *
	 * @return bool True when the member was in the role and is removed.
	 *
	 * @throws InvalidArgumentException When the role is not one of the invite roles.
	 * @throws DomainException When a game master removes themself from the game masters.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	public function removeMember(string $role, string $uid, string $actingUid): bool {
		self::assertKnownRole(role: $role);

		if ($role === AppIdentity::GM_GROUP && $uid === $actingUid) {
			throw new DomainException('You cannot remove yourself from the game masters');
		}

		return $this->team->removeFrom(role: $role, uid: $uid);
	}//end removeMember()


	/**
	 * Whether an invite still adds people: revoked first, then expired, then used up.
	 *
	 * @param array<string, mixed> $invite The invite.
	 *
	 * @return string One of the STATE_* values.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 * @spec openspec/specs/staff-roles/spec.md
	 */
	public function state(array $invite): string {
		return $this->inviteState->state(invite: $invite);
	}//end state()



	/**
	 * Refuse a role that is not a staff role.
	 *
	 * @param string $role The group id.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When it is not.
	 */
	private static function assertKnownRole(string $role): void {
		if (in_array($role, self::roles(), true) === false) {
			throw new InvalidArgumentException('Unknown role');
		}
	}//end assertKnownRole()

	/**
	 * Refuse a validity outside 1 to MAX_DAYS days.
	 *
	 * @param int $days The days.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When it is outside.
	 */
	private static function assertDays(int $days): void {
		if ($days < 1 || $days > self::MAX_DAYS) {
			throw new InvalidArgumentException('An invite is valid for 1 to ' . self::MAX_DAYS . ' days');
		}
	}//end assertDays()

	/**
	 * Refuse a use count outside 1 to MAX_USES.
	 *
	 * @param int $maxUses The uses.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When it is outside.
	 */
	private static function assertMaxUses(int $maxUses): void {
		if ($maxUses < 1 || $maxUses > self::MAX_USES) {
			throw new InvalidArgumentException('An invite has 1 to ' . self::MAX_USES . ' uses');
		}
	}//end assertMaxUses()

	/**
	 * The SHA-256 hash under which an invite's token is stored.
	 *
	 * @param string $token The plain token.
	 *
	 * @return string The hex hash.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 */
	public static function hashToken(string $token): string {
		return hash('sha256', $token);
	}//end hashToken()

	/**
	 * The invite a token opens.
	 *
	 * @param string $token The plain token: 32 hexadecimal characters.
	 *
	 * @return array<string, mixed> The invite.
	 *
	 * @throws OutOfBoundsException When the token is malformed or matches no invite.
	 */
	private function findByToken(string $token): array {
		if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
			throw new OutOfBoundsException('No such invite');
		}

		$hash = self::hashToken(token: $token);
		$found = $this->objectFetcher->getObjectsWithAppAuthority(objectType: self::OBJECT_TYPE, filters: ['tokenHash' => $hash], limit: 2);
		// The filter is matched again here, so a store that ignores it never opens another invite.
		foreach ($found as $invite) {
			if (self::hasHash(invite: $invite, hash: $hash) === true) {
				return $invite;
			}
		}

		throw new OutOfBoundsException('No such invite');
	}//end findByToken()

	/**
	 * Whether the invite carries this token hash.
	 *
	 * @param array<string, mixed> $invite The invite.
	 * @param string               $hash   The token's hash.
	 *
	 * @return bool
	 */
	private static function hasHash(array $invite, string $hash): bool {
		return is_string($invite['tokenHash'] ?? null) === true && hash_equals($hash, $invite['tokenHash']) === true;
	}//end hasHash()


	/**
	 * The id of an invite.
	 *
	 * @param array<string, mixed> $invite The invite.
	 *
	 * @return string The id.
	 */
	private static function idOf(array $invite): string {
		return (string)($invite['id'] ?? $invite['@self']['id'] ?? '');
	}//end idOf()

	/**
	 * The stored fields of an invite, without OpenRegister's metadata.
	 *
	 * @param array<string, mixed> $invite The invite as read.
	 *
	 * @return array<string, mixed> The fields to save.
	 */
	private static function payload(array $invite): array {
		unset($invite['@self'], $invite['id'], $invite['state']);
		return $invite;
	}//end payload()

	/**
	 * The invite without its token hash, which never leaves the service.
	 *
	 * @param array<string, mixed> $invite The invite.
	 *
	 * @return array<string, mixed> The invite.
	 */
	private static function withoutHash(array $invite): array {
		unset($invite['tokenHash']);
		return $invite;
	}//end withoutHash()

}//end class
