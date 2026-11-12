<?php

/**
 * SkillRequirementService for Larpinq
 *
 * Server-authoritative enforcement of skill prerequisites and the XP budget on
 * character-assignment writes. Consumes the existing stat engine
 * (CharacterService::calculateCharacter()) for the XP budget — never a parallel
 * formula — and reads the data-only prerequisite model already carried by the
 * skill schema (requiredSkills/requiredStats/requiredScore/requiredConditions/
 * requiredEffects).
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Psr\Log\LoggerInterface;

/**
 * Validates character skill/item/condition assignments against their
 * declared requirements and the computed XP budget.
 *
 * The result shape is a list of per-requirement entries plus a budget block,
 * suitable for both the OR pre-write veto payload and the on-demand
 * requirement report.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */
class SkillRequirementService {
	/**
	 * Constructor for SkillRequirementService.
	 *
	 * @param CharacterService $characterService The stat engine (reused for the XP budget).
	 * @param RegisterObjectFetcher $objectFetcher The register object fetcher.
	 * @param LoggerInterface $logger The logger.
	 * @param SkillRequirementChecker $checker The per-skill requirement rules.
	 * @param IdListNormaliser $idList Relation-shape normaliser.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly CharacterService $characterService,
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly LoggerInterface $logger,
		private readonly SkillRequirementChecker $checker,
		private readonly IdListNormaliser $idList,
	) {
	}//end __construct()

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
	 *   dependents: array<int, array<string,mixed>>
	 * } The structured validation result.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	public function validate(array $candidate, array $oldCharacter = []): array {
		$skills = $this->idList->normalise($candidate['skills'] ?? []);
		$oldSkills = $this->idList->normalise($oldCharacter['skills'] ?? []);
		$context = $this->candidateContext(candidate: $candidate, skills: $skills);

		$requirements = $this->evaluateAdded(added: array_values(array_diff($skills, $oldSkills)), context: $context);
		$budget = $this->evaluateBudget(stats: $context['stats']);
		$dependents = $this->analyseRemovals(removed: array_values(array_diff($oldSkills, $skills)), context: $context);

		return [
			'valid' => ($this->hasUnmet(requirements: $requirements, budget: $budget) === false),
			'requirements' => $requirements,
			'budget' => $budget,
			'dependents' => $dependents,
		];
	}//end validate()

	/**
	 * Resolve the context a candidate is checked in: its skills, every skill,
	 * its computed stats, conditions, effects in play and GM overrides.
	 *
	 * @param array<string,mixed> $candidate The incoming (candidate) character array.
	 * @param array<int,string>   $skills    The candidate's skill ids.
	 *
	 * @return array{
	 *   skills: array<int,string>,
	 *   allSkills: array<string,array<string,mixed>>,
	 *   stats: array<string,array<string,mixed>>,
	 *   conditions: array<int,string>,
	 *   effects: array<int,string>,
	 *   overrides: array<string,array<string,mixed>>
	 * } The resolved character context.
	 */
	private function candidateContext(array $candidate, array $skills): array {
		$allSkills = $this->fetchIndexed(objectType: 'skill');
		$stats = $this->candidateStats(candidate: $candidate);
		$overrides = $this->indexOverrides(character: $candidate);

		return [
			'skills' => $skills,
			'allSkills' => $allSkills,
			'stats' => $stats,
			'conditions' => $this->idList->normalise($candidate['conditions'] ?? []),
			'effects' => $this->collectAssignedEffects(character: $candidate, allSkills: $allSkills),
			'overrides' => $overrides,
		];
	}//end candidateContext()

	/**
	 * Compute the candidate stats once via the stat engine (no parallel formula).
	 *
	 * @param array<string,mixed> $candidate The incoming (candidate) character array.
	 *
	 * @return array<string,array<string,mixed>> The computed stats map keyed by ability id.
	 */
	private function candidateStats(array $candidate): array {
		$calculated = $this->characterService->calculateCharacter(character: $candidate);
		if (is_array($calculated['stats'] ?? null) === true) {
			return $calculated['stats'];
		}

		return [];
	}//end candidateStats()

