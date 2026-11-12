<?php

/**
 * CastLookup for Larpinq
 *
 * The players and attendance records of one event, indexed for building the
 * roster rows and run-sheet cast entries.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Players by id and attendance records by character id, for one event.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */
final class CastLookup {
	/**
	 * Constructor for CastLookup.
	 *
	 * @param array<string,array<string,mixed>> $players Players indexed by id.
	 * @param array<string,array<string,mixed>> $attendance Attendance records indexed by character id.
	 */
	public function __construct(
		public readonly array $players,
		public readonly array $attendance,
	) {
	}//end __construct()

	/**
	 * The attendance record of a character, or an empty record.
	 *
	 * @param array<string,mixed> $character The character object.
	 *
	 * @return array<string,mixed> The record.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	public function attendanceOf(array $character): array {
		return $this->attendance[(string)($character['id'] ?? '')] ?? [];
	}//end attendanceOf()
}//end class
