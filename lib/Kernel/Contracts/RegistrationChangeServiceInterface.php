<?php

/**
 * Contract of RegistrationChangeService
 *
 * The public surface of RegistrationChangeService that other components call, so they depend
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
 * Contract of RegistrationChangeService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface RegistrationChangeServiceInterface {

	/**
	 * Cancel one registration. Its place goes to the waiting list, and a paid
	 * one is refunded or credited.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid Who cancels.
	 * @param string $choice `refund` or `credit` when the policy lets the player choose, else ''.
	 *
	 * @return array<string, mixed> The stored registration.
	 *
	 * @throws \OCA\Larpinq\Service\RegistrationChangeRefusedException When the caller may not cancel it now.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function cancel(string $registrationId, string $actingUid, string $choice = ''): array;

	/**
	 * Add a participant to the booking of the caller's own registration: a new
	 * player with a name, or a player the caller booked before. The participant
	 * gets a registration of their own, decided like any other sign-up.
	 *
	 * @param string $registrationId The caller's own registration.
	 * @param string $actingUid Who books.
	 * @param string $name The new participant's name, or ''.
	 * @param string $playerId A player the caller booked before, or ''.
	 *
	 * @return array<string, mixed> The participant's registration.
	 *
	 * @throws \OCA\Larpinq\Service\RegistrationChangeRefusedException When the caller may not add to this booking.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function addParticipant(string $registrationId, string $actingUid, string $name, string $playerId = ''): array;

	/**
	 * What the caller may change on a registration now, for the page.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid The caller.
	 *
	 * @return array<string, mixed> The answers.
	 *
	 * @throws \OCA\Larpinq\Service\RegistrationChangeRefusedException When the registration does not exist.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function whatMayChange(string $registrationId, string $actingUid): array;
}//end interface