	/**
	 * Evaluate every newly-added skill against its declared requirements.
	 *
	 * A skill id that does not resolve to a skill object yields a single
	 * "unresolvable" entry rather than being silently skipped — an unknown
	 * prerequisite must never read as satisfied.
	 *
	 * @param array<int,string> $added The newly-added skill ids.
	 * @param array<string,mixed> $context The resolved character context, with the GM overrides
	 *                                     indexed by skill id under `overrides`.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	private function evaluateAdded(array $added, array $context): array {
		$requirements = [];
		foreach ($added as $skillId) {
			$requirements = array_merge($requirements, $this->requirementsOf(skillId: $skillId, context: $context));
		}

		return $requirements;
	}//end evaluateAdded()

	/**
	 * The requirement entries of one newly-added skill.
	 *
	 * @param string              $skillId The newly-added skill id.
	 * @param array<string,mixed> $context The resolved character context.
	 *
	 * @return array<int,array<string,mixed>> The skill's entries, or one "unresolvable" entry.
	 */
	private function requirementsOf(string $skillId, array $context): array {
		$skill = ($context['allSkills'] ?? [])[$skillId] ?? null;
		if ($skill === null) {
			return [$this->unresolvableSkill(skillId: $skillId)];
		}

		$overrides = $context['overrides'] ?? [];

		return $this->checker->check(
			subject: new SkillUnderCheck(skillId: $skillId, skill: $skill, overridden: isset($overrides[$skillId])),
			context: $context
		);
	}//end requirementsOf()

	/**
	 * The entry of an added skill id that does not resolve to a skill object.
	 *
	 * @param string $skillId The unresolvable skill id.
	 *
	 * @return array<string,string> The "unresolvable" entry.
	 */
	private function unresolvableSkill(string $skillId): array {
		return [
			'skill' => $skillId,
			'type' => 'skill',
			'target' => $skillId,
			'status' => 'unresolvable',
		];
	}//end unresolvableSkill()

	/**
	 * Whether anything blocks the write: an overspent budget or a failed entry.
	 *
	 * @param array<int,array<string,mixed>> $requirements The requirement entries.
	 * @param array{ability: string, value: int, shortfall: int, ok: bool} $budget The budget block.
	 *
	 * @return bool True when the candidate must be rejected.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	private function hasUnmet(array $requirements, array $budget): bool {
		if ($budget['ok'] === false) {
			return true;
		}

		foreach ($requirements as $entry) {
			if ($entry['status'] === 'unmet' || $entry['status'] === 'unresolvable') {
				return true;
			}
		}

		return false;
	}//end hasUnmet()

	/**
	 * Evaluate the XP budget from the computed stats.
	 *
	 * Resolves the XP ability (config-tolerant name-match fallback) and reports
	 * whether its computed value is non-negative.
	 *
	 * @param array<string,array<string,mixed>> $stats The computed stats map keyed by ability id.
	 *
	 * @return array{ability: string, value: int, shortfall: int, ok: bool} The budget block.
	 */
	private function evaluateBudget(array $stats): array {
		$xpAbilityId = $this->resolveXpAbility(stats: $stats);
		if ($xpAbilityId === null) {
			// No XP ability configured/resolvable — the budget cannot be
			// enforced, so it never blocks (degrade open for the budget only;
			// prerequisites are unaffected).
			return $this->budgetBlock(ability: '', value: 0);
		}

		return $this->budgetBlock(ability: $xpAbilityId, value: (int)($stats[$xpAbilityId]['value'] ?? 0));
	}//end evaluateBudget()

	/**
	 * The budget block for an XP ability's computed value.
	 *
	 * @param string $ability The XP ability id ('' when none resolves).
	 * @param int    $value   The computed XP value.
	 *
	 * @return array{ability: string, value: int, shortfall: int, ok: bool} The budget block.
	 */
	private function budgetBlock(string $ability, int $value): array {
		$shortfall = 0;
		if ($value < 0) {
			$shortfall = abs($value);
		}

		return [
			'ability' => $ability,
			'value' => $value,
			'shortfall' => $shortfall,
			'ok' => ($value >= 0),
		];
	}//end budgetBlock()

	/**
	 * Resolve the XP ability id from the computed stats.
	 *
	 * No hardcoded UUIDs: matches on the ability name containing "xp" or
	 * "experience" (case-insensitive). Shared resolution rule with
	 * event-xp-award-workflow.
	 *
	 * @param array<string,array<string,mixed>> $stats The computed stats map.
	 *
	 * @return string|null The XP ability id, or null when none resolves.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	public function resolveXpAbility(array $stats): ?string {
		foreach ($stats as $abilityId => $ability) {
			if ($this->isXpName(name: strtolower((string)($ability['name'] ?? ''))) === true) {
				return (string)$abilityId;
			}
		}

		return null;
	}//end resolveXpAbility()

	/**
	 * Whether a lower-cased ability name names the XP ability.
	 *
	 * @param string $name The lower-cased ability name.
	 *
	 * @return bool True for "xp", a name containing "experience" or "xp ".
	 */
	private function isXpName(string $name): bool {
		return $name === 'xp' || str_contains($name, 'experience') === true || str_contains($name, 'xp ') === true;
	}//end isXpName()

