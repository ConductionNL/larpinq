<?php

/**
 * A payment request larpinq cannot raise.
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
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use RuntimeException;

/**
 * Raised when a payment request is not raised; the message is the untranslated
 * reason a game master reads, the status the HTTP status it answers with.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class PaymentRequestRefusedException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $message The untranslated reason.
	 * @param int $status The HTTP status (403 not allowed, 404 unknown, 409 not now, 502 shillinq refused).
	 */
	public function __construct(string $message, private readonly int $status) {
		parent::__construct(message: $message);
	}//end __construct()

	/**
	 * The HTTP status to answer with.
	 *
	 * @return int The status.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function getStatus(): int {
		return $this->status;
	}//end getStatus()
}//end class
