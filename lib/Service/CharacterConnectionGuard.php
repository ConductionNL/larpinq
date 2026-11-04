<?php

/**
 * Decides whether a player may write a faction, a membership, a
 * relationship (characters-factions-and-relationships D3) or a build
 * (characters-multiple-builds).
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-connections/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * The rules OpenRegister's row rules cannot express, because they depend on
 * the status a write moves to and on who the caller is relative to two other
 * objects (the member's character and the group's leader).
 *
 * Every method answers for a player who is not a game master; the listener
 * lets game masters and system writes through before it asks. A faction or
 * character that cannot be read refuses the write: this is an authorization
 * check and does not fail open.
 *
 * @spec openspec/specs/character-connections/spec.md
 */
class CharacterConnectionGuard {
	use \OCA\Larpinq\Support\ReadsRelationId;

	/**
	 * Who may move a membership from one status to another: the group leader
	 * or the player who owns the member character.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const TRANSITIONS = [
		'requested' => ['active' => 'leader', 'declined' => 'leader', 'left' => 'owner'],
		'invited' => ['active' => 'owner', 'declined' => 'owner', 'removed' => 'leader'],
		'active' => ['left' => 'owner', 'removed' => 'leader'],
	];

	/**
	 * The field that names the owning account, per object type owns() answers for.
	 *
	 * @var array<string, string>
	 */
	private const OWNER_FIELDS = ['character' => 'ownerUid', 'player' => 'userUid'];

