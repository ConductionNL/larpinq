<?php

/**
 * CharacterStatsPresenter for Larpinq
 *
 * Turns the stat engine's per-ability audit into the stat sheet: each ability
 * with its base, its final value and the ordered modifiers that moved it, each
 * named by the skill, item, condition, event or XP award behind it. XP earned,
 * spent and left come from the same audit, so they cannot disagree with the
 * budget check.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Presents computed character stats with their sources.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */
class CharacterStatsPresenter {

	/**
	 * The stat sheet for one computed character.
	 *
	 * @param array<string, array<string, mixed>> $stats The engine's `stats` map (abilityId => name, base, value, audit).
	 * @param string|null $xpAbilityId The XP ability, from SkillRequirementService::resolveXpAbility() so the
	 *                                 sheet and the budget check read the same ability.
	 *
	 * @return array{abilities: array<int, array<string, mixed>>, xp: array{ability: string, earned: int, spent: int, left: int}|null} The sheet.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function present(array $stats, ?string $xpAbilityId): array {
		$abilities = [];
		foreach ($stats as $abilityId => $score) {
			$abilities[] = [
				'id' => (string)$abilityId,
				'name' => (string)($score['name'] ?? ''),
				'base' => (int)($score['base'] ?? 0),
				'final' => (int)($score['value'] ?? 0),
				'modifiers' => $this->modifiers(audit: (array)($score['audit'] ?? [])),
			];
		}

		return [
			'abilities' => $abilities,
			'xp' => $this->xp(abilities: $abilities, xpAbilityId: $xpAbilityId),
		];
	}//end present()

	/**
	 * The audit entries of one ability as modifiers with their source.
	 *
	 * @param array<int, mixed> $audit The engine's audit entries.
	 *
	 * @return array<int, array<string, mixed>> The modifiers, in the order applied.
	 */
	private function modifiers(array $audit): array {
		$modifiers = [];
		foreach ($audit as $entry) {
			if (is_array($entry) === false) {
				continue;
			}

			$old = (int)($entry['old'] ?? 0);
			$new = (int)($entry['new'] ?? 0);
			$modifiers[] = array_merge($this->sourceOf(entry: $entry), ['change' => ($new - $old), 'old' => $old, 'new' => $new]);
		}

		return $modifiers;
	}//end modifiers()

	/**
	 * Who carried one audit entry.
	 *
	 * @param array<string, mixed> $entry An audit entry.
	 *
	 * @return array{source: string, sourceId: string, sourceName: string, effectName: string} The source.
	 */
	private function sourceOf(array $entry): array {
		if (($entry['type'] ?? '') === 'xpAward') {
			$award = (array)($entry['award'] ?? []);
			return [
				'source' => 'xpAward',
				'sourceId' => (string)($award['id'] ?? ''),
				'sourceName' => (string)($award['reason'] ?? ''),
				'effectName' => '',
			];
		}

		return [
			'source' => (string)($entry['source'] ?? ''),
			'sourceId' => (string)($entry['sourceId'] ?? ''),
			'sourceName' => (string)($entry['sourceName'] ?? ''),
			'effectName' => (string)($entry['effectName'] ?? ''),
		];
	}//end sourceOf()

	/**
	 * XP earned, spent and left, or null when no XP ability exists.
	 *
	 * Earned is the base plus every increase, spent every decrease, left the
	 * final value: the number the budget check compares with zero.
	 *
	 * @param array<int, array<string, mixed>> $abilities The presented abilities.
	 * @param string|null $xpAbilityId The XP ability id.
	 *
	 * @return array{ability: string, earned: int, spent: int, left: int}|null The XP line.
	 */
	private function xp(array $abilities, ?string $xpAbilityId): ?array {
		foreach ($abilities as $ability) {
			if ($xpAbilityId === null || $ability['id'] !== $xpAbilityId) {
				continue;
			}

			$earned = (int)$ability['base'];
			$spent = 0;
			foreach ($ability['modifiers'] as $modifier) {
				$change = (int)$modifier['change'];
				if ($change > 0) {
					$earned += $change;
					continue;
				}

				$spent -= $change;
			}

			return ['ability' => $xpAbilityId, 'earned' => $earned, 'spent' => $spent, 'left' => (int)$ability['final']];
		}//end foreach

		return null;
	}//end xp()
}//end class
