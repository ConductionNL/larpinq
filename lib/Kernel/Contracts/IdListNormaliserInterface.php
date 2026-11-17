<?php

/**
 * Contract of IdListNormaliser
 *
 * The public surface of IdListNormaliser that other components call, so they depend
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
 * Contract of IdListNormaliser.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface IdListNormaliserInterface {

	/**
	 * Normalise a value to a list of string ids.
	 *
	 * Tolerates arrays of strings, arrays of {id} objects, or a single value.
	 * Null and empty-string entries are dropped rather than becoming ''.
	 *
	 * @param mixed $value The raw value.
	 *
	 * @return array<int,string> The id list.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	public function normalise(mixed $value): array;
}//end interface
