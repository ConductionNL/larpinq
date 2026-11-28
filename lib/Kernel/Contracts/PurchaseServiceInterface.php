<?php

/**
 * Contract of PurchaseService
 *
 * The public surface of PurchaseService that other components call, so they depend
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

/**
 * Contract of PurchaseService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface PurchaseServiceInterface {

	/**
	 * Buy an item for a character.
	 *
	 * The caller MUST have been checked as the character's owner or a game master.
	 *
	 * @param array<string,mixed> $character The character, as read.
	 * @param string              $itemId    The item UUID.
	 *
	 * @return array<string,mixed> The character as saved.
	 *
	 * @throws \OCA\Larpinq\Service\PurchaseRefusedException 404 for an unknown item, 409 when the purchase is not possible.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function buy(array $character, string $itemId): array;

	/**
	 * Read a character, as the caller.
	 *
	 * @param string $characterId The character UUID.
	 *
	 * @return array<string,mixed> The character.
	 *
	 * @throws \OCA\Larpinq\Service\PurchaseRefusedException 404 when there is no such character.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function character(string $characterId): array;
}//end interface
