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
 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Shillinq\Event;

/**
 * The refund command.
 *
 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
 */
final class PaymentRefundRequestedEvent extends PaymentSettlementCommandEvent {
}//end class
