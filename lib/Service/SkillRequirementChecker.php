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
	 * @param SkillUnderCheck $subject The skill being assigned, and whether a GM override covers it.
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
	public function check(SkillUnderCheck $subject, array $context): array {
		$scan = new RequirementScan(subject: $subject, context: $context);

		return array_merge(
			$this->checkRequiredSkills(scan: $scan),
			$this->checkRequiredStats(scan: $scan),
			$this->checkMemberships(scan: $scan)
		);
	}//end check()

	/**
	 * Prerequisite skills: each must be present in the candidate skill list.
	 *
	 * @param RequirementScan $scan The skill being assigned and its character context.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function checkRequiredSkills(RequirementScan $scan): array {
		$entries = [];
		foreach ($this->idList->normalise($scan->subject->skill['requiredSkills'] ?? []) as $reqSkill) {
			$entries[] = $this->requiredSkillEntry(scan: $scan, reqSkill: $reqSkill);
		}

		return $entries;
	}//end checkRequiredSkills()

	/**
	 * The entry of one prerequisite skill.
	 *
	 * @param RequirementScan $scan     The skill being assigned and its character context.
	 * @param string          $reqSkill The required skill id.
	 *
	 * @return array<string,string|int> The requirement entry.
	 */
	private function requiredSkillEntry(RequirementScan $scan, string $reqSkill): array {
		$allSkills = $scan->context['allSkills'] ?? [];
		$resolved = isset($allSkills[$reqSkill]);
		$targetName = '';
		if ($resolved === true) {
			$targetName = (string)($allSkills[$reqSkill]['name'] ?? '');
		}

		return $this->entry(
			subject: $scan->subject,
			fields: [
				'type' => 'requiredSkill',
				'target' => $reqSkill,
				'targetName' => $targetName,
				'status' => $scan->subject->status(passed: in_array($reqSkill, $scan->context['skills'] ?? [], true), resolved: $resolved),
			]
		);
	}//end requiredSkillEntry()

	/**
	 * Required ability scores: each named ability must meet requiredScore.
	 *
	 * @param RequirementScan $scan The skill being assigned and its character context.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function checkRequiredStats(RequirementScan $scan): array {
		$entries = [];
		foreach ($this->idList->normalise($scan->subject->skill['requiredStats'] ?? []) as $reqStat) {
			$entries[] = $this->requiredStatEntry(scan: $scan, reqStat: $reqStat);
		}

		return $entries;
	}//end checkRequiredStats()

	/**
	 * The entry of one required ability score.
	 *
	 * @param RequirementScan $scan    The skill being assigned and its character context.
	 * @param string          $reqStat The required ability id.
	 *
	 * @return array<string,string|int> The requirement entry.
	 */
	private function requiredStatEntry(RequirementScan $scan, string $reqStat): array {
		$stat = $scan->context['stats'][$reqStat] ?? null;
		$requiredScore = (int)($scan->subject->skill['requiredScore'] ?? 0);
		$current = (int)($stat['value'] ?? 0);

		return $this->entry(
			subject: $scan->subject,
			fields: [
				'type' => 'requiredStat',
				'target' => $reqStat,
				'targetName' => (string)($stat['name'] ?? ''),
				'status' => $scan->subject->status(passed: ($stat !== null && $current >= $requiredScore), resolved: ($stat !== null)),
				'current' => $current,
				'required' => $requiredScore,
			]
		);
	}//end requiredStatEntry()

	/**
	 * Membership requirements: each target must be present in a given pool.
	 *
	 * Required conditions are checked against the candidate's conditions, then
	 * requiredEffects against the effects granted by assigned entities (see
	 * MEMBERSHIP_RULES). Both are always "resolved": the target is a plain
	 * reference, so the only outcome that can differ is present/absent.
	 *
	 * @param RequirementScan $scan The skill being assigned and its character context.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function checkMemberships(RequirementScan $scan): array {
		$entries = [];
		foreach (array_keys(self::MEMBERSHIP_RULES) as $type) {
			$entries = array_merge($entries, $this->membershipEntries(scan: $scan, type: $type));
		}

		return $entries;
	}//end checkMemberships()

	/**
	 * The entries of one membership requirement type.
	 *
	 * @param RequirementScan $scan The skill being assigned and its character context.
	 * @param string          $type The requirement type, a key of MEMBERSHIP_RULES.
	 *
	 * @return array<int,array<string,mixed>> The requirement entries.
	 */
	private function membershipEntries(RequirementScan $scan, string $type): array {
		[$skillField, $poolField] = self::MEMBERSHIP_RULES[$type];
		$pool = ($scan->context[$poolField] ?? []);
		$entries = [];
		foreach ($this->idList->normalise($scan->subject->skill[$skillField] ?? []) as $target) {
			$entries[] = $this->entry(
				subject: $scan->subject,
				fields: [
					'type' => $type,
					'target' => $target,
					'targetName' => '',
					'status' => $scan->subject->status(
						passed: in_array($target, $pool, true),
						resolved: true
					),
				]
			);
		}

		return $entries;
	}//end membershipEntries()

	/**
	 * Build a single requirement-result entry for the skill under check. The
	 * stat values `current` and `required` default to 0 and come last.
	 *
	 * @param SkillUnderCheck          $subject The skill being assigned.
	 * @param array<string,string|int> $fields  The entry fields after `skill`, in output order: type,
	 *                                          target, targetName and status, optionally current and required.
	 *
	 * @return array<string,string|int> The entry.
	 */
	private function entry(SkillUnderCheck $subject, array $fields): array {
		return ['skill' => $subject->skillId] + $fields + ['current' => 0, 'required' => 0];
	}//end entry()
}//end class
