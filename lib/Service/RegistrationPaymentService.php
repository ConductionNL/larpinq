<?php

/**
 * Larpinq Registration Payment Service
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

use OCA\Larpinq\AppInfo\Application;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Asks shillinq for the payment of an accepted, priced registration
 * (registration-payments-through-shillinq REQ-RPS-001, REQ-RPS-006).
 *
 * Shillinq's leaf raises a request only for a signed-in user with its
 * `payment.request` action, so the request is raised when a game master
 * accepts. A registration accepted any other way (a free place on sign-up, a
 * move up the waiting list, the daily job) waits as `to-request` until a game
 * master presses "Request payment". Without shillinq it waits the same way and
 * a game master sets the state by hand.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationPaymentService {

	public const NOT_NEEDED = 'not-needed';

	public const TO_REQUEST = 'to-request';

	public const OPEN = 'open';

	public const PAID = 'paid';

	public const EXPIRED = 'expired';

	private const ACCEPTED = 'accepted';

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes the register.
	 * @param PaymentLeaf $leaf Shillinq's payment requests leaf.
	 * @param PaymentRequestBuilder $builder Amount, pay-by date, reference and payload.
	 * @param IGroupManager $groups Who is a game master.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly PaymentLeaf $leaf,
		private readonly PaymentRequestBuilder $builder,
		private readonly IGroupManager $groups,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * After a registration is stored: when it has just become accepted on an
	 * event that takes payment, ask for the payment or mark it as waiting.
	 *
	 * @param array<string, mixed> $new The stored registration.
	 * @param array<string, mixed>|null $old The registration before, or null on create.
	 * @param string $actingUid Who made the change, empty for the system.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function afterWrite(array $new, ?array $old, string $actingUid): void {
		if ($this->justAccepted(new: $new, old: $old) === false || $this->hasRequest(registration: $new) === true) {
			return;
		}

		$event = $this->event(registration: $new);
		if (($event['paymentRequired'] ?? false) !== true) {
			return;
		}

		$id = (string)($new['id'] ?? '');
		if ($this->builder->cents(registration: $new) <= 0) {
			$this->store(id: $id, fields: ['paymentState' => self::NOT_NEEDED]);
			return;
		}

		if ($this->isGameMaster(uid: $actingUid) === true && $this->leaf->available() === true) {
			try {
				$this->raise(registration: $new, event: $event);
				return;
			} catch (PaymentRequestRefusedException $e) {
				$this->logger->info('Larpinq: the payment of registration {id} waits for a game master: {reason}', ['id' => $id, 'reason' => $e->getMessage()]);
			}
		}

		$this->store(id: $id, fields: ['paymentState' => self::TO_REQUEST, 'payBy' => $this->builder->payBy(registration: $new, event: $event)]);
	}//end afterWrite()

	/**
	 * A game master's "Request payment": raise the request of an accepted registration that waits.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid The signed-in user.
	 *
	 * @return array<string, mixed> The registration as stored.
	 *
	 * @throws PaymentRequestRefusedException With the reason and status when it is not raised.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function request(string $registrationId, string $actingUid): array {
		if ($this->isGameMaster(uid: $actingUid) === false) {
			throw new PaymentRequestRefusedException('Only game masters request payments.', 403);
		}

		try {
			$registration = $this->fetcher->getObject(objectType: 'registration', id: $registrationId);
		} catch (Throwable $e) {
			throw new PaymentRequestRefusedException('Registration not found.', 404);
		}

		$this->assertRequestable(registration: $registration);
		$event = $this->event(registration: $registration);
		if ($this->builder->cents(registration: $registration) <= 0) {
			throw new PaymentRequestRefusedException('This registration has nothing to pay.', 409);
		}

		return $this->raise(registration: $registration, event: $event);
	}//end request()

	/**
	 * Whether a user is a game master (the group, or a Nextcloud admin).
	 *
	 * @param string $uid The user id, empty for the system.
	 *
	 * @return bool True for a game master.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function isGameMaster(string $uid): bool {
		if ($uid === '') {
			return false;
		}

		return $this->groups->isInGroup($uid, Application::GM_GROUP) === true || $this->groups->isAdmin($uid) === true;
	}//end isGameMaster()

	/**
	 * Refuse a request for a registration that is not accepted, already has one, or without shillinq.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return void
	 *
	 * @throws PaymentRequestRefusedException When it cannot be requested now.
	 */
	private function assertRequestable(array $registration): void {
		if ((string)($registration['status'] ?? '') !== self::ACCEPTED) {
			throw new PaymentRequestRefusedException('Only an accepted registration is paid.', 409);
		}

		if ($this->hasRequest(registration: $registration) === true) {
			throw new PaymentRequestRefusedException('This registration already has a payment request.', 409);
		}

		if ($this->leaf->available() === false) {
			throw new PaymentRequestRefusedException('Shillinq is not installed. Set the payment state by hand.', 409);
		}
	}//end assertRequestable()

	/**
	 * Raise the request through the leaf and store what the player needs to pay.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array<string, mixed> $event The event.
	 *
	 * @return array<string, mixed> The registration as stored.
	 *
	 * @throws PaymentRequestRefusedException When shillinq refuses.
	 */
	private function raise(array $registration, array $event): array {
		$id = (string)($registration['id'] ?? '');
		$reference = $this->builder->reference(registration: $registration, event: $event);
		$payBy = $this->builder->payBy(registration: $registration, event: $event);
		$created = $this->leaf->create(
			registrationId: $id,
			payload: $this->builder->payload(registration: $registration, event: $event, reference: $reference, payBy: $payBy)
		);

		$fields = [
			'paymentState' => self::OPEN,
			'paymentRequestId' => (string)($created['id'] ?? ''),
			'paymentReference' => $reference,
			'payBy' => $payBy,
		];
		if ((string)($created['paymentLink'] ?? '') !== '') {
			$fields['paymentLink'] = (string)$created['paymentLink'];
		}

		return $this->store(id: $id, fields: $fields);
	}//end raise()

	/**
	 * Whether the registration became accepted with this write.
	 *
	 * @param array<string, mixed> $new The stored registration.
	 * @param array<string, mixed>|null $old The registration before.
	 *
	 * @return bool True when it just became accepted.
	 */
	private function justAccepted(array $new, ?array $old): bool {
		return (string)($new['status'] ?? '') === self::ACCEPTED && (string)($old['status'] ?? '') !== self::ACCEPTED;
	}//end justAccepted()

	/**
	 * Whether a payment is already asked for or made.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return bool True when open or paid.
	 */
	private function hasRequest(array $registration): bool {
		return in_array((string)($registration['paymentState'] ?? ''), [self::OPEN, self::PAID], true) === true;
	}//end hasRequest()

	/**
	 * The registration's event, or an empty array when it cannot be read.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return array<string, mixed> The event.
	 */
	private function event(array $registration): array {
		$eventId = (string)($registration['event'] ?? '');
		try {
			$event = $this->fetcher->getObject(objectType: 'event', id: $eventId);
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: event {event} of a registration could not be read for its payment.', ['event' => $eventId, 'exception' => $e]);
			return [];
		}

		return array_merge($event, ['id' => $eventId]);
	}//end event()

	/**
	 * Write payment fields onto a registration with the app's authority.
	 *
	 * @param string $id The registration.
	 * @param array<string, mixed> $fields The fields.
	 *
	 * @return array<string, mixed> The registration as stored.
	 */
	private function store(string $id, array $fields): array {
		return $this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: $fields, uuid: $id);
	}//end store()
}//end class
