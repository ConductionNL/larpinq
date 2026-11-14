<?php

/**
 * GiveAcceptedRegistrationsACheckinCode repair step for Larpinq
 *
 * Gives every accepted registration without a check-in code a code
 * (events-qr-checkin, REQ-EQC-001). RegistrationService sets the code when a
 * registration becomes accepted; registrations accepted before that existed
 * have none, and their players would arrive at the door without one.
 * Idempotent: a registration that has a code keeps it.
 *
 * @category  Repair
 * @package   OCA\Larpinq\Repair
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-every-accepted-registration-has-a-check-in-code-req-eqc-001
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Repair;

use OCA\Larpinq\Service\CheckinCode;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Repair step: a check-in code for each accepted registration that has none.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-every-accepted-registration-has-a-check-in-code-req-eqc-001
 */
class GiveAcceptedRegistrationsACheckinCode implements IRepairStep {

	/**
	 * The most accepted registrations read in one pass.
	 *
	 * @var int
	 */
	public const MAX_ROWS = 10000;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher The register.
	 * @param LoggerInterface       $logger        The logger.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/events-qr-checkin/tasks.md#1.1
	 */
	public function getName(): string {
		return 'Give accepted Larpinq registrations a check-in code';
	}//end getName()

	/**
	 * Give each accepted registration without a code a code.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/events-qr-checkin/tasks.md#1.1
	 */
	public function run(IOutput $output): void {
		try {
			$accepted = $this->objectFetcher->getObjectsWithAppAuthority(
				objectType: 'registration',
				filters: ['status' => RegistrationService::ACCEPTED],
				limit: self::MAX_ROWS
			);
		} catch (Throwable $e) {
			$output->warning('Could not read the registrations, so none got a check-in code: ' . $e->getMessage());
			return;
		}

		$missing = array_filter(
			$accepted,
			static fn (mixed $registration): bool => is_array($registration) === true
				&& (string)($registration['checkinCode'] ?? '') === ''
				&& (string)($registration['id'] ?? '') !== ''
		);

		$done = 0;
		foreach ($missing as $registration) {
			$uuid = (string)$registration['id'];
			unset($registration['@self']);
			$registration['checkinCode'] = CheckinCode::generate();
			try {
				$this->objectFetcher->saveObjectWithAppAuthority(objectType: 'registration', data: $registration, uuid: $uuid);
				$done++;
			} catch (Throwable $e) {
				$this->logger->warning('Larpinq: could not give registration {id} a check-in code', ['id' => $uuid, 'exception' => $e->getMessage()]);
			}
		}

		$output->info($done . ' of ' . count($missing) . ' accepted registrations without a check-in code now have one.');
	}//end run()
}//end class
