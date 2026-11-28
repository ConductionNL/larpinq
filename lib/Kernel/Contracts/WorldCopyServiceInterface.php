<?php

/**
 * Contract of WorldCopyService
 *
 * The public surface of WorldCopyService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Larpinq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Kernel\Contracts;

use InvalidArgumentException;
use LengthException;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Contract of WorldCopyService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface WorldCopyServiceInterface {

	/**
	 * Copy the world under a new name.
	 *
	 * @param string $worldId The world UUID.
	 * @param string $name The new world's name.
	 *
	 * @return array{world: array<string, mixed>, counts: array<string, int>} The new world and counts.
	 *
	 * @throws InvalidArgumentException When the name is empty or too long.
	 * @throws DoesNotExistException When the world does not exist.
	 * @throws LengthException When the world holds more than MAX_OBJECTS rule objects.
	 * @throws \OCA\Larpinq\Service\WorldCopyFailedException When a write fails; the copy is rolled back.
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	public function copy(string $worldId, string $name): array;

	/**
	 * What a copy of the world would create, without writing anything.
	 *
	 * @param string $worldId The world UUID.
	 *
	 * @return array{world: array<string, mixed>, counts: array<string, int>} The world and counts.
	 *
	 * @throws DoesNotExistException When the world does not exist.
	 * @throws LengthException When the world holds more than MAX_OBJECTS rule objects.
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	public function preview(string $worldId): array;
}//end interface
