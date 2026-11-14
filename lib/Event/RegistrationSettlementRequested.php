<?php

/**
 * Larpinq Registration Settlement Requested
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

use OCP\EventDispatcher\Event;

/**
 * The money of a cancelled, paid registration goes back: a command to shillinq
 * (hydra ADR-041, ADR-107), which holds refunds and credit balances. Larpinq
 * never holds a balance itself. The debtor, amount and currency are in
 * shillinq's PaymentRequest shape; the subject is the paid request's subject.
 *
 * @category Event
 * @package  OCA\Larpinq\Event
 *
 * @spec openspec/specs/event-registration/spec.md
 */
abstract class RegistrationSettlementRequested extends Event {

	/**
	 * Constructor.
	 *
	 * @param string $registrationId The cancelled registration.
	 * @param string $paymentRequestId Shillinq's paid payment request.
	 * @param string $eventId The larp event.
	 * @param string $playerId The player whose registration it was.
	 * @param array{name: string, email?: string} $debtor Who paid, in shillinq's debtor shape.
	 * @param float $amount The paid amount, in currency units.
	 * @param string $currency ISO 4217 currency code.
	 * @param array<string, string> $subject The paid request's subject (register, schema, id).
	 */
	public function __construct(
		private readonly string $registrationId,
		private readonly string $paymentRequestId,
		private readonly string $eventId,
		private readonly string $playerId,
		private readonly array $debtor,
		private readonly float $amount,
		private readonly string $currency,
		private readonly array $subject,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * What is asked for: `refund` or `credit`.
	 *
	 * @return string The kind.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	abstract public function getKind(): string;

	/**
	 * The cancelled registration.
	 *
	 * @return string The uuid.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function getRegistrationId(): string {
		return $this->registrationId;
	}//end getRegistrationId()

	/**
	 * Shillinq's paid payment request.
	 *
	 * @return string The uuid.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function getPaymentRequestId(): string {
		return $this->paymentRequestId;
	}//end getPaymentRequestId()

	/**
	 * The larp event.
	 *
	 * @return string The uuid.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function getEventId(): string {
		return $this->eventId;
	}//end getEventId()

	/**
	 * The player whose registration it was.
	 *
	 * @return string The uuid.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function getPlayerId(): string {
		return $this->playerId;
	}//end getPlayerId()

	/**
	 * Who paid.
	 *
	 * @return array{name: string, email?: string} The debtor.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function getDebtor(): array {
		return $this->debtor;
	}//end getDebtor()

	/**
	 * The paid amount.
	 *
	 * @return float The amount in currency units.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function getAmount(): float {
		return $this->amount;
	}//end getAmount()

	/**
	 * The currency.
	 *
	 * @return string ISO 4217 code.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function getCurrency(): string {
		return $this->currency;
	}//end getCurrency()

	/**
	 * The whole command, as a listener in another app reads it.
	 *
	 * @return array<string, mixed> The payload.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function toArray(): array {
		return [
			'kind' => $this->getKind(),
			'registrationId' => $this->registrationId,
			'paymentRequestId' => $this->paymentRequestId,
			'eventId' => $this->eventId,
			'playerId' => $this->playerId,
			'debtor' => $this->debtor,
			'amount' => $this->amount,
			'currency' => $this->currency,
			'subject' => $this->subject,
		];
	}//end toArray()
}//end class
