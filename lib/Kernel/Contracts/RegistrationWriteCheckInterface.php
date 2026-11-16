<?php

/**
 * Contract of RegistrationWriteCheck
 *
 * The public surface of RegistrationWriteCheck that other components call, so they depend
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
 * Contract of RegistrationWriteCheck.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface RegistrationWriteCheckInterface {

	/**
	 * Check a registration before it is stored.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed>|null $stored The stored registration, or null on create.
	 *
	 * @return array{refusal: string|null, changes: array<string, mixed>} The reason it is refused, or the fields to set.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function check(array $new, ?array $stored): array;
}//end interface
