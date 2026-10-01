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
class PaymentFollowUp {

	/**
	 * Shillinq's states that mean the money came.
	 *
	 * @var array<int, string>
	 */
	private const CAPTURED = ['captured', 'captured_unapplied'];

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
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly PaymentLeaf $leaf,
		private readonly LoggerInterface $logger,
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
	 */
	public function requestChanged(array $request): void {
		$requestId = (string)($request['id'] ?? '');
		if ($requestId === '' || $this->isCaptured(request: $request) === false) {
			return;
		}

		$rows = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['paymentRequestId' => $requestId], limit: 1);
		if ($rows === [] || (string)($rows[0]['paymentState'] ?? '') === RegistrationPaymentService::PAID) {
			return;
		}

		$this->markPaid(registration: $rows[0], request: $request);
	}//end requestChanged()

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
		$open = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['paymentState' => RegistrationPaymentService::OPEN], limit: self::PAGE);
		foreach ($open as $registration) {
			try {
				$counts[$this->followUp(registration: $registration, now: $now)]++;
			} catch (Throwable $e) {
				$this->logger->error('Larpinq: the payment of registration {id} was not followed up.', ['id' => (string)($registration['id'] ?? ''), 'exception' => $e]);
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
			$this->save(registration: $registration, fields: ['status' => 'cancelled', 'cancelReason' => 'unpaid', 'paymentState' => RegistrationPaymentService::EXPIRED]);
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
	 * Mark a registration paid, keeping the payment link shillinq has.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array<string, mixed> $request The captured request.
	 *
	 * @return void
	 */
	private function markPaid(array $registration, array $request): void {
		$fields = ['paymentState' => RegistrationPaymentService::PAID];
		if ((string)($registration['paymentLink'] ?? '') === '' && (string)($request['paymentLink'] ?? '') !== '') {
			$fields['paymentLink'] = (string)$request['paymentLink'];
		}

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
