<?php

/**
 * Larpinq Event Feedback Job
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category BackgroundJob
 * @package  OCA\Larpinq\BackgroundJob
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\BackgroundJob;

use OCA\Larpinq\Service\EventFeedbackService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Once a day: post-event feedback invitations and reminders (design D2).
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md
 */
class EventFeedbackJob extends TimedJob {

	/**
	 * Seconds between runs.
	 *
	 * @var int
	 */
	private const INTERVAL = 86400;

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory         $time     The clock.
	 * @param EventFeedbackService $feedback The daily round.
	 * @param LoggerInterface      $logger   The logger.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly EventFeedbackService $feedback,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL);
	}//end __construct()

	/**
	 * Run the daily round.
	 *
	 * @param mixed $argument Unused: a TimedJob is scheduled without one.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md
	 */
	protected function run($argument): void {
		unset($argument);
		$counts = $this->feedback->daily(now: $this->time->now());
		$this->logger->info('Larpinq: post-event feedback for {events} events: {invited} invited, {reminded} reminded.', $counts);
	}//end run()

}//end class
