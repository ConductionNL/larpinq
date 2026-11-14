<?php
/**
 * SkillListPools for Larpinq
 *
 * The abilities a character's skill lists pay from, besides XP.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Resolves skills to their list's cost ability.
 *
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
 */
final class SkillListPools {
	/**
	 * The distinct cost abilities of the lists these skills belong to, in the
	 * order the skills name them. A skill without a list, a list that is not
	 * known and a list without a cost ability add nothing: they pay from XP.
	 *
	 * @param list<string>                        $skills    The character's skill ids.
	 * @param array<string, array<string, mixed>> $allSkills Every skill, by id.
	 * @param array<string, array<string, mixed>> $allLists  Every skill list, by id.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
	 */
	public static function costAbilities(array $skills, array $allSkills, array $allLists): array {
		$abilities = [];
		foreach ($skills as $skillId) {
			$listId = (string)($allSkills[$skillId]['list'] ?? '');
			$ability = (string)($allLists[$listId]['costAbility'] ?? '');
			if ($ability !== '') {
				$abilities[$ability] = true;
			}
		}

		return array_keys($abilities);
	}//end costAbilities()

	/**
	 * One budget block per pool: the XP block first, then each list's cost
	 * ability other than XP. The lists are read only when a skill is in one.
	 *
	 * @param array<string, mixed>                                        $stats     The computed stats, by ability id.
	 * @param array{ability: string, value: int, shortfall: int, ok: bool} $budget    The XP block.
	 * @param list<string>                                                $skills    The character's skill ids.
	 * @param array<string, array<string, mixed>>                         $allSkills Every skill, by id.
	 * @param callable(): array<string, array<string, mixed>>             $lists     Reads every skill list, by id.
	 *
	 * @return list<array{ability: string, value: int, shortfall: int, ok: bool}>
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
	 */
	public static function budgets(array $stats, array $budget, array $skills, array $allSkills, callable $lists): array {
		$budgets = [$budget];
		if (self::anyListed(skills: $skills, allSkills: $allSkills) === false) {
			return $budgets;
		}

		$abilities = self::costAbilities(skills: $skills, allSkills: $allSkills, allLists: $lists());
		foreach (array_diff($abilities, [$budget['ability']]) as $ability) {
			$value = (int)($stats[$ability]['value'] ?? 0);
			$budgets[] = ['ability' => $ability, 'value' => $value, 'shortfall' => max(0, -$value), 'ok' => ($value >= 0)];
		}

		return $budgets;
	}//end budgets()

	/**
	 * The overspent pools of a validation result, unless the only one is the
	 * XP block the result already reports as `budget`.
	 *
	 * @param array<string, mixed> $result The SkillRequirementService result.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
	 */
	public static function overspent(array $result): array {
		$overspent = [];
		foreach ((array)($result['budgets'] ?? []) as $pool) {
			if (is_array($pool) === true && ($pool['ok'] ?? true) === false) {
				$overspent[] = $pool;
			}
		}

		if ($overspent === [($result['budget'] ?? null)]) {
			return [];
		}

		return $overspent;
	}//end overspent()

	/**
	 * Whether any of these skills belongs to a list.
	 *
	 * @param list<string>                        $skills    The character's skill ids.
	 * @param array<string, array<string, mixed>> $allSkills Every skill, by id.
	 *
	 * @return boolean
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
	 */
	public static function anyListed(array $skills, array $allSkills): bool {
		foreach ($skills as $skillId) {
			if ((string)($allSkills[$skillId]['list'] ?? '') !== '') {
				return true;
			}
		}

		return false;
	}//end anyListed()
}//end class
