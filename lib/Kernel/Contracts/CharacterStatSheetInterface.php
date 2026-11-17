<?php

/**
 * Contract of CharacterStatSheet
 *
 * The public surface of CharacterStatSheet that other components call, so they depend
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
 * Contract of CharacterStatSheet.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface CharacterStatSheetInterface {

	/**
	 * The presented sheet of computed stats, with the XP ability the budget check reads.
	 *
	 * @param array<string, mixed> $stats The engine's stats map.
	 *
	 * @return array<string, mixed> The sheet.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function present(array $stats): array;

	/**
	 * The requirement report of a changed character.
	 *
	 * @param array<string, mixed> $candidate The character as it would be.
	 * @param array<string, mixed> $character The character as it is.
	 *
	 * @return array<string, mixed> The report.
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 */
	public function report(array $candidate, array $character): array;

	/**
	 * The engine's stats map of a character.
	 *
	 * @param array<string, mixed> $character The character.
	 *
	 * @return array<string, mixed> The stats, abilityId => score.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function stats(array $character): array;
}//end interface
