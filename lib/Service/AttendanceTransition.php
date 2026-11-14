<?php

/**
 * Larpinq attendance transition
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git_id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Which attendance status may follow which, and the record a status stores.
 *
 * Pure: no storage, no clock. A check-out is allowed only from `checked-in`;
 * every other status from anything, as before check-out existed.
 *
 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
 */
final class AttendanceTransition {

	/**
	 * The status a participant leaves the site with.
	 */
	public const CHECKED_OUT = 'checked-out';

	/**
	 * Whether a status may be recorded over the current record.
	 *
	 * @param array<string,mixed> $prior The current record, or an empty array.
	 * @param string $status The status asked for.
	 *
	 * @return bool Whether the change is allowed.
	 *
	 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
	 */
	public function allows(array $prior, string $status): bool {
		return $status !== self::CHECKED_OUT || ($prior['status'] ?? 'registered') === 'checked-in';
	}//end allows()

	/**
	 * The full record to store for a status, with server-stamped provenance.
	 *
	 * A check-out keeps the check-in stamps and adds `checkedOutAt` and
	 * `checkedOutBy`. Every other status stamps the check-in fields anew and
	 * leaves the check-out fields out, which clears them: the save replaces
	 * the whole record. Nothing here comes from a request body.
	 *
	 * @param array{event: string, character: string} $ids The event and character UUIDs.
	 * @param string $status The attendance status (already validated).
	 * @param array{uid: string, at: string} $stamp The acting game master and the server time.
	 * @param array<string,mixed> $prior The current record, or an empty array.
	 *
	 * @return array<string,mixed> The record to store.
	 *
	 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
	 */
	public function payload(array $ids, string $status, array $stamp, array $prior): array {
		$payload = ['event' => $ids['event'], 'character' => $ids['character'], 'status' => $status];
		if ($status !== self::CHECKED_OUT) {
			return $payload + ['checkedInAt' => $stamp['at'], 'checkedInBy' => $stamp['uid']];
		}

		foreach (['checkedInAt', 'checkedInBy'] as $field) {
			if (is_string($prior[$field] ?? null) === true && $prior[$field] !== '') {
				$payload[$field] = $prior[$field];
			}
		}

		return $payload + ['checkedOutAt' => $stamp['at'], 'checkedOutBy' => $stamp['uid']];
	}//end payload()
}//end class
