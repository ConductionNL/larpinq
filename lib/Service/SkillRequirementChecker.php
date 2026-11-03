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
	 * Membership requirement types, in check order: the skill field holding
	 * the required ids and the context pool they must be in.
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private const MEMBERSHIP_RULES = [
		'requiredCondition' => ['requiredConditions', 'conditions'],
		'requiredEffect' => ['requiredEffects', 'effects'],
	];

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
		$subject = new SkillUnderCheck(skillId: $skillId, skill: $skill, overridden: $overridden);

		return array_merge(
			$this->checkRequiredSkills(subject: $subject, context: $context),
			$this->checkRequiredStats(subject: $subject, context: $context),
			$this->checkMemberships(subject: $subject, context: $context)
		);
	}//end check()

	/**
	 * Prerequisite skills: each must be present in the candidate skill list.
	 *
	 * @param SkillUnderCheck $subject The skill being assigned.
	 * @param array<string,mixed> $context The resolved character context.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function checkRequiredSkills(SkillUnderCheck $subject, array $context): array {
		$skills = $context['skills'] ?? [];
		$allSkills = $context['allSkills'] ?? [];

		$entries = [];
		foreach ($this->idList->normalise($subject->skill['requiredSkills'] ?? []) as $reqSkill) {
			$present = in_array($reqSkill, $skills, true);
			$resolved = isset($allSkills[$reqSkill]);
			$targetName = '';
			if ($resolved === true) {
				$targetName = (string)($allSkills[$reqSkill]['name'] ?? '');
			}

			$entries[] = $this->entry(
				fields: [
					'skill' => $subject->skillId,
					'type' => 'requiredSkill',
					'target' => $reqSkill,
					'targetName' => $targetName,
					'status' => $subject->status(passed: $present, resolved: $resolved),
				]
			);
		}

		return $entries;
	}//end checkRequiredSkills()

	/**
	 * Required ability scores: each named ability must meet requiredScore.
	 *
	 * @param SkillUnderCheck $subject The skill being assigned.
	 * @param array<string,mixed> $context The resolved character context.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function checkRequiredStats(SkillUnderCheck $subject, array $context): array {
		$stats = $context['stats'] ?? [];
		$requiredScore = (int)($subject->skill['requiredScore'] ?? 0);

		$entries = [];
		foreach ($this->idList->normalise($subject->skill['requiredStats'] ?? []) as $reqStat) {
			$resolved = isset($stats[$reqStat]);
			$current = 0;
			$targetName = '';
			if ($resolved === true) {
				$current = (int)($stats[$reqStat]['value'] ?? 0);
				$targetName = (string)($stats[$reqStat]['name'] ?? '');
			}

			$passed = ($resolved === true && $current >= $requiredScore);
			$entries[] = $this->entry(
				fields: [
					'skill' => $subject->skillId,
					'type' => 'requiredStat',
					'target' => $reqStat,
					'targetName' => $targetName,
					'status' => $subject->status(passed: $passed, resolved: $resolved),
					'current' => $current,
					'required' => $requiredScore,
				]
			);
		}

		return $entries;
	}//end checkRequiredStats()

	/**
	 * Membership requirements: each target must be present in a given pool.
	 *
	 * Required conditions are checked against the candidate's conditions, then
	 * requiredEffects against the effects granted by assigned entities (see
	 * MEMBERSHIP_RULES). Both are always "resolved": the target is a plain
	 * reference, so the only outcome that can differ is present/absent.
	 *
	 * @param SkillUnderCheck $subject The skill being assigned.
	 * @param array<string,mixed> $context The resolved character context.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function checkMemberships(SkillUnderCheck $subject, array $context): array {
		$entries = [];
		foreach (self::MEMBERSHIP_RULES as $type => [$skillField, $poolField]) {
			$pool = ($context[$poolField] ?? []);
			foreach ($this->idList->normalise($subject->skill[$skillField] ?? []) as $target) {
				$entries[] = $this->entry(
					fields: [
						'skill' => $subject->skillId,
						'type' => $type,
						'target' => $target,
						'targetName' => '',
						'status' => $subject->status(
							passed: in_array($target, $pool, true),
							resolved: true
						),
					]
				);
			}
		}

		return $entries;
	}//end checkMemberships()

	/**
	 * Build a single requirement-result entry. The stat values `current` and
	 * `required` default to 0 and come last.
	 *
	 * @param array<string,string|int> $fields The entry fields in output order: skill, type, target,
	 *                                         targetName and status, optionally current and required.
	 *
	 * @return array<string,string|int> The entry.
	 */
	private function entry(array $fields): array {
		return $fields + ['current' => 0, 'required' => 0];
	}//end entry()
}//end class
