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

		$isLeader = ($faction['leaderOwnerUid'] ?? '') === $userId;
		$isOwner = ($character['ownerUid'] ?? '') === $userId;

		if ($old === null) {
			return $this->checkNewMembership(membership: $membership, isLeader: $isLeader, isOwner: $isOwner);
		}

		return $this->checkMembershipChange(membership: $membership, old: $old, isLeader: $isLeader, isOwner: $isOwner);
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
	 * A new membership: a player asks with their own character, the leader
	 * invites, and the leader places their own character as an active member.
	 *
	 * @param array<string, mixed> $membership The new membership.
	 * @param bool $isLeader Whether the caller leads the group.
	 * @param bool $isOwner Whether the caller owns the member character.
	 *
	 * @return array<string, string>|null The refusal, or null to allow.
	 */
	private function checkNewMembership(array $membership, bool $isLeader, bool $isOwner): ?array {
		$status = (string)($membership['status'] ?? 'requested');
		$isMember = (string)($membership['role'] ?? 'member') === 'member';

		// Who may create a membership in each status, and the refusal otherwise.
		$allowed = [
			'requested' => [$isOwner === true && $isMember === true, 'membership_own_character_only'],
			'invited' => [$isLeader === true && $isMember === true, 'membership_leader_only'],
			'active' => [$isLeader === true && $isOwner === true, 'membership_needs_invitation'],
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
	 * A change to a stored membership.
	 *
	 * @param array<string, mixed> $membership The membership as it will be saved.
	 * @param array<string, mixed> $old The stored membership.
	 * @param bool $isLeader Whether the caller leads the group.
	 * @param bool $isOwner Whether the caller owns the member character.
	 *
	 * @return array<string, string>|null The refusal, or null to allow.
	 */
	private function checkMembershipChange(array $membership, array $old, bool $isLeader, bool $isOwner): ?array {
		if (($membership['role'] ?? 'member') !== ($old['role'] ?? 'member') && $isLeader === false) {
			return $this->refuse(code: 'membership_leader_only');
		}

		$from = (string)($old['status'] ?? 'requested');
		$target = (string)($membership['status'] ?? $from);
		if ($from === $target) {
			return null;
		}

		$who = (self::TRANSITIONS[$from][$target] ?? null);
		if ($who === null) {
			return $this->refuse(code: 'membership_status_not_allowed');
		}

		if ($who === 'leader' && $isLeader === false) {
			return $this->refuse(code: 'membership_leader_only');
		}

		if ($who === 'owner' && $isOwner === false) {
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
	 * @param string $objectType The object type ('faction' or 'character').
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
	 * The UUID of a relation value, which OpenRegister may hand over as a
	 * string or as an object with an id.
	 *
	 * @param mixed $value The relation value.
	 *
	 * @return string The UUID, or ''.
	 */
	private function idOf(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? $value['uuid'] ?? '');
		}

		if (is_string($value) === false) {
			return '';
		}

		return $value;
	}//end idOf()

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
