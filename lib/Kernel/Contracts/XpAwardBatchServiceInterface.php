<?php

/**
 * Contract of XpAwardBatchService
 *
 * The public surface of XpAwardBatchService that other components call, so they depend
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
 * Contract of XpAwardBatchService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface XpAwardBatchServiceInterface {

	/**
	 * Create one award per row. Each row stands or falls on its own: a row
	 * for a character off the roster, without a positive amount, or for a
	 * character who already has an award for the event (unless it is marked
	 * extra with a reason) is refused, and the others are still created.
	 *
	 * @param string $eventId The event UUID.
	 * @param array<int,mixed> $rows The rows: {character, amount, reason?, extra?}.
	 * @param string $actingUid The game master making the awards.
	 *
	 * @return array{created: array<int,array<string,mixed>>, refused: array<int,array{character: string, reason: string}>} The outcome.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public function award(string $eventId, array $rows, string $actingUid): array;

	/**
	 * The award roster: every participant with attendance, existing awards
	 * and whether the row starts ticked (checked in and not yet awarded).
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return array{rows: array<int,array<string,mixed>>, attendanceAvailable: bool} The roster.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public function awardRoster(string $eventId): array;
}//end interface
