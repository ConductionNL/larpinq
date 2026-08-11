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
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
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
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */
class SkillRequirementService
{
    /**
     * Constructor for SkillRequirementService.
     *
     * @param CharacterService        $characterService The stat engine (reused for the XP budget).
     * @param RegisterObjectFetcher   $objectFetcher    The register object fetcher.
     * @param LoggerInterface         $logger           The logger.
     * @param SkillRequirementChecker $checker          The per-skill requirement rules.
     * @param IdListNormaliser        $idList           Relation-shape normaliser.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly CharacterService $characterService,
        private readonly RegisterObjectFetcher $objectFetcher,
        private readonly LoggerInterface $logger,
        private readonly SkillRequirementChecker $checker,
        private readonly IdListNormaliser $idList
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
     * @spec openspec/specs/skill-requirement-enforcement/spec.md
     */
    public function validate(array $candidate, array $oldCharacter=[]): array
    {
        $skills    = $this->idList->normalise($candidate['skills'] ?? []);
        $oldSkills = $this->idList->normalise($oldCharacter['skills'] ?? []);
        $added     = array_values(array_diff($skills, $oldSkills));
        $removed   = array_values(array_diff($oldSkills, $skills));

        $allSkills = $this->fetchIndexed(objectType: 'skill');

        // Compute the candidate stats once via the stat engine (no parallel formula).
        $calculated = $this->characterService->calculateCharacter(character: $candidate);
        $stats      = [];
        if (is_array($calculated['stats'] ?? null) === true) {
            $stats = $calculated['stats'];
        }

        $requirements = $this->evaluateAdded(
            added: $added,
            overrides: $this->indexOverrides(character: $candidate),
            context: [
                'skills'     => $skills,
                'allSkills'  => $allSkills,
                'stats'      => $stats,
                'conditions' => $this->idList->normalise($candidate['conditions'] ?? []),
                'effects'    => $this->collectAssignedEffects(character: $candidate, allSkills: $allSkills),
            ]
        );

        $budget     = $this->evaluateBudget(stats: $stats);
        $dependents = $this->analyseRemovals(removed: $removed, remainingSkills: $skills, allSkills: $allSkills);

        return [
            'valid'        => ($this->hasUnmet(requirements: $requirements, budget: $budget) === false),
            'requirements' => $requirements,
            'budget'       => $budget,
            'dependents'   => $dependents,
        ];
    }//end validate()

    /**
     * Evaluate every newly-added skill against its declared requirements.
     *
     * A skill id that does not resolve to a skill object yields a single
     * "unresolvable" entry rather than being silently skipped — an unknown
     * prerequisite must never read as satisfied.
     *
     * @param array<int,string>                 $added     The newly-added skill ids.
     * @param array<string,array<string,mixed>> $overrides GM overrides indexed by skill id.
     * @param array<string,mixed>               $context   The resolved character context.
     *
     * @return array<int,array<string,mixed>> The requirement entries.
     *
     * @spec openspec/specs/skill-requirement-enforcement/spec.md
     */
    private function evaluateAdded(array $added, array $overrides, array $context): array
    {
        $allSkills = $context['allSkills'] ?? [];

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

            $requirements = array_merge(
                $requirements,
                $this->checker->check(
                    skillId: $skillId,
                    skill: $skill,
                    overridden: isset($overrides[$skillId]),
                    context: $context
                )
            );
        }//end foreach

        return $requirements;
    }//end evaluateAdded()

    /**
     * Whether anything blocks the write: an overspent budget or a failed entry.
     *
     * @param array<int,array<string,mixed>>                               $requirements The requirement entries.
     * @param array{ability: string, value: int, shortfall: int, ok: bool} $budget       The budget block.
     *
     * @return bool True when the candidate must be rejected.
     *
     * @spec openspec/specs/skill-requirement-enforcement/spec.md
     */
    private function hasUnmet(array $requirements, array $budget): bool
    {
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
     * @spec openspec/specs/skill-requirement-enforcement/spec.md
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

            foreach ($this->idList->normalise($skill['requiredSkills'] ?? []) as $reqSkill) {
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
}//end class
