<?php
/**
 * CreateStaffRoleGroups repair step for Larpinq
 *
 * Creates the four staff role groups (story writers, rules marshals,
 * treasurers, stewards) when they are missing. Nobody is added to them; a
 * group of the same name that already exists is reused as it is.
 *
 * @category  Repair
 * @package   OCA\Larpinq\Repair
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-four-staff-roles-exist-next-to-the-game-masters-req-asr-001
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Repair;

use OCA\Larpinq\Support\AppIdentity;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Creates the missing staff role groups; logs and continues on a failure, so an install never aborts on it.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-four-staff-roles-exist-next-to-the-game-masters-req-asr-001
 */
class CreateStaffRoleGroups implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param IGroupManager   $groupManager Creates the groups.
	 * @param LoggerInterface $logger       Logs a group that could not be created.
	 */
	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-four-staff-roles-exist-next-to-the-game-masters-req-asr-001
	 */
	public function getName(): string {
		return 'Create the Larpinq staff role groups';
	}//end getName()

	/**
	 * Create each staff role group that does not exist yet.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-four-staff-roles-exist-next-to-the-game-masters-req-asr-001
	 */
	public function run(IOutput $output): void {
		foreach (AppIdentity::STAFF_ROLES as $group) {
			if ($this->groupManager->groupExists($group) === true) {
				continue;
			}

			try {
				if ($this->groupManager->createGroup($group) === null) {
					$this->logger->warning('Larpinq: could not create staff role group ' . $group);
					continue;
				}

				$output->info('Created staff role group ' . $group);
			} catch (Throwable $exception) {
				$this->logger->warning('Larpinq: could not create staff role group ' . $group . ': ' . $exception->getMessage());
			}
		}
	}//end run()
}//end class
