<?php

/**
 * Larpinq Registration Credit Requested
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Event
 * @package   OCA\Larpinq\Event
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Event;

/**
 * Larpinq asks shillinq for a credit of a cancelled, paid registration
 * (registration-cancel-transfer-refund REQ-RCT-003).
 *
 * @category Event
 * @package  OCA\Larpinq\Event
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationCreditRequested extends RegistrationSettlementRequested {

	/**
	 * What is asked for.
	 *
	 * @return string `credit`.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function getKind(): string {
		return 'credit';
	}//end getKind()
}//end class
