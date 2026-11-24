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
 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-paid-cancellation-is-refunded-or-credited-through-shillinq-req-rct-003
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

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
 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-paid-cancellation-is-refunded-or-credited-through-shillinq-req-rct-003
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
	 * @param IEventDispatcher $dispatcher Sends the command.
	 * @param LoggerInterface $logger The logger.
	 * @param string $refundClass The refund command class, looked up by name.
	 * @param string $creditClass The credit command class, looked up by name.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IEventDispatcher $dispatcher,
		private readonly LoggerInterface $logger,
		private readonly string $refundClass = self::REFUND_EVENT,
		private readonly string $creditClass = self::CREDIT_EVENT,
	) {
	}//end __construct()

	/**
	 * Ask for a refund or a credit of one settled payment request.
	 *
	 * @param string $outcome `refund` or `credit`.
	 * @param string $paymentRequestId The settled request.
	 * @param string $correlationId What the money was for (the registration).
	 * @param string $reason Why the money goes back.
	 *
	 * @return string|null The request's state as the payments app answers it
	 *                     (`refund_requested` or `credited`), or null when the
	 *                     app is absent or refused the command.
	 *
	 * @psalm-suppress MixedMethodCall The payments app's event class is an optional dependency.
	 * @psalm-suppress MixedAssignment The payments app's event class is an optional dependency.
	 *
	 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-paid-cancellation-is-refunded-or-credited-through-shillinq-req-rct-003
	 */
	public function ask(string $outcome, string $paymentRequestId, string $correlationId, string $reason): ?string {
		$class = $this->refundClass;
		if ($outcome === CancellationPolicy::CREDIT) {
			$class = $this->creditClass;
		}

		if ($paymentRequestId === '' || class_exists($class) === false) {
			$this->logger->warning(
				'Larpinq: the {outcome} of registration {id} was not handed to the payments app: it is not installed or the request is unknown.',
				['outcome' => $outcome, 'id' => $correlationId]
			);
			return null;
		}

		try {
			$command = new $class(
				sourceApp: self::SOURCE_APP,
				paymentRequestId: $paymentRequestId,
				reason: $reason,
				correlationId: $correlationId,
			);
			$this->dispatcher->dispatchTyped($command);
		} catch (Throwable $e) {
			$this->logger->error(
				'Larpinq: the {outcome} of registration {id} could not be sent to the payments app.',
				['outcome' => $outcome, 'id' => $correlationId, 'exception' => $e]
			);
			return null;
		}

		return $this->answer(command: $command, outcome: $outcome, correlationId: $correlationId);
	}//end ask()

	/**
	 * The state the payments app answered, or null when it did not take the command.
	 *
	 * @param object $command The dispatched command.
	 * @param string $outcome `refund` or `credit`.
	 * @param string $correlationId The registration.
	 *
	 * @return string|null The state.
	 *
	 * @psalm-suppress MixedMethodCall The payments app's event class is an optional dependency.
	 * @psalm-suppress MixedAssignment The payments app's event class is an optional dependency.
	 */
	private function answer(object $command, string $outcome, string $correlationId): ?string {
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
			['outcome' => $outcome, 'id' => $correlationId, 'error' => $error]
		);
		return null;
	}//end answer()
}//end class
