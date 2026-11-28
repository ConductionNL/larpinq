<?php

/**
 * Why a rejected character cannot be submitted again.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use RuntimeException;

/**
 * A refused resubmission, with the HTTP status the controller answers.
 *
 * @spec openspec/specs/character-management/spec.md
 */
class ResubmissionRefusedException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $message The reason, in English.
	 * @param int    $status  The HTTP status to answer.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function __construct(string $message, private readonly int $status) {
		parent::__construct(message: $message);
	}//end __construct()

	/**
	 * The HTTP status to answer.
	 *
	 * @return int The status.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function getStatus(): int {
		return $this->status;
	}//end getStatus()
}//end class
