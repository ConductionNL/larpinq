<?php

/**
 * Contract of EventCareRecords
 *
 * The public surface of EventCareRecords that other components call, so they depend
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
 * Contract of EventCareRecords.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface EventCareRecordsInterface {

	/**
	 * The care fields copied from the player.
	 *
	 * @var array<int, string>
	 */
	public const FIELDS = ['allergies', 'medicalNotes', 'dietaryNeeds', 'emergencyContactName', 'emergencyContactPhone'];

	/**
	 * Bring the copies of a player's accepted registrations up to date; events that started are left alone.
	 *
	 * @param string $playerId The player whose details changed.
	 *
	 * @return int How many copies were written.
	 *
	 * @spec openspec/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
	 */
	public function refreshForPlayer(string $playerId): int;
}//end interface
