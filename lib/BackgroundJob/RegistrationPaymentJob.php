<?php

/**
 * Larpinq Registration Payment Job
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
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\BackgroundJob;

use OCA\Larpinq\Service\PaymentFollowUp;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Once a day: read back captures the listener missed, remind players three
 * days before their pay-by date, and release the places of registrations still
 * unpaid a day after it (registration-payments-through-shillinq REQ-RPS-002,
 * REQ-RPS-003, REQ-RPS-004).
 *
 * @category BackgroundJob
 * @package  OCA\Larpinq\BackgroundJob
 *
 * @psalm-suppress UnusedClass Registered in appinfo/info.xml.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationPaymentJob extends TimedJob {

	/**
	 * Once a day.
	 *
	 * @var integer
	 */
	private const INTERVAL = 86400;

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory $time The clock.
	 * @param PaymentFollowUp $followUp The daily pass.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly PaymentFollowUp $followUp,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(self::INTERVAL);
	}//end __construct()

	/**
	 * Follow up the open payments.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	protected function run($argument): void {
		$counts = $this->followUp->daily(now: $this->time->now());
		$this->logger->info('Larpinq: payments followed up: {paid} paid, {reminded} reminded, {expired} expired.', $counts);
	}//end run()
}//end class
