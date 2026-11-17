<?php

/**
 * Contract of CheckinByCode
 *
 * The public surface of CheckinByCode that other components call, so they depend
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
 * Contract of CheckinByCode.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface CheckinByCodeInterface {

	/**
	 * Check in the participant a code belongs to.
	 *
	 * @param string $eventId   The event at whose door the code is used.
	 * @param string $code      The code as typed or scanned.
	 * @param string $actingUid The game master checking in.
	 *
	 * @return array{0: int, 1: array<string, mixed>} The HTTP status and the answer.
	 *
	 * @spec openspec/changes/events-qr-checkin/tasks.md#2.1
	 */
	public function checkIn(string $eventId, string $code, string $actingUid): array;
}//end interface
