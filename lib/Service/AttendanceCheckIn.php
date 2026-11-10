<?php

/**
 * AttendanceCheckIn for Larpinq
 *
 * One attendance mark a game master sets at the door: which character of
 * which event, the status, and who set it.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * An attendance mark to record for a participant.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */
final class AttendanceCheckIn {
	/**
	 * Constructor for AttendanceCheckIn.
	 *
	 * @param string $eventId The event UUID.
	 * @param string $characterId The character UUID.
	 * @param string $status The attendance status (already validated).
	 * @param string $actingUid The acting game master's uid.
	 */
	public function __construct(
		public readonly string $eventId,
		public readonly string $characterId,
		public readonly string $status,
		public readonly string $actingUid,
	) {
	}//end __construct()
}//end class
