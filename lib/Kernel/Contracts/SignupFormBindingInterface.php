<?php

/**
 * Contract of SignupFormBinding
 *
 * The public surface of SignupFormBinding that other components call, so they depend
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

use RuntimeException;

/**
 * Contract of SignupFormBinding.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface SignupFormBindingInterface {

	/**
	 * Whether a write changes the event's binding: the form or its mapping.
	 *
	 * @param array<string, mixed> $candidate The event as it will be stored.
	 * @param array<string, mixed> $old       The event as stored now; empty on create.
	 *
	 * @return bool True when the form or the mapping changed and a form is bound.
	 *
	 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-binding-must-be-valid-against-the-registration-schema-when-it-is-made
	 */
	public function bindingChanged(array $candidate, array $old): bool;

	/**
	 * Every problem with the event's binding, judged by OpenRegister.
	 *
	 * @param array<string, mixed> $event The event as it will be stored.
	 *
	 * @return array<int, array<string, mixed>> The findings; empty when the binding is valid.
	 *
	 * @throws RuntimeException When the binding cannot be judged (no validator, no registration schema).
	 *
	 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-binding-must-be-valid-against-the-registration-schema-when-it-is-made
	 */
	public function findings(array $event): array;
}//end interface
