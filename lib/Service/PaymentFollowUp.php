<?php

/**
 * Larpinq Payment Follow-up
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use OCA\Larpinq\Kernel\Contracts\PaymentFollowUpInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * What happens to an open payment after it is asked for
 * (registration-payments-through-shillinq REQ-RPS-002, REQ-RPS-003,
 * REQ-RPS-004): shillinq's capture marks the registration paid; three days
 * before the pay-by date the player is reminded (the registration's
 * `payment-reminder` notification rule sends on `paymentReminderAt`); a day
 * after it, a request that is still not captured cancels the registration as
 * unpaid, which moves the waiting list up. Shillinq's own record decides: a
 * registration is never expired without reading its request first.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class PaymentFollowUp implements PaymentFollowUpInterface {

	/**
	 * Shillinq's states that mean the money came.
	 *
	 * @var array<int, string>
	 */
	private const CAPTURED = ['captured', 'captured_unapplied'];

	/**
	 * Shillinq's states of a request whose money went back: the registration
	 * shows the same word as its `settlement` (REQ-RCT-003).
	 *
	 * @var array<int, string>
	 */
	private const SETTLED = [RegistrationSettlement::REFUNDED, RegistrationSettlement::CREDITED];

	/**
	 * How shillinq marks a request settled from a bank statement line, and the
	 * payment method a registration then shows.
	 * @var string
	 */
	private const BANK_TRANSFER = 'bank-transfer';

	/**
	 * Days before the pay-by date the player is reminded.
	 *
	 * @var string
	 */
	private const REMIND_BEFORE = 'P3D';

	/**
	 * Days after the pay-by date an unpaid registration expires.
	 *
	 * @var string
	 */
	private const EXPIRE_AFTER = 'P1D';

	/**
	 * Open registrations one daily run reads (hydra ADR-058, bounded).
	 *
	 * @var integer
	 */
	private const PAGE = 500;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes the register.
	 * @param PaymentLeaf $leaf Shillinq's payment requests leaf.
	 * @param LoggerInterface $logger The logger.
	 * @param RegistrationInvoice $invoice The invoice shillinq issued on request.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly PaymentLeaf $leaf,
		private readonly LoggerInterface $logger,
		private readonly RegistrationInvoice $invoice,
	) {
	}//end __construct()

	/**
	 * Shillinq changed a payment request of a registration.
	 *
	 * @param array<string, mixed> $request The payment request, with its `id`.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-paid-cancellation-is-refunded-or-credited-through-shillinq-req-rct-003
	 */
	public function requestChanged(array $request): void {
		$requestId = (string)($request['id'] ?? '');
		if ($this->moneyBack(request: $request) === true) {
			return;
		}

		$captured = $this->isCaptured(request: $request);
		$hasInvoice = trim((string)($request['invoiceReference'] ?? '')) !== '';
		if ($requestId === '' || ($captured === false && $hasInvoice === false)) {
			return;
		}

		$rows = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['paymentRequestId' => $requestId], limit: 1);
		if ($rows === []) {
			return;
		}

		if ($captured === true && (string)($rows[0]['paymentState'] ?? '') !== RegistrationPaymentService::PAID) {
			$this->markPaid(registration: $rows[0], request: $request);
			return;
		}

		// An invoice shillinq issued later than the request (REQ-RPS-006) lands once.
		$invoice = $this->invoice->fields(registration: $rows[0], request: $request);
		if ($invoice !== []) {
			$this->save(registration: $rows[0], fields: $invoice);
		}
	}//end requestChanged()

	/**
	 * Shillinq refunded or credited a request: the registration it paid shows it once.
	 *
	 * @param array<string, mixed> $request The payment request, with its `id` and `state`.
	 *
	 * @return bool True when the request's money went back, so nothing else follows.
	 *
	 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-paid-cancellation-is-refunded-or-credited-through-shillinq-req-rct-003
	 */
	private function moneyBack(array $request): bool {
		$requestId = (string)($request['id'] ?? '');
		$state = (string)($request['state'] ?? '');
		if ($requestId === '' || in_array($state, self::SETTLED, true) === false) {
			return false;
		}

		$rows = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['paymentRequestId' => $requestId], limit: 1);
		if ($rows !== [] && (string)($rows[0]['settlement'] ?? '') !== $state) {
			$this->save(registration: $rows[0], fields: ['settlement' => $state]);
		}

		return true;
	}//end moneyBack()

	/**
	 * The daily pass over open registrations.
	 *
	 * @param DateTimeImmutable $now The moment of the run.
	 *
	 * @return array<string, int> How many were paid, reminded and expired.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function daily(DateTimeImmutable $now): array {
		$counts = ['paid' => 0, 'reminded' => 0, 'expired' => 0, 'none' => 0];
		$filters = ['paymentState' => RegistrationPaymentService::OPEN];
		$open = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: $filters, limit: self::PAGE);
		foreach ($open as $registration) {
			try {
				$counts[$this->followUp(registration: $registration, now: $now)]++;
			} catch (Throwable $e) {
				$context = ['id' => (string)($registration['id'] ?? ''), 'exception' => $e];
				$this->logger->error('Larpinq: the payment of registration {id} was not followed up.', $context);
			}
		}

		return $counts;
	}//end daily()

	/**
	 * One open registration: paid, expired, reminded or nothing yet.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param DateTimeImmutable $now The moment of the run.
	 *
	 * @return string What happened.
	 */
	private function followUp(array $registration, DateTimeImmutable $now): string {
		$request = $this->requestOf(registration: $registration);
		if ($request !== null && $this->isCaptured(request: $request) === true) {
			$this->markPaid(registration: $registration, request: $request);
			return 'paid';
		}

		$payBy = $this->moment(value: $registration['payBy'] ?? null);
		if ($payBy === null) {
			return 'none';
		}

		if ($request !== null && $now >= $payBy->add(new DateInterval(self::EXPIRE_AFTER))) {
			$expired = ['status' => 'cancelled', 'cancelReason' => 'unpaid', 'paymentState' => RegistrationPaymentService::EXPIRED];
			$this->save(registration: $registration, fields: $expired);
			return 'expired';
		}

		if ((string)($registration['paymentReminderAt'] ?? '') === '' && $now >= $payBy->sub(new DateInterval(self::REMIND_BEFORE))) {
			$this->save(registration: $registration, fields: ['paymentReminderAt' => $now->format(DateTimeInterface::ATOM)]);
			return 'reminded';
		}

		return 'none';
	}//end followUp()

	/**
	 * The registration's request as shillinq holds it, or null when it cannot be read.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return array<string, mixed>|null The request.
	 */
	private function requestOf(array $registration): ?array {
		$requestId = (string)($registration['paymentRequestId'] ?? '');
		if ($requestId === '') {
			return null;
		}

		foreach ($this->leaf->requests(registrationId: (string)($registration['id'] ?? '')) as $request) {
			if ((string)($request['id'] ?? '') === $requestId) {
				return $request;
			}
		}

		return null;
	}//end requestOf()

	/**
	 * Mark a registration paid, keeping the payment link shillinq has and,
	 * for a request a bank statement line settled, the method bank transfer.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array<string, mixed> $request The captured request.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md#requirement-a-game-master-uploads-a-bank-statement-to-match-transfers-req-rps-007
	 * @spec openspec/specs/event-registration/spec.md#requirement-invoices-and-receipts-come-from-shillinq-req-rps-006
	 */
	private function markPaid(array $registration, array $request): void {
		$fields = ['paymentState' => RegistrationPaymentService::PAID];
		if ((string)($registration['paymentLink'] ?? '') === '' && (string)($request['paymentLink'] ?? '') !== '') {
			$fields['paymentLink'] = (string)$request['paymentLink'];
		}

		// Shillinq stamps `settledVia` when a bank statement line quoting the
		// reference settled the request (REQ-RPS-007).
		if ((string)($request['settledVia'] ?? '') === self::BANK_TRANSFER) {
			$fields['paymentMethod'] = self::BANK_TRANSFER;
		}

		$fields += $this->invoice->fields(registration: $registration, request: $request);

		$this->save(registration: $registration, fields: $fields);
	}//end markPaid()

	/**
	 * Whether shillinq reports the money as received.
	 *
	 * @param array<string, mixed> $request The request.
	 *
	 * @return bool True when captured.
	 */
	private function isCaptured(array $request): bool {
		return in_array((string)($request['state'] ?? ''), self::CAPTURED, true) === true;
	}//end isCaptured()

	/**
	 * A stored moment, or null when it is not one.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return DateTimeImmutable|null The moment.
	 */
	private function moment(mixed $value): ?DateTimeImmutable {
		if (is_string($value) === false || $value === '') {
			return null;
		}

		try {
			return new DateTimeImmutable($value);
		} catch (Throwable $e) {
			return null;
		}
	}//end moment()

	/**
	 * Write fields onto a registration with the app's authority.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array<string, mixed> $fields The fields.
	 *
	 * @return void
	 */
	private function save(array $registration, array $fields): void {
		$this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: $fields, uuid: (string)($registration['id'] ?? ''));
	}//end save()
}//end class
