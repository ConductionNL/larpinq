<?php

/**
 * Contract of PaymentFollowUp
 *
 * The public surface of PaymentFollowUp that other components call, so they depend
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

use DateTimeImmutable;

/**
 * Contract of PaymentFollowUp.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface PaymentFollowUpInterface {

	/**
	 * The daily pass over open registrations.
	 *
	 * @param DateTimeImmutable $now The moment of the run.
	 *
	 * @return array<string, int> How many were paid, reminded and expired.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function daily(DateTimeImmutable $now): array;

	/**
	 * Shillinq changed a payment request of a registration.
	 *
	 * @param array<string, mixed> $request The payment request, with its `id`.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function requestChanged(array $request): void;
}//end interface
