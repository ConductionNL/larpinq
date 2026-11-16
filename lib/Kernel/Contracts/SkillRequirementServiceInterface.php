<?php

/**
 * Contract of SkillRequirementService
 *
 * The public surface of SkillRequirementService that other components call, so they depend
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
 * Contract of SkillRequirementService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface SkillRequirementServiceInterface {

	/**
	 * Validate a candidate character state against skill requirements and the XP budget.
	 *
	 * Diff-scoped: only skills newly added relative to $oldCharacter are checked
	 * for prerequisites, so a pre-existing unmet state never blocks an unrelated
	 * edit. The XP budget is always evaluated on the candidate (a write that
	 * overspends must be rejected regardless of which association changed).
	 *
	 * @param array<string,mixed> $candidate The incoming (candidate) character array.
	 * @param array<string,mixed> $oldCharacter The persisted character array (empty for create).
	 *
	 * @return array{
	 *   valid: bool,
	 *   requirements: array<int, array<string,mixed>>,
	 *   budget: array{ability: string, value: int, shortfall: int, ok: bool},
	 *   budgets: list<array{ability: string, value: int, shortfall: int, ok: bool}>,
	 *   overspentPools: list<array<string, mixed>>,
	 *   dependents: array<int, array<string,mixed>>
	 * } The structured validation result.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	public function validate(array $candidate, array $oldCharacter = []): array;
}//end interface
