<?php

/**
 * CharacterStatSheet for Larpinq
 *
 * The stat sheet of a character as the stats and builds endpoints show it:
 * the engine's computed stats, presented with the XP ability the budget check
 * reads, and the requirement report of a changed character.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Computes, presents and checks character stats for the stat sheet endpoints.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */
class CharacterStatSheet {

	/**
	 * Constructor.
	 *
	 * @param CharacterService $characterService The stat engine.
	 * @param SkillRequirementService $requirementService The requirement and XP budget check.
	 * @param CharacterStatsPresenter $presenter Shapes the sheet.
	 * @param CharacterXpHistory|null $xpHistory The skill refunds of a character; without it the history is empty.
	 */
	public function __construct(
		private readonly CharacterService $characterService,
		private readonly SkillRequirementService $requirementService,
		private readonly CharacterStatsPresenter $presenter,
		private readonly ?CharacterXpHistory $xpHistory = null,
	) {
	}//end __construct()

	/**
	 * The engine's stats map of a character.
	 *
	 * @param array<string, mixed> $character The character.
	 *
	 * @return array<string, mixed> The stats, abilityId => score.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function stats(array $character): array {
		return (array)($this->characterService->calculateCharacter(character: $character)['stats'] ?? []);
	}//end stats()

	/**
	 * The presented sheet of computed stats, with the XP ability the budget check reads.
	 *
	 * @param array<string, mixed> $stats The engine's stats map.
	 *
	 * @return array<string, mixed> The sheet.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function present(array $stats): array {
		return $this->presenter->present(stats: $stats, xpAbilityId: $this->requirementService->resolveXpAbility(stats: $stats));
	}//end present()

	/**
	 * The XP history of a character: the skills removed from it and the XP each returned.
	 *
	 * @param array<string, mixed> $character The character, as read by the caller.
	 *
	 * @return array<int, array<string, mixed>> The history lines, newest first.
	 *
	 * @spec openspec/changes/characters-skill-refund/specs/character-management/spec.md#requirement-the-refund-shows-in-the-xp-history-req-csp-006
	 */
	public function xpHistory(array $character): array {
		if ($this->xpHistory === null) {
			return [];
		}

		return $this->xpHistory->refunds(character: $character);
	}//end xpHistory()

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
	public function report(array $candidate, array $character): array {
		return $this->requirementService->validate(candidate: $candidate, oldCharacter: $character);
	}//end report()
}//end class