	/**
	 * The refusal messages, by code.
	 *
	 * @var array<string, string>
	 */
	private const MESSAGES = [
		'connection_unverifiable' => 'The faction or character of this write could not be read, so the write is refused.',
		'faction_game_masters_only' => 'Only game masters create factions and place characters in them. Players form groups.',
		'group_leader_own_character_only' => 'A group is led by one of your own characters.',
		'membership_own_character_only' => 'You can only ask to join with one of your own characters.',
		'membership_leader_only' => 'Only the leader of the group can do this.',
		'membership_member_only' => 'Only the player of the invited character can do this.',
		'membership_needs_invitation' => 'A character becomes an active member only when the leader accepts a request or the player accepts an invitation.',
		'membership_status_not_allowed' => 'A membership cannot move to this status from where it stands.',
		'membership_moved' => 'A membership stays with its faction and character. Leave and ask to join again instead.',
		'relationship_game_masters_only' => 'Only game masters record relationships that game masters alone can see. '
			. 'Choose who sees it: the players of both characters.',
		'relationship_own_character_only' => 'You can only record relationships from one of your own characters.',
		'build_own_character_only' => 'You can only make builds for your own characters.',
		'build_moved' => 'A build stays with its character. Make a new build for the other character instead.',
	];

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher Reads factions and characters through OpenRegister.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
	) {
	}//end __construct()

	/**
	 * A faction or group write by a player: only groups, led by their own character.
	 *
	 * @param array<string, mixed> $faction The faction as it will be saved.
	 * @param string $userId The player.
	 *
	 * @return array<string, string>|null The refusal, or null to allow.
	 *
	 * @spec openspec/specs/character-connections/spec.md
	 */
	public function checkFaction(array $faction, string $userId): ?array {
		if (($faction['kind'] ?? 'group') !== 'group') {
			return $this->refuse(code: 'faction_game_masters_only');
		}

		$leader = $this->read(objectType: 'character', id: ($faction['leader'] ?? null));
		if ($leader === null) {
			return $this->refuse(code: 'group_leader_own_character_only');
		}

		if (($leader['ownerUid'] ?? '') !== $userId) {
			return $this->refuse(code: 'group_leader_own_character_only');
		}

		return null;
	}//end checkFaction()

	/**
	 * A membership write by a player.
	 *
	 * @param array<string, mixed> $membership The membership as it will be saved.
	 * @param array<string, mixed>|null $old The stored membership, or null on a create.
	 * @param string $userId The player.
	 *
	 * @return array<string, string>|null The refusal, or null to allow.
	 *
	 * @spec openspec/specs/character-connections/spec.md
	 */
	public function checkMembership(array $membership, ?array $old, string $userId): ?array {
		if ($old !== null && $this->isMoved(membership: $membership, old: $old) === true) {
			return $this->refuse(code: 'membership_moved');
		}

		$faction = $this->read(objectType: 'faction', id: ($membership['faction'] ?? null));
		$character = $this->read(objectType: 'character', id: ($membership['character'] ?? null));
		if ($faction === null || $character === null) {
			return $this->refuse(code: 'connection_unverifiable');
		}

		if (($faction['kind'] ?? '') !== 'group') {
			return $this->refuse(code: 'faction_game_masters_only');
		}

		$caller = new MembershipCaller(
			isLeader: ($faction['leaderOwnerUid'] ?? '') === $userId,
			isOwner: ($character['ownerUid'] ?? '') === $userId
		);
		if ($old === null) {
			return $this->checkNewMembership(membership: $membership, caller: $caller);
		}

		return $this->checkMembershipChange(change: $this->changeOf(membership: $membership, old: $old), caller: $caller);
	}//end checkMembership()

	/**
	 * A relationship write by a player: from their own character, known to owners.
	 *
	 * @param array<string, mixed> $relationship The relationship as it will be saved.
	 * @param string $userId The player.
	 *
	 * @return array<string, string>|null The refusal, or null to allow.
	 *
	 * @spec openspec/specs/character-connections/spec.md
	 */
	public function checkRelationship(array $relationship, string $userId): ?array {
		if (($relationship['knownTo'] ?? 'gamemasters') !== 'owners') {
			return $this->refuse(code: 'relationship_game_masters_only');
		}

		$from = $this->read(objectType: 'character', id: ($relationship['from'] ?? null));
		if ($from === null) {
			return $this->refuse(code: 'connection_unverifiable');
		}

		if (($from['ownerUid'] ?? '') !== $userId) {
			return $this->refuse(code: 'relationship_own_character_only');
		}

		return null;
	}//end checkRelationship()

	/**
	 * A build write by a player: only for their own character, and a stored
	 * build stays with its character (characters-multiple-builds REQ-CMB-004).
	 *
	 * @param array<string, mixed> $build The build as it will be saved.
	 * @param array<string, mixed>|null $old The stored build, or null on a create.
	 * @param string $userId The player.
	 *
	 * @return array<string, string>|null The refusal, or null to allow.
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 */
	public function checkBuild(array $build, ?array $old, string $userId): ?array {
		if ($old !== null
			&& $this->idOf(value: ($build['character'] ?? null)) !== $this->idOf(value: ($old['character'] ?? null))
		) {
			return $this->refuse(code: 'build_moved');
		}

		$character = $this->read(objectType: 'character', id: ($build['character'] ?? null));
		if ($character === null) {
			return $this->refuse(code: 'connection_unverifiable');
		}

		if (($character['ownerUid'] ?? '') !== $userId) {
			return $this->refuse(code: 'build_own_character_only');
		}

		return null;
	}//end checkBuild()

	/**
	 * Whether the user owns a stored character or player, judged from the
	 * STORED object read as the user, never from a payload the user sent. A
	 * character is the user's when its `ownerUid` is theirs, a player when its
	 * `userUid` is. Gates the reads larpinq makes with its own authority for a
	 * player's own records (DECISIONS row 30).
	 *
	 * @param string $objectType 'character' or 'player'.
	 * @param mixed $id The object's id (a UUID, or an object carrying one).
	 * @param string $userId The user.
	 *
	 * @return bool True when the stored object is the user's.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 * @spec openspec/specs/events-players/spec.md
	 */
	public function owns(string $objectType, mixed $id, string $userId): bool {
		$field = (self::OWNER_FIELDS[$objectType] ?? '');

		return $userId !== '' && $field !== '' && (($this->read(objectType: $objectType, id: $id)[$field] ?? '') === $userId);
	}//end owns()

	/**
	 * A new membership: a player asks with their own character, the leader
	 * invites, and the leader places their own character as an active member.
	 *
	 * @param array<string, mixed> $membership The new membership.
	 * @param MembershipCaller $caller How the caller stands to the group and the character.
	 *
	 * @return array<string, string>|null The refusal, or null to allow.
	 */
	private function checkNewMembership(array $membership, MembershipCaller $caller): ?array {
		$status = (string)($membership['status'] ?? 'requested');
		$isMember = (string)($membership['role'] ?? 'member') === 'member';

		// Who may create a membership in each status, and the refusal otherwise.
		$allowed = [
			'requested' => [$caller->isOwner === true && $isMember === true, 'membership_own_character_only'],
			'invited' => [$caller->isLeader === true && $isMember === true, 'membership_leader_only'],
			'active' => [$caller->isLeader === true && $caller->isOwner === true, 'membership_needs_invitation'],
		];
		if (isset($allowed[$status]) === false) {
			return $this->refuse(code: 'membership_status_not_allowed');
		}

		if ($allowed[$status][0] === true) {
			return null;
		}

		return $this->refuse(code: $allowed[$status][1]);
	}//end checkNewMembership()

	/**
	 * What an update changes in a stored membership: the role, and the status from and to.
	 *
	 * @param array<string, mixed> $membership The membership as it will be saved.
	 * @param array<string, mixed> $old The stored membership.
	 *
	 * @return array{roleChanged: bool, from: string, target: string} The change.
	 */
	private function changeOf(array $membership, array $old): array {
		$from = (string)($old['status'] ?? 'requested');

		return [
			'roleChanged' => ($membership['role'] ?? 'member') !== ($old['role'] ?? 'member'),
			'from' => $from,
			'target' => (string)($membership['status'] ?? $from),
		];
	}//end changeOf()

	/**
	 * A change to a stored membership.
	 *
	 * @param array{roleChanged: bool, from: string, target: string} $change What the update changes.
	 * @param MembershipCaller $caller How the caller stands to the group and the character.
	 *
	 * @return array<string, string>|null The refusal, or null to allow.
	 */
	private function checkMembershipChange(array $change, MembershipCaller $caller): ?array {
		if ($change['roleChanged'] === true && $caller->isLeader === false) {
			return $this->refuse(code: 'membership_leader_only');
		}

		if ($change['from'] === $change['target']) {
			return null;
		}

		$who = (self::TRANSITIONS[$change['from']][$change['target']] ?? null);
		if ($who === null) {
			return $this->refuse(code: 'membership_status_not_allowed');
		}

		if ($who === 'leader' && $caller->isLeader === false) {
			return $this->refuse(code: 'membership_leader_only');
		}

		if ($who === 'owner' && $caller->isOwner === false) {
			return $this->refuse(code: 'membership_member_only');
		}

		return null;
	}//end checkMembershipChange()

	/**
	 * Whether an update points a membership at another faction or character.
	 *
	 * @param array<string, mixed> $membership The membership after the write.
	 * @param array<string, mixed> $old        The stored membership.
	 *
	 * @return bool True when the faction or the character changed.
	 */
	private function isMoved(array $membership, array $old): bool {
		foreach (['faction', 'character'] as $field) {
			if ($this->idOf(value: ($membership[$field] ?? null)) !== $this->idOf(value: ($old[$field] ?? null))) {
				return true;
			}
		}

		return false;
	}//end isMoved()

	/**
	 * Read one object by a relation value, or null when it cannot be read.
	 *
	 * @param string $objectType The object type ('faction', 'character' or 'player').
	 * @param mixed $id The relation value (a UUID, or an object carrying one).
	 *
	 * @return array<string, mixed>|null The object.
	 */
	private function read(string $objectType, mixed $id): ?array {
		$uuid = $this->idOf(value: $id);
		if ($uuid === '') {
			return null;
		}

		try {
			return $this->objectFetcher->getObject(objectType: $objectType, id: $uuid);
		} catch (\Throwable $e) {
			return null;
		}
	}//end read()

	/**
	 * The refusal payload for a code.
	 *
	 * @param string $code The refusal code.
	 *
	 * @return array{code: string, message: string} The payload.
	 */
	private function refuse(string $code): array {
		return ['code' => $code, 'message' => self::MESSAGES[$code]];
	}//end refuse()
}//end class
