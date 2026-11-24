<?php

/**
 * Larpinq Payment Settlement Command
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
 * @spec openspec/specs/event-registration/spec.md#requirement-a-paid-cancellation-is-refunded-or-credited-through-shillinq-req-rct-003
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCA\Larpinq\Event\RegistrationSettlementRequested;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Asks the payments app to give the money of a settled payment request back,
 * or to keep it as credit for the payer's next request
 * (registration-cancel-transfer-refund REQ-RCT-003). The command is the
 * payments app's own typed fleet event (hydra ADR-041): the class is looked up
 * by name and the ask fails closed when that app is absent, so larpinq keeps
 * working without it. Larpinq never holds a balance itself (hydra ADR-107).
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md#requirement-a-paid-cancellation-is-refunded-or-credited-through-shillinq-req-rct-003
 */
class PaymentSettlementCommand {

	/**
	 * The payments app's refund command.
	 *
	 * @var string
	 */
	public const REFUND_EVENT = 'OCA\\Shillinq\\Event\\PaymentRefundRequestedEvent';

	/**
	 * The payments app's credit command.
	 *
	 * @var string
	 */
	public const CREDIT_EVENT = 'OCA\\Shillinq\\Event\\PaymentCreditRequestedEvent';

	/**
	 * The app the payment requests stand on.
	 *
	 * @var string
	 */
	private const SOURCE_APP = 'larpinq';

	/**
	 * Constructor.
	 *
	 * @param IEventDispatcher $dispatcher Sends the commands.
	 * @param LoggerInterface $logger The logger.
	 * @param array<string, string> $commands The payments app's command class per kind, looked up by name.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IEventDispatcher $dispatcher,
		private readonly LoggerInterface $logger,
		private readonly array $commands = [
			CancellationPolicy::REFUND => self::REFUND_EVENT,
			CancellationPolicy::CREDIT => self::CREDIT_EVENT,
		],
	) {
	}//end __construct()

	/**
	 * Send larpinq's own refund or credit command, then hand it to the payments app.
	 *
	 * @param RegistrationSettlementRequested $event Larpinq's command, for its own listeners.
	 * @param string $reason Why the money goes back.
	 *
	 * @return string|null The request's state as the payments app answers it
	 *                     (`refund_requested` or `credited`), or null when the
	 *                     app is absent or refused the command.
	 *
	 * @spec openspec/specs/event-registration/spec.md#requirement-a-paid-cancellation-is-refunded-or-credited-through-shillinq-req-rct-003
	 */
	public function send(RegistrationSettlementRequested $event, string $reason): ?string {
		$this->dispatcher->dispatchTyped($event);

		$class = (string)($this->commands[$event->getKind()] ?? '');
		if ($event->getPaymentRequestId() === '' || $class === '' || class_exists($class) === false) {
			$this->logger->warning(
				'Larpinq: the {outcome} of registration {id} was not handed to the payments app: it is not installed or the request is unknown.',
				['outcome' => $event->getKind(), 'id' => $event->getRegistrationId()]
			);
			return null;
		}

		try {
			$command = new $class(
				sourceApp: self::SOURCE_APP,
				paymentRequestId: $event->getPaymentRequestId(),
				reason: $reason,
				correlationId: $event->getRegistrationId(),
			);
			$this->dispatcher->dispatchTyped($command);
		} catch (Throwable $e) {
			$this->logger->error(
				'Larpinq: the {outcome} of registration {id} could not be sent to the payments app.',
				['outcome' => $event->getKind(), 'id' => $event->getRegistrationId(), 'exception' => $e]
			);
			return null;
		}

		return $this->answer(command: $command, event: $event);
	}//end send()

	/**
	 * The state the payments app answered, or null when it did not take the command.
	 *
	 * @param object $command The dispatched command.
	 * @param RegistrationSettlementRequested $event Larpinq's command it answers.
	 *
	 * @return string|null The state.
	 *
	 * @psalm-suppress MixedMethodCall The payments app's event class is an optional dependency.
	 * @psalm-suppress MixedAssignment The payments app's event class is an optional dependency.
	 */
	private function answer(object $command, RegistrationSettlementRequested $event): ?string {
		$result = null;
		if (method_exists($command, 'isHandled') === true && $command->isHandled() === true && method_exists($command, 'getResult') === true) {
			$result = $command->getResult();
		}

		if (is_array($result) === true && is_string($result['state'] ?? null) === true && $result['state'] !== '') {
			return $result['state'];
		}

		$error = '';
		if (method_exists($command, 'getError') === true) {
			$error = (string)$command->getError();
		}

		$this->logger->warning(
			'Larpinq: the payments app did not take the {outcome} of registration {id}: {error}',
			['outcome' => $event->getKind(), 'id' => $event->getRegistrationId(), 'error' => $error]
		);
		return null;
	}//end answer()
}//end class
