<?php

/**
 * Larpinq Backfill Check-in Codes
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Repair
 * @package  OCA\Larpinq\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Repair;

use OCA\Larpinq\Service\CheckinCodes;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Give registrations accepted before events-qr-checkin a check-in code.
 * Idempotent: a registration that has a code keeps it.
 *
 * @category Repair
 * @package  OCA\Larpinq\Repair
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */
class BackfillCheckinCodes implements IRepairStep {

	/**
	 * Registrations read in one pass.
	 *
	 * @var int
	 */
	private const MAX_ROWS = 10000;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes registrations with the app's authority.
	 * @param CheckinCodes $codes Makes the codes.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly CheckinCodes $codes,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
	 */
	public function getName(): string {
		return 'Give accepted Larpinq registrations a check-in code';
	}//end getName()

	/**
	 * Run the backfill.
	 *
	 * @param IOutput $output The output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
	 */
	public function run(IOutput $output): void {
		try {
			$accepted = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['status' => 'accepted'], limit: self::MAX_ROWS);
		} catch (Throwable $e) {
			$output->warning('Could not read the registrations, so none got a check-in code: ' . $e->getMessage());
			return;
		}

		$done = 0;
		foreach ($accepted as $registration) {
			$uuid = (string)($registration['id'] ?? '');
			if ($uuid === '' || (string)($registration['checkinCode'] ?? '') !== '') {
				continue;
			}

			try {
				$this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: ['checkinCode' => $this->codes->generate()], uuid: $uuid);
				$done++;
			} catch (Throwable $e) {
				$this->logger->warning('Larpinq: could not give registration {id} a check-in code', ['id' => $uuid, 'exception' => $e->getMessage()]);
			}
		}

		$output->info($done . ' accepted registrations got a check-in code.');
	}//end run()
}//end class
