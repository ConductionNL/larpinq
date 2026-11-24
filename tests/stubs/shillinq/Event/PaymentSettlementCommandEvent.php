<?php

/**
 * Payment Settlement Command Event
 *
 * The shared shape of the two commands another app sends shillinq about a
 * paid request on one of its objects: give the money back (a refund) or keep
 * it for the debtor's next request (credit). The sending app dispatches the
 * event with `IEventDispatcher::dispatchTyped()` and reads the answer from the
 * same object: `isHandled()` with `getResult()`, or `getError()` (ADR-041).
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

use OCP\EventDispatcher\Event;

/**
 * A command about one settled payment request, answered in place.
 *
 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
 */
abstract class PaymentSettlementCommandEvent extends Event {

	/**
	 * The version of the answer's shape.
	 *
	 * @var int
	 */
	public const CONTRACT_VERSION = 1;

	/**
	 * The answer, once a listener accepted the command.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $result = null;

	/**
	 * Why the command was refused, once a listener refused it.
	 *
	 * @var string|null
	 */
	private ?string $error = null;

	/**
	 * Constructor.
	 *
	 * @param string $sourceApp The app that asks, which must own the request's subject.
	 * @param string $paymentRequestId The settled PaymentRequest's uuid.
	 * @param string $reason Why, in the asking app's words.
	 * @param string $correlationId The asking app's own id for this command.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly string $sourceApp,
		private readonly string $paymentRequestId,
		private readonly string $reason = '',
		private readonly string $correlationId = '',
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * The app that asks.
	 *
	 * @return string The app id.
	 *
	 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
	 */
	public function getSourceApp(): string {
		return $this->sourceApp;
	}//end getSourceApp()

	/**
	 * The request the command is about.
	 *
	 * @return string The PaymentRequest uuid.
	 *
	 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
	 */
	public function getPaymentRequestId(): string {
		return $this->paymentRequestId;
	}//end getPaymentRequestId()

	/**
	 * Why, in the asking app's words.
	 *
	 * @return string The reason.
	 *
	 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
	 */
	public function getReason(): string {
		return $this->reason;
	}//end getReason()

	/**
	 * The asking app's own id for this command.
	 *
	 * @return string The correlation id.
	 *
	 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
	 */
	public function getCorrelationId(): string {
		return $this->correlationId;
	}//end getCorrelationId()

	/**
	 * Accept the command and answer with the request's new state.
	 *
	 * @param string $state The request's state after the command.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
	 */
	public function accept(string $state): void {
		$this->error = null;
		$this->result = [
			'contractVersion' => self::CONTRACT_VERSION,
			'paymentRequestId' => $this->paymentRequestId,
			'state' => $state,
		];
	}//end accept()

	/**
	 * Refuse the command with a reason.
	 *
	 * @param string $error Why.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
	 */
	public function refuse(string $error): void {
		$this->result = null;
		$this->error = $error;
	}//end refuse()

	/**
	 * Whether a listener accepted the command.
	 *
	 * @return bool True when accepted.
	 *
	 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
	 */
	public function isHandled(): bool {
		return $this->result !== null;
	}//end isHandled()

	/**
	 * The answer: contractVersion, paymentRequestId and state.
	 *
	 * @return array<string, mixed>|null The answer, or null when not accepted.
	 *
	 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
	 */
	public function getResult(): ?array {
		return $this->result;
	}//end getResult()

	/**
	 * Why the command was refused.
	 *
	 * @return string|null The reason, or null when not refused.
	 *
	 * @spec openspec/changes/archive/2026-11-24-receivables-object-request-refund-and-credit/specs/object-payment-requests/spec.md (REQ-ORC-001)
	 */
	public function getError(): ?string {
		return $this->error;
	}//end getError()
}//end class
