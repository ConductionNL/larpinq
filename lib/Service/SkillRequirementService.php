<?php

/**
 * SkillRequirementService for LarpingApp
 *
 * Server-authoritative enforcement of skill prerequisites and the XP budget on
 * character-assignment writes. Consumes the existing stat engine
 * (CharacterService::calculateCharacter()) for the XP budget — never a parallel
 * formula — and reads the data-only prerequisite model already carried by the
 * skill schema (requiredSkills/requiredStats/requiredScore/requiredConditions/
 * requiredEffects).
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

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
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 *
 * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
 */
class SkillRequirementService
{
    /**
     * Constructor for SkillRequirementService.
     *
     * @param CharacterService      $characterService The stat engine (reused for the XP budget).
     * @param RegisterObjectFetcher $objectFetcher    The register object fetcher.
     * @param LoggerInterface       $logger           The logger.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly CharacterService $characterService,
        private readonly RegisterObjectFetcher $objectFetcher,
        private readonly LoggerInterface $logger
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
     * @param array<string,mixed> $candidate    The incoming (candidate) character array.
     * @param array<string,mixed> $oldCharacter The persisted character array (empty for create).
     *
     * @return array{
     *   valid: bool,
     *   requirements: array<int, array<string,mixed>>,
     *   budget: array{ability: string, value: int, shortfall: int, ok: bool},
     *   dependents: array<int, array<string,mixed>>
     * } The structured validation result.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
     */
    public function validate(array $candidate, array $oldCharacter=[]): array
    {
        $skills    = $this->idList(value: ($candidate['skills'] ?? []));
        $oldSkills = $this->idList(value: ($oldCharacter['skills'] ?? []));
        $added     = array_values(array_diff($skills, $oldSkills));
        $removed   = array_values(array_diff($oldSkills, $skills));

        $overrides     = $this->indexOverrides(character: $candidate);
        $allSkills     = $this->fetchIndexed(objectType: 'skill');
        $allConditions = $this->idList(value: ($candidate['conditions'] ?? []));
        $allEffects    = $this->collectAssignedEffects(character: $candidate, allSkills: $allSkills);

        // Compute the candidate stats once via the stat engine (no parallel formula).
        $calculated = $this->characterService->calculateCharacter(character: $candidate);
        $stats      = [];
        if (is_array($calculated['stats'] ?? null) === true) {
            $stats = $calculated['stats'];
        }

        $requirements = [];
        foreach ($added as $skillId) {
            $skill = $allSkills[$skillId] ?? null;
            if ($skill === null) {
                $requirements[] = [
                    'skill'  => $skillId,
                    'type'   => 'skill',
                    'target' => $skillId,
                    'status' => 'unresolvable',
                ];
                continue;
            }

            $overridden = isset($overrides[$skillId]);

            // Prerequisite skills: each must be present in the candidate skill list.
            foreach ($this->idList(value: ($skill['requiredSkills'] ?? [])) as $reqSkill) {
                $present    = in_array($reqSkill, $skills, true);
                $resolved   = isset($allSkills[$reqSkill]);
                $targetName = '';
                if ($resolved === true) {
                    $targetName = (string) ($allSkills[$reqSkill]['name'] ?? '');
                }

                $requirements[] = $this->entry(
                    skillId: $skillId,
                    type: 'requiredSkill',
                    target: $reqSkill,
                    targetName: $targetName,
                    status: $this->status(passed: $present, resolved: $resolved, overridden: $overridden)
                );
            }

            // Required ability scores: each ability must meet requiredScore.
            $requiredScore = (int) ($skill['requiredScore'] ?? 0);
            foreach ($this->idList(value: ($skill['requiredStats'] ?? [])) as $reqStat) {
                $resolved   = isset($stats[$reqStat]);
                $current    = 0;
                $targetName = '';
                if ($resolved === true) {
                    $current    = (int) ($stats[$reqStat]['value'] ?? 0);
                    $targetName = (string) ($stats[$reqStat]['name'] ?? '');
                }

                $passed         = ($resolved === true && $current >= $requiredScore);
                $requirements[] = $this->entry(
                    skillId: $skillId,
                    type: 'requiredStat',
                    target: $reqStat,
                    targetName: $targetName,
                    status: $this->status(passed: $passed, resolved: $resolved, overridden: $overridden),
                    current: $current,
                    required: $requiredScore
                );
            }

            // Required conditions: each must be in the candidate conditions list.
            foreach ($this->idList(value: ($skill['requiredConditions'] ?? [])) as $reqCond) {
                $present        = in_array($reqCond, $allConditions, true);
                $requirements[] = $this->entry(
                    skillId: $skillId,
                    type: 'requiredCondition',
                    target: $reqCond,
                    targetName: '',
                    status: $this->status(passed: $present, resolved: true, overridden: $overridden)
                );
            }

            // Required effects: each must be granted by an assigned entity.
            foreach ($this->idList(value: ($skill['requiredEffects'] ?? [])) as $reqEffect) {
                $present        = in_array($reqEffect, $allEffects, true);
                $requirements[] = $this->entry(
                    skillId: $skillId,
                    type: 'requiredEffect',
                    target: $reqEffect,
                    targetName: '',
                    status: $this->status(passed: $present, resolved: true, overridden: $overridden)
                );
            }
        }//end foreach

        $budget     = $this->evaluateBudget(stats: $stats);
        $dependents = $this->analyseRemovals(removed: $removed, remainingSkills: $skills, allSkills: $allSkills);

        $hasUnmet = $budget['ok'] === false;
        foreach ($requirements as $entry) {
            if ($entry['status'] === 'unmet' || $entry['status'] === 'unresolvable') {
                $hasUnmet = true;
                break;
            }
        }

        return [
            'valid'        => ($hasUnmet === false),
            'requirements' => $requirements,
            'budget'       => $budget,
            'dependents'   => $dependents,
        ];
    }//end validate()

