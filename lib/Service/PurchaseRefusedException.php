<?php

/**
 * A purchase larpinq refuses.
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
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use RuntimeException;

/**
 * Raised when a character cannot buy an item; the message is the untranslated
 * reason, the status the HTTP status it answers with.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
 */
class PurchaseRefusedException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $message The untranslated reason.
	 * @param int    $status  The HTTP status (404 unknown, 409 not possible).
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	public function __construct(string $message, private readonly int $status) {
		parent::__construct(message: $message);
	}//end __construct()

	/**
	 * The HTTP status to answer with.
	 *
	 * @return int The status.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	public function getStatus(): int {
		return $this->status;
	}//end getStatus()
}//end class
