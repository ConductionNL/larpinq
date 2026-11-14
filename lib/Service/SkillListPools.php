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
	 * @param array{skills: list<string>, allSkills: array<string, array<string, mixed>>} $context  The character's skill ids and every skill, by id.
	 * @param array<string, array<string, mixed>>                                        $allLists Every skill list, by id.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
	 */
	public static function costAbilities(array $context, array $allLists): array {
		$abilities = [];
		foreach ($context['skills'] as $skillId) {
			$listId = (string)($context['allSkills'][$skillId]['list'] ?? '');
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
	 * @param array<string, mixed>                            $context The stats by ability id, the XP block (budget), skills and allSkills.
	 * @param callable(): array<string, array<string, mixed>> $lists   Reads every skill list, by id.
	 *
	 * @return list<array{ability: string, value: int, shortfall: int, ok: bool}>
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
	 */
	public static function budgets(array $context, callable $lists): array {
		$budget = $context['budget'];
		$budgets = [$budget];
		if (self::anyListed(context: $context) === false) {
			return $budgets;
		}

		$abilities = self::costAbilities(context: $context, allLists: $lists());
		foreach (array_diff($abilities, [$budget['ability']]) as $ability) {
			$value = (int)($context['stats'][$ability]['value'] ?? 0);
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
	 * @param array{skills: list<string>, allSkills: array<string, array<string, mixed>>} $context The character's skill ids and every skill, by id.
	 *
	 * @return boolean
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
	 */
	public static function anyListed(array $context): bool {
		foreach ($context['skills'] as $skillId) {
			if ((string)($context['allSkills'][$skillId]['list'] ?? '') !== '') {
				return true;
			}
		}

		return false;
	}//end anyListed()
}//end class
