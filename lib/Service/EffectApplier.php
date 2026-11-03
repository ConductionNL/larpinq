<?php

/**
 * EffectApplier for Larpinq
 *
 * The effect arithmetic of the stat engine: resolving effect references,
 * enforcing the non-cumulative dedup rule, and applying signed modifiers to
 * abilities with an audit trail. Split out of CharacterService so that class
 * keeps the world loading and calculation orchestration while the per-effect
 * rules live here.
 *
 * Stateless by design — the effect lookup and the ability scores are passed in
 * per call, so one instance is safe to share across characters.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-73
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Applies effects to a character's ability scores.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-73
 */
class EffectApplier {
	/**
	 * Apply a list of effect references to abilities.
	 *
	 * @param array<string, array<string, mixed>> $abilities Reference to abilities.
	 *                                                       See calculateEffect() for why this is the loose shape and not
	 *                                                       `array{name?: string, base?: int, value: int, audit: array}` — the
	 *                                                       array is threaded by reference all the way down to
	 *                                                       applyModifierToAbility(), which can seed an entry with `value`
	 *                                                       alone.
	 * @param array|null $effects Array of effect IDs.
	 * @param array<string, bool> $appliedEffects Tracks applied non-cumulative effects.
	 * @param array<string, array<string, mixed>> $effectLookup All effects indexed by ID.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedArgumentTypeCoercion Abilities array keys may widen during mutation.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-73
	 */
	public function applyEffects(
		array &$abilities,
		?array $effects,
		array &$appliedEffects,
		array $effectLookup,
	): void {
		// Return early if effects is null or empty.
		if ($effects === null || count($effects) === 0) {
			return;
		}

		$run = new EffectRun(abilities: $abilities, applied: $appliedEffects);
		// @psalm-suppress MixedAssignment Effect IDs from entity arrays
		foreach ($effects as $effectId) {
			// Skip if effectId is null.
			if ($effectId === null) {
				continue;
			}

			$effect = $effectLookup[(string)$effectId] ?? null;
			if ($effect !== null) {
				$this->calculateEffect(run: $run, effect: $effect);
			}
		}

		$abilities = $run->abilities;
		$appliedEffects = $run->applied;
	}//end applyEffects()

	/**
	 * Collect all unique ability IDs affected by a given effect.
	 *
	 * Merges `abilities` array and `stat_id` field, then deduplicates to prevent
	 * double-application when `stat_id` is also present in `abilities`. Closes #208.
	 *
	 * @param array<string, mixed> $effect Effect data.
	 *
	 * @return array List of unique ability IDs.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-74
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-75
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-76
	 */
	private function collectEffectAbilities(array $effect): array {
		$effectAbilities = [];
		if (isset($effect['abilities']) === true && is_array($effect['abilities']) === true) {
			$effectAbilities = $effect['abilities'];
		}

		// Add stat_id to affected abilities if present. `isset()` is already
		// false for null, so the `!== null` this replaces was unreachable.
		if (isset($effect['stat_id']) === true) {
			// @psalm-suppress MixedAssignment Effect array values are mixed
			$effectAbilities[] = $effect['stat_id'];
		}

		// Deduplicate to prevent double-application when stat_id is also in abilities.
		// Closes #208 (stat_id double-apply).
		return array_values(array_unique($effectAbilities));
	}//end collectEffectAbilities()

	/**
	 * Apply a modifier to a single ability based on an effect.
	 *
	 * The `modifier` value is always coerced to its absolute value; the
	 * `modification` enum (positive/negative) controls direction. This
	 * eliminates the sign-direction confusion where `{modifier: -3,
	 * modification: 'negative'}` would have added 3 instead of subtracting it.
	 * Closes #209.
	 *
	 * The audit trail stores only the minimal scalar fields needed to explain
	 * the change (effect ID + label + delta), not the full effect object.
	 * This avoids denormalisation bloat if derived stats are ever persisted.
	 * Closes #219.
	 *
	 * @param array<string, mixed> $ability Reference to the ability; a missing `value` starts at 0.
	 * @param array<string, mixed> $effect Effect data.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-77
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-78
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-79
	 */
	private function applyModifierToAbility(array &$ability, array $effect): void {
		if (isset($ability['value']) === false) {
			$ability['value'] = 0;
		}

		// Get current value and modifiers.
		$currentValue = (int)$ability['value'];

		// Coerce modifier to a non-negative integer. The `modification` enum (positive/negative)
		// controls the sign. This prevents sign-direction confusion where a GM authors
		// `{modifier: -3, modification: 'negative'}` and gets +3 instead of -3.
		// Closes #209.
		$modifier = abs((int)($effect['modifier'] ?? 0));

		// @var string $modification.
		$modification = $effect['modification'] ?? 'positive';

		// Apply modification based on type.
		if ($modification === 'positive') {
			$ability['value'] = $currentValue + $modifier;
		} elseif ($modification === 'negative') {
			$ability['value'] = $currentValue - $modifier;
		}

		$newValue = $ability['value'];

		// Store a lean audit entry — effect ID + label + delta only.
		// Storing the full $effect object risks denormalisation bloat when stats
		// are ever persisted back to OR. Closes #219.
		$ability['audit'][] = [
			'type' => 'effect',
			'effectId' => (string)($effect['id'] ?? ''),
			'effectName' => (string)($effect['name'] ?? ''),
			'old' => $currentValue,
			'new' => $newValue,
		];
	}//end applyModifierToAbility()

	/**
	 * Calculate and apply a single effect.
	 *
	 * Skips non-cumulative effects that have already been applied in this
	 * calculation pass. Closes #208.
	 *
	 * @param EffectRun $run The pass: the abilities as they change and the effects applied so far.
	 * @param array<string, mixed> $effect Effect data.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-76
	 */
	private function calculateEffect(EffectRun $run, array $effect): void {
		$effectId = (string)($effect['id'] ?? '');

		// Enforce the non-cumulative dedup rule. If this effect has been applied
		// already and is marked non-cumulative, skip it. Closes #208.
		if ($effectId !== '' && isset($run->applied[$effectId]) === true) {
			$cumulative = (string)($effect['cumulative'] ?? 'non-cumulative');
			if ($cumulative === 'non-cumulative') {
				return;
			}
		}

		$effectAbilities = $this->collectEffectAbilities(effect: $effect);

		// Skip if no abilities are affected.
		if (empty($effectAbilities) === true) {
			return;
		}

		// Apply the effect to each affected ability.
		// @psalm-suppress MixedAssignment Ability IDs from effect arrays.
		foreach ($effectAbilities as $rawAbilityId) {
			// Skip if abilityId is null.
			if ($rawAbilityId === null) {
				continue;
			}

			$abilityId = (string)$rawAbilityId;
			$run->abilities[$abilityId] ??= [];
			$this->applyModifierToAbility(ability: $run->abilities[$abilityId], effect: $effect);
		}

		// Record that this effect was applied so non-cumulative dedupe works
		// on the next encounter of the same effect ID within this pass.
		if ($effectId !== '') {
			$run->applied[$effectId] = true;
		}
	}//end calculateEffect()
}//end class
