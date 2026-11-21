<?php

/**
 * Contract of RegistrationPaymentService
 *
 * The public surface of RegistrationPaymentService that other components call, so they depend
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
 * Contract of RegistrationPaymentService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface RegistrationPaymentServiceInterface {

	/**
	 * After a registration is stored: when it has just become accepted on an
	 * event that takes payment, ask for the payment or mark it as waiting.
	 *
	 * @param array<string, mixed> $new The stored registration.
	 * @param array<string, mixed>|null $old The registration before, or null on create.
	 * @param string $actingUid Who made the change, empty for the system.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function afterWrite(array $new, ?array $old, string $actingUid): void;

	/**
	 * Whether a user is a game master (the group, or a Nextcloud admin).
	 *
	 * @param string $uid The user id, empty for the system.
	 *
	 * @return bool True for a game master.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function isGameMaster(string $uid): bool;

	/**
	 * A game master's "Request payment": raise the request of an accepted registration that waits.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid The signed-in user.
	 *
	 * @return array<string, mixed> The registration as stored.
	 *
	 * @throws \OCA\Larpinq\Service\PaymentRequestRefusedException With the reason and status when it is not raised.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function request(string $registrationId, string $actingUid): array;
}//end interface
