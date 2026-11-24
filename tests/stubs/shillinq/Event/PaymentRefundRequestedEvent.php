<?php

/**
 * Payment Refund Requested Event
 *
 * Another app asks shillinq to give the money of a settled request back to the payer. Finance approves and pays it (REQ-ORC-002).
 *
 * @category Event
 * @package  OCA\Shillinq\Event
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

// Test stub: shillinq's lib/Event/PaymentRefundRequestedEvent.php at 3fb5d8a, verbatim but for its @spec
// tags (they name shillinq specs). Loaded only by tests/bootstrap.php when shillinq is absent.

declare(strict_types=1);

namespace OCA\Shillinq\Event;

/**
 * The refund command.
 *
 */
final class PaymentRefundRequestedEvent extends PaymentSettlementCommandEvent {
}//end class