    /**
     * Build a single requirement-result entry.
     *
     * @param string $skillId    The skill being assigned.
     * @param string $type       The requirement type.
     * @param string $target     The required target UUID.
     * @param string $targetName The required target's human name (may be empty).
     * @param string $status     One of passed|unmet|overridden|unresolvable.
     * @param int    $current    The current value (stat checks only).
     * @param int    $required   The required value (stat checks only).
     *
     * @return array<string,mixed> The entry.
     */
    private function entry(
        string $skillId,
        string $type,
        string $target,
        string $targetName,
        string $status,
        int $current=0,
        int $required=0
    ): array {
        return [
            'skill'      => $skillId,
            'type'       => $type,
            'target'     => $target,
            'targetName' => $targetName,
            'status'     => $status,
            'current'    => $current,
            'required'   => $required,
        ];
    }//end entry()

    /**
     * Derive a status from passed/resolved/overridden flags.
     *
     * An overridden requirement is reported as "overridden" (never "passed"),
     * an unresolvable target as "unresolvable", otherwise passed/unmet.
     *
     * @param bool $passed     Whether the requirement is satisfied.
     * @param bool $resolved   Whether the required target resolves to an object.
     * @param bool $overridden Whether a GM override covers this skill.
     *
     * @return string The status.
     */
    private function status(bool $passed, bool $resolved, bool $overridden): string
    {
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
    private function evaluateBudget(array $stats): array
    {
        $xpAbilityId = $this->resolveXpAbility(stats: $stats);
        if ($xpAbilityId === null) {
            // No XP ability configured/resolvable — the budget cannot be
            // enforced, so it never blocks (degrade open for the budget only;
            // prerequisites are unaffected).
            return [
                'ability'   => '',
                'value'     => 0,
                'shortfall' => 0,
                'ok'        => true,
            ];
        }

        $value     = (int) ($stats[$xpAbilityId]['value'] ?? 0);
        $shortfall = 0;
        if ($value < 0) {
            $shortfall = abs($value);
        }

        return [
            'ability'   => $xpAbilityId,
            'value'     => $value,
            'shortfall' => $shortfall,
            'ok'        => ($value >= 0),
        ];
    }//end evaluateBudget()

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
     * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
     */
    public function resolveXpAbility(array $stats): ?string
    {
        foreach ($stats as $abilityId => $ability) {
            $name = strtolower((string) ($ability['name'] ?? ''));
            if ($name === 'xp' || str_contains($name, 'experience') === true || str_contains($name, 'xp ') === true) {
                return (string) $abilityId;
            }
        }

        return null;
    }//end resolveXpAbility()

    /**
     * Analyse skill removals for now-dangling dependents.
     *
     * When a skill is removed, any still-assigned skill that declared it as a
     * requiredSkill is flagged dependent-now-unmet. The write is NOT blocked —
     * this is a report, surfaced as a warning.
     *
     * @param array<int,string>                 $removed         The removed skill ids.
     * @param array<int,string>                 $remainingSkills The remaining (candidate) skill ids.
     * @param array<string,array<string,mixed>> $allSkills       All skills indexed by id.
     *
     * @return array<int,array<string,mixed>> The dependent flags.
     */
    private function analyseRemovals(array $removed, array $remainingSkills, array $allSkills): array
    {
        if (empty($removed) === true) {
            return [];
        }

        $dependents = [];
        foreach ($remainingSkills as $skillId) {
            $skill = $allSkills[$skillId] ?? null;
            if ($skill === null) {
                continue;
            }

            foreach ($this->idList(value: ($skill['requiredSkills'] ?? [])) as $reqSkill) {
                if (in_array($reqSkill, $removed, true) === true) {
                    $dependents[] = [
                        'skill'        => $skillId,
                        'skillName'    => (string) ($skill['name'] ?? ''),
                        'missingSkill' => $reqSkill,
                    ];
                }
            }
        }

        return $dependents;
    }//end analyseRemovals()

    /**
     * Collect the effect ids granted by a character's assigned skills.
     *
     * @param array<string,mixed>               $character The character array.
     * @param array<string,array<string,mixed>> $allSkills All skills indexed by id.
     *
     * @return array<int,string> The effect ids in play.
     */
    private function collectAssignedEffects(array $character, array $allSkills): array
    {
        $effects = [];
        foreach ($this->idList(value: ($character['skills'] ?? [])) as $skillId) {
            $skill = $allSkills[$skillId] ?? null;
            if ($skill === null) {
                continue;
            }

            foreach ($this->idList(value: ($skill['effects'] ?? [])) as $effectId) {
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
    private function indexOverrides(array $character): array
    {
        $indexed   = [];
        $overrides = $character['requirementOverrides'] ?? [];
        if (is_array($overrides) === false) {
            return $indexed;
        }

        foreach ($overrides as $override) {
            if (is_array($override) === false) {
                continue;
            }

            $skill  = (string) ($override['skill'] ?? '');
            $reason = trim((string) ($override['reason'] ?? ''));
            if ($skill !== '' && $reason !== '') {
                $indexed[$skill] = $override;
            }
        }

        return $indexed;
    }//end indexOverrides()

    /**
     * Fetch all objects of a type and index them by id, tolerating OR absence.
     *
     * @param string $objectType The object type slug.
     *
     * @return array<string,array<string,mixed>> Objects indexed by id.
     */
    private function fetchIndexed(string $objectType): array
    {
        try {
            $objects = $this->objectFetcher->getObjects($objectType);
        } catch (\Throwable $e) {
            $this->logger->debug(
                'LarpingApp: SkillRequirementService could not load '.$objectType.'; degrading to data-only.',
                ['exception' => $e]
            );
            return [];
        }

        $indexed = [];
        foreach ($objects as $object) {
            if (is_array($object) === true && isset($object['id']) === true) {
                $indexed[(string) $object['id']] = $object;
            }
        }

        return $indexed;
    }//end fetchIndexed()

    /**
     * Normalise a value to a list of string ids.
     *
     * Tolerates arrays of strings, arrays of {id} objects, or a single value.
     *
     * @param mixed $value The raw value.
     *
     * @return array<int,string> The id list.
     */
    private function idList(mixed $value): array
    {
        if (is_array($value) === false) {
            if ($value === null || $value === '') {
                return [];
            }

            return [(string) $value];
        }

        $ids = [];
        foreach ($value as $entry) {
            if (is_array($entry) === true) {
                if (isset($entry['id']) === true) {
                    $ids[] = (string) $entry['id'];
                }

                continue;
            }

            if ($entry !== null && $entry !== '') {
                $ids[] = (string) $entry;
            }
        }

        return $ids;
    }//end idList()
}//end class
