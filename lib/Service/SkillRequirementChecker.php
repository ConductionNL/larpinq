<?php

/**
 * SkillRequirementChecker for Larpinq
 *
 * Evaluates the declared prerequisites of a single newly-added skill against a
 * resolved character context, producing the per-requirement result entries.
 * Split out of SkillRequirementService so that service keeps the orchestration
 * (diffing, budget, dependents) and this class keeps the requirement rules.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Produces requirement-result entries for one assigned skill.
 *
 * The four requirement kinds carried by the skill schema are checked here:
 * requiredSkills, requiredStats (against requiredScore), requiredConditions and
 * requiredEffects. Conditions and effects share one membership check.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */
class SkillRequirementChecker {
	/**
	 * Constructor for SkillRequirementChecker.
	 *
	 * @param IdListNormaliser $idList Relation-shape normaliser.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IdListNormaliser $idList,
	) {
	}//end __construct()

	/**
	 * Check every declared requirement of one assigned skill.
	 *
	 * @param string $skillId The skill being assigned.
	 * @param array<string,mixed> $skill The skill object.
	 * @param bool $overridden Whether a GM override covers this skill.
	 * @param array<string,mixed> $context The resolved character context: keys
	 *                                     `skills` (array<int,string>),
	 *                                     `allSkills` (array<string,array<string,mixed>>),
	 *                                     `stats` (array<string,array<string,mixed>>),
	 *                                     `conditions` (array<int,string>) and
	 *                                     `effects` (array<int,string>).
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	public function check(string $skillId, array $skill, bool $overridden, array $context): array {
		return array_merge(
			$this->checkRequiredSkills(
				skillId: $skillId,
				skill: $skill,
				overridden: $overridden,
				context: $context
			),
			$this->checkRequiredStats(
				skillId: $skillId,
				skill: $skill,
				overridden: $overridden,
				context: $context
			),
			$this->checkMembership(
				skillId: $skillId,
				type: 'requiredCondition',
				targets: $this->idList->normalise($skill['requiredConditions'] ?? []),
				pool: ($context['conditions'] ?? []),
				overridden: $overridden
			),
			$this->checkMembership(
				skillId: $skillId,
				type: 'requiredEffect',
				targets: $this->idList->normalise($skill['requiredEffects'] ?? []),
				pool: ($context['effects'] ?? []),
				overridden: $overridden
			)
		);
	}//end check()

	/**
	 * Prerequisite skills: each must be present in the candidate skill list.
	 *
	 * @param string $skillId The skill being assigned.
	 * @param array<string,mixed> $skill The skill object.
	 * @param bool $overridden Whether a GM override covers this skill.
	 * @param array<string,mixed> $context The resolved character context.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function checkRequiredSkills(string $skillId, array $skill, bool $overridden, array $context): array {
		$skills = $context['skills'] ?? [];
		$allSkills = $context['allSkills'] ?? [];

		$entries = [];
		foreach ($this->idList->normalise($skill['requiredSkills'] ?? []) as $reqSkill) {
			$present = in_array($reqSkill, $skills, true);
			$resolved = isset($allSkills[$reqSkill]);
			$targetName = '';
			if ($resolved === true) {
				$targetName = (string)($allSkills[$reqSkill]['name'] ?? '');
			}

			$entries[] = $this->entry(
				skillId: $skillId,
				type: 'requiredSkill',
				target: $reqSkill,
				targetName: $targetName,
				status: $this->status(passed: $present, resolved: $resolved, overridden: $overridden)
			);
		}

		return $entries;
	}//end checkRequiredSkills()

	/**
	 * Required ability scores: each named ability must meet requiredScore.
	 *
	 * @param string $skillId The skill being assigned.
	 * @param array<string,mixed> $skill The skill object.
	 * @param bool $overridden Whether a GM override covers this skill.
	 * @param array<string,mixed> $context The resolved character context.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function checkRequiredStats(string $skillId, array $skill, bool $overridden, array $context): array {
		$stats = $context['stats'] ?? [];
		$requiredScore = (int)($skill['requiredScore'] ?? 0);

		$entries = [];
		foreach ($this->idList->normalise($skill['requiredStats'] ?? []) as $reqStat) {
			$resolved = isset($stats[$reqStat]);
			$current = 0;
			$targetName = '';
			if ($resolved === true) {
				$current = (int)($stats[$reqStat]['value'] ?? 0);
				$targetName = (string)($stats[$reqStat]['name'] ?? '');
			}

			$passed = ($resolved === true && $current >= $requiredScore);
			$entries[] = $this->entry(
				skillId: $skillId,
				type: 'requiredStat',
				target: $reqStat,
				targetName: $targetName,
				status: $this->status(passed: $passed, resolved: $resolved, overridden: $overridden),
				current: $current,
				required: $requiredScore
			);
		}

		return $entries;
	}//end checkRequiredStats()

	/**
	 * Membership requirements: each target must be present in a given pool.
	 *
	 * Shared by requiredConditions (pool = the candidate's conditions) and
	 * requiredEffects (pool = the effects granted by assigned entities). Both
	 * are always "resolved" — the target is a plain reference, so the only
	 * outcome that can differ is present/absent.
	 *
	 * @param string $skillId The skill being assigned.
	 * @param string $type The requirement type.
	 * @param array<int,string> $targets The required target ids.
	 * @param array<int,string> $pool The ids in play on the candidate.
	 * @param bool $overridden Whether a GM override covers this skill.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function checkMembership(
		string $skillId,
		string $type,
		array $targets,
		array $pool,
		bool $overridden,
	): array {
		$entries = [];
		foreach ($targets as $target) {
			$entries[] = $this->entry(
				skillId: $skillId,
				type: $type,
				target: $target,
				targetName: '',
				status: $this->status(
					passed: in_array($target, $pool, true),
					resolved: true,
					overridden: $overridden
				)
			);
		}

		return $entries;
	}//end checkMembership()

	/**
	 * Build a single requirement-result entry.
	 *
	 * @param string $skillId The skill being assigned.
	 * @param string $type The requirement type.
	 * @param string $target The required target UUID.
	 * @param string $targetName The required target's human name (may be empty).
	 * @param string $status One of passed|unmet|overridden|unresolvable.
	 * @param int $current The current value (stat checks only).
	 * @param int $required The required value (stat checks only).
	 *
	 * @return array<string,mixed> The entry.
	 */
	private function entry(
		string $skillId,
		string $type,
		string $target,
		string $targetName,
		string $status,
		int $current = 0,
		int $required = 0,
	): array {
		return [
			'skill' => $skillId,
			'type' => $type,
			'target' => $target,
			'targetName' => $targetName,
			'status' => $status,
			'current' => $current,
			'required' => $required,
		];
	}//end entry()

	/**
	 * Derive a status from passed/resolved/overridden flags.
	 *
	 * An overridden requirement is reported as "overridden" (never "passed"),
	 * an unresolvable target as "unresolvable", otherwise passed/unmet.
	 *
	 * @param bool $passed Whether the requirement is satisfied.
	 * @param bool $resolved Whether the required target resolves to an object.
	 * @param bool $overridden Whether a GM override covers this skill.
	 *
	 * @return string The status.
	 */
	private function status(bool $passed, bool $resolved, bool $overridden): string {
		if ($resolved === false) {
			return 'unresolvable';
		}

		if ($passed === true) {
			return 'passed';
		}

		if ($overridden === true) {
			return 'overridden';
		}

		return 'unmet';
	}//end status()
}//end class
