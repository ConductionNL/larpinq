<?php

/**
 * Larpinq Registration Settlement
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

use DateTimeInterface;
use OCA\Larpinq\Event\RegistrationCreditRequested;
use OCA\Larpinq\Event\RegistrationRefundRequested;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The money of a cancelled, paid registration (registration-cancel-transfer-refund
 * REQ-RCT-003). Whichever way a paid registration becomes cancelled (the
 * participant, the booker, or a game master on the object page), larpinq asks
 * shillinq for a refund or credit as the event's policy, or the player's choice,
 * says, and records it on the registration. Shillinq holds refunds and credit
 * balances (hydra ADR-107); the ask is a typed event (hydra ADR-041).
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationSettlement {

	private const CANCELLED = 'cancelled';

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the event, writes the registration.
	 * @param CancellationPolicy $policy What a paid cancellation gets.
	 * @param PaymentRequestBuilder $builder Amount, currency, debtor and the clock.
	 * @param PaymentLeaf $leaf The paid request as shillinq holds it.
	 * @param IEventDispatcher $dispatcher Sends the request to shillinq.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly CancellationPolicy $policy,
		private readonly PaymentRequestBuilder $builder,
		private readonly PaymentLeaf $leaf,
		private readonly IEventDispatcher $dispatcher,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * After a registration is stored: settle it when it just became cancelled while paid.
	 *
	 * @param array<string, mixed> $new The stored registration.
	 * @param array<string, mixed>|null $old The registration before, or null on create.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function afterWrite(array $new, ?array $old): void {
		if ((string)($new['status'] ?? '') !== self::CANCELLED || (string)($old['status'] ?? '') === self::CANCELLED) {
			return;
		}

		if ((string)($new['paymentState'] ?? '') !== RegistrationPaymentService::PAID || (string)($new['settlement'] ?? '') !== '') {
			return;
		}

		$id = (string)($new['id'] ?? '');
		try {
			$event = $this->fetcher->getObject(objectType: 'event', id: (string)($new['event'] ?? ''));
		} catch (Throwable $e) {
			$this->logger->error('Larpinq: the cancelled, paid registration {id} was not settled: its event could not be read.', ['id' => $id, 'exception' => $e]);
			return;
		}

		$outcome = $this->policy->paidOutcome(event: $event, choice: (string)($new['settlementChoice'] ?? ''));
		$fields = ['settlement' => CancellationPolicy::NONE];
		if ($outcome !== CancellationPolicy::NONE) {
			$this->dispatcher->dispatchTyped($this->command(registration: $new, outcome: $outcome));
			$fields = [
				'settlement' => $outcome . '-requested',
				'settlementRequestedAt' => $this->builder->now()->format(DateTimeInterface::ATOM),
			];
		}

		$this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: $fields, uuid: $id);
	}//end afterWrite()

	/**
	 * The refund or credit request for a registration.
	 *
	 * @param array<string, mixed> $registration The cancelled, paid registration.
	 * @param string $outcome `refund` or `credit`.
	 *
	 * @return RegistrationRefundRequested|RegistrationCreditRequested The command.
	 */
	private function command(array $registration, string $outcome): RegistrationRefundRequested|RegistrationCreditRequested {
		$id = (string)($registration['id'] ?? '');
		$requestId = (string)($registration['paymentRequestId'] ?? '');
		$args = [
			'registrationId' => $id,
			'paymentRequestId' => $requestId,
			'eventId' => (string)($registration['event'] ?? ''),
			'playerId' => (string)($registration['player'] ?? ''),
			'debtor' => $this->builder->debtor(registration: $registration),
			'amount' => $this->paidAmount(registration: $registration, requestId: $requestId),
			'currency' => $this->builder->currency(registration: $registration),
			'subject' => $this->leaf->subject(registrationId: $id),
		];
		if ($outcome === CancellationPolicy::CREDIT) {
			return new RegistrationCreditRequested(...$args);
		}

		return new RegistrationRefundRequested(...$args);
	}//end command()

	/**
	 * The amount of the paid request as shillinq holds it, else the registration's price lines.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param string $requestId The paid request.
	 *
	 * @return float The amount in currency units.
	 */
	private function paidAmount(array $registration, string $requestId): float {
		foreach ($this->leaf->requests(registrationId: (string)($registration['id'] ?? '')) as $request) {
			if ((string)($request['id'] ?? '') === $requestId && is_numeric($request['amount'] ?? null) === true) {
				return round((float)$request['amount'], 2);
			}
		}

		return round($this->builder->cents(registration: $registration) / 100, 2);
	}//end paidAmount()
}//end class
