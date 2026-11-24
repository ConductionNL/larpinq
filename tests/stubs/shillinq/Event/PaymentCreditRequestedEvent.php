<?php

/**
 * Payment Credit Requested Event
 *
 * Another app asks shillinq to keep the money of a settled request as credit for the payer's next request (REQ-ORC-003).
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
 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-003)
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Shillinq\Event;

/**
 * The credit command.
 *
 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-003)
 */
final class PaymentCreditRequestedEvent extends PaymentSettlementCommandEvent {
}//end class
