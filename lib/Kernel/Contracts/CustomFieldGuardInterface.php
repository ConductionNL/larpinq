<?php

/**
 * Contract of CustomFieldGuard
 *
 * The public surface of CustomFieldGuard that other components call, so they depend
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
 * Contract of CustomFieldGuard.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface CustomFieldGuardInterface {

	/**
	 * The veto payload for a character write, or null when it may pass.
	 *
	 * @param array<string, mixed> $candidate The character as it will be saved.
	 * @param array<string, mixed> $old The character as it is stored (empty on create).
	 *
	 * @return array<string, mixed>|null `{code, message, fields: {key: message}}`, or null.
	 *
	 * @spec openspec/specs/character-custom-fields/spec.md
	 */
	public function check(array $candidate, array $old): ?array;
}//end interface
