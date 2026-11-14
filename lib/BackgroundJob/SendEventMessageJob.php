<?php

/**
 * Sends a queued event message, 50 recipients per run.
 *
 * @category  BackgroundJob
 * @package   OCA\Larpinq\BackgroundJob
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\BackgroundJob;

use OCA\Larpinq\Service\EventMessageService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/**
 * One run sends one batch of a message; EventMessageService queues the next
 * batch itself and stores the counts after the last one.
 */
class SendEventMessageJob extends QueuedJob {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory $time The clock.
	 * @param EventMessageService $messages The message service.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly EventMessageService $messages,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
	}//end __construct()

	/**
	 * Send the batch the argument names.
	 *
	 * @param mixed $argument `{messageId, eventId, eventName, eventDate, offset, sent, failed}`.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-each-recipient-gets-their-own-mail-req-cmp-003
	 */
	protected function run($argument): void {
		if (is_array($argument) === false) {
			$argument = [];
		}

		$result = $this->messages->sendBatch(argument: $argument);
		$this->logger->info(
			'Larpinq: event message batch sent: {sent} sent, {failed} failed.',
			['sent' => $result['sent'], 'failed' => $result['failed']]
		);
	}//end run()
}//end class
