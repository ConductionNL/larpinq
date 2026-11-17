<?php

/**
 * Contract of CharacterConnectionGuard
 *
 * The public surface of CharacterConnectionGuard that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Larpinq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Kernel\Contracts;

/**
 * Contract of CharacterConnectionGuard.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface CharacterConnectionGuardInterface {

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
	public function checkBuild(array $build, ?array $old, string $userId): ?array;

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
	public function checkFaction(array $faction, string $userId): ?array;

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
	public function checkMembership(array $membership, ?array $old, string $userId): ?array;

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
	public function checkRelationship(array $relationship, string $userId): ?array;

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
	public function owns(string $objectType, mixed $id, string $userId): bool;
}//end interface
