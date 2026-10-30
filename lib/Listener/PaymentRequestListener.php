<?php

/**
 * Larpinq Payment Request Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
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

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\Service\PaymentFollowUp;
use OCA\Larpinq\Service\PaymentLeaf;
use OCA\Larpinq\Service\RegistrationPaymentService;
use OCA\Larpinq\Service\RegistrationSettlement;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IUserSession;

/**
 * The two object events a registration's payment follows
 * (registration-payments-through-shillinq REQ-RPS-001, REQ-RPS-002):
 *
 * - a larpinq registration that has just become accepted asks for its payment;
 * - a shillinq `PaymentRequest` whose subject is a larpinq registration and
 *   that shillinq reports captured marks that registration paid;
 * - a paid registration that has just become cancelled asks shillinq for a
 *   refund or credit (registration-cancel-transfer-refund REQ-RCT-003).
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class PaymentRequestListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $config The registration schema id.
	 * @param RegistrationPaymentService $payments Asks for a registration's payment.
	 * @param PaymentFollowUp $followUp Marks a registration paid.
	 * @param PaymentLeaf $leaf Whether a request's subject is a registration.
	 * @param IUserSession $session Who accepted.
	 * @param RegistrationSettlement $settlement The money of a paid cancellation.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IAppConfig $config,
		private readonly RegistrationPaymentService $payments,
		private readonly PaymentFollowUp $followUp,
		private readonly PaymentLeaf $leaf,
		private readonly IUserSession $session,
		private readonly RegistrationSettlement $settlement,
	) {
	}//end __construct()

	/**
	 * Handle a created or updated OpenRegister object.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @listener-placement inline correctness: shillinq's leaf raises a payment request only for the signed-in
	 * game master who accepts (its payment.request action is read from the session), so a deferred job, which has
	 * no session, would always be refused; the paid state is one bounded write of the one registration the request
	 * names, found by a limit-1 filtered read.
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedArgument    OpenRegister event/entity classes are optional dependencies.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function handle(Event $event): void {
		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatedEvent) {
			$this->changed(entity: $event->getObject(), old: null);
			return;
		}

		if ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatedEvent) {
			$this->changed(entity: $event->getNewObject(), old: $event->getOldObject());
		}
	}//end handle()

	/**
	 * Route one stored object: a registration, a shillinq request on one, or neither.
	 *
	 * @param object $entity The object as stored.
	 * @param object|null $old The object before, or null on create.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity classes are optional dependencies.
	 */
	private function changed(object $entity, ?object $old): void {
		$data = $this->dataOf(entity: $entity);
		$schema = $this->config->getValueString('larpinq', 'registration_schema', '');
		if ($schema !== '' && is_callable([$entity, 'getSchema']) === true && (string)$entity->getSchema() === $schema) {
			$before = null;
			if ($old !== null) {
				$before = $this->dataOf(entity: $old);
			}

			$this->payments->afterWrite(new: $data, old: $before, actingUid: $this->actingUid());
			$this->settlement->afterWrite(new: $data, old: $before);
			return;
		}

		if (($data['subjectKind'] ?? '') === 'object' && $this->leaf->isRegistrationSubject(subject: $data['subject'] ?? null) === true) {
			$this->followUp->requestChanged(request: $data);
		}
	}//end changed()

	/**
	 * The object's data with its uuid as `id`.
	 *
	 * @param object $entity The OpenRegister object.
	 *
	 * @return array<string, mixed> The data.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity classes are optional dependencies.
	 */
	private function dataOf(object $entity): array {
		$data = (array)$entity->getObject();
		if (is_callable([$entity, 'getUuid']) === true && (string)$entity->getUuid() !== '') {
			$data['id'] = (string)$entity->getUuid();
		}

		return $data;
	}//end dataOf()

	/**
	 * The signed-in user, or an empty string for a system write.
	 *
	 * @return string The user id.
	 */
	private function actingUid(): string {
		$user = $this->session->getUser();
		if ($user === null) {
			return '';
		}

		return $user->getUID();
	}//end actingUid()
}//end class