	/**
	 * Analyse skill removals for now-dangling dependents.
	 *
	 * When a skill is removed, any still-assigned skill that declared it as a
	 * requiredSkill is flagged dependent-now-unmet. The write is NOT blocked —
	 * this is a report, surfaced as a warning.
	 *
	 * @param array<int,string> $removed The removed skill ids.
	 * @param array<string,mixed> $context The resolved character context: the remaining
	 *                                     (candidate) skill ids under `skills`, all skills
	 *                                     indexed by id under `allSkills`.
	 *
	 * @return array<int,array<string,mixed>> The dependent flags.
	 */
	private function analyseRemovals(array $removed, array $context): array {
		if (empty($removed) === true) {
			return [];
		}

		$scan = ['allSkills' => $context['allSkills'], 'removed' => $removed];
		$dependents = [];
		foreach ($context['skills'] as $skillId) {
			$dependents = array_merge($dependents, $this->dependentsOf(skillId: $skillId, scan: $scan));
		}

		return $dependents;
	}//end analyseRemovals()

	/**
	 * The dependent flags of one remaining skill: each removed skill it requires.
	 *
	 * @param string              $skillId The remaining skill id.
	 * @param array<string,mixed> $scan    All skills indexed by id under `allSkills`,
	 *                                     the removed skill ids under `removed`.
	 *
	 * @return array<int,array<string,mixed>> The dependent flags, empty for an unknown skill.
	 */
	private function dependentsOf(string $skillId, array $scan): array {
		$skill = $scan['allSkills'][$skillId] ?? null;
		if ($skill === null) {
			return [];
		}

		$dependents = [];
		foreach (array_intersect($this->idList->normalise($skill['requiredSkills'] ?? []), $scan['removed']) as $reqSkill) {
			$dependents[] = [
				'skill' => $skillId,
				'skillName' => (string)($skill['name'] ?? ''),
				'missingSkill' => $reqSkill,
			];
		}

		return $dependents;
	}//end dependentsOf()

	/**
	 * Collect the effect ids granted by a character's assigned skills.
	 *
	 * @param array<string,mixed> $character The character array.
	 * @param array<string,array<string,mixed>> $allSkills All skills indexed by id.
	 *
	 * @return array<int,string> The effect ids in play.
	 */
	private function collectAssignedEffects(array $character, array $allSkills): array {
		$effects = [];
		foreach ($this->idList->normalise($character['skills'] ?? []) as $skillId) {
			$skill = $allSkills[$skillId] ?? null;
			if ($skill === null) {
				continue;
			}

			foreach ($this->idList->normalise($skill['effects'] ?? []) as $effectId) {
				$effects[] = $effectId;
			}
		}

		return array_values(array_unique($effects));
	}//end collectAssignedEffects()

	/**
	 * Index the requirementOverrides[] of a character by skill id.
	 *
	 * @param array<string,mixed> $character The character array.
	 *
	 * @return array<string,array<string,mixed>> Overrides indexed by skill id.
	 */
	private function indexOverrides(array $character): array {
		$indexed = [];
		$overrides = $character['requirementOverrides'] ?? [];
		if (is_array($overrides) === false) {
			return $indexed;
		}

		foreach ($overrides as $override) {
			$skill = $this->overriddenSkill(override: $override);
			if ($skill !== '') {
				$indexed[$skill] = $override;
			}
		}

		return $indexed;
	}//end indexOverrides()

	/**
	 * The skill an override applies to, when it names one and gives a reason.
	 *
	 * @param mixed $override One requirementOverrides[] entry.
	 *
	 * @return string The overridden skill id, or '' when the override does not apply.
	 */
	private function overriddenSkill(mixed $override): string {
		if (is_array($override) === false) {
			return '';
		}

		$reason = trim((string)($override['reason'] ?? ''));
		if ($reason === '') {
			return '';
		}

		return (string)($override['skill'] ?? '');
	}//end overriddenSkill()

	/**
	 * Fetch all objects of a type and index them by id, tolerating OR absence.
	 *
	 * @param string $objectType The object type slug.
	 *
	 * @return array<string,array<string,mixed>> Objects indexed by id.
	 */
	private function fetchIndexed(string $objectType): array {
		try {
			$objects = $this->objectFetcher->getObjects($objectType);
		} catch (\Throwable $e) {
			$this->logger->debug(
				'Larpinq: SkillRequirementService could not load ' . $objectType . '; degrading to data-only.',
				['exception' => $e]
			);
			return [];
		}

		return $this->indexById(objects: $objects);
	}//end fetchIndexed()

	/**
	 * Index objects by id, skipping anything without one.
	 *
	 * @param array<int|string,mixed> $objects The fetched objects.
	 *
	 * @return array<string,array<string,mixed>> Objects indexed by id.
	 */
	private function indexById(array $objects): array {
		$indexed = [];
		foreach ($objects as $object) {
			if (is_array($object) === true && isset($object['id']) === true) {
				$indexed[(string)$object['id']] = $object;
			}
		}

		return $indexed;
	}//end indexById()
}//end class
