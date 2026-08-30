# Tasks — skill-requirement-enforcement

## 1. Backend validation service

- [x] 1.1 Create `lib/Service/SkillRequirementService.php`: given old + candidate character arrays, diff `skills[]`/`items[]`/`conditions[]`, and for each newly added skill check `requiredSkills[]`, `requiredStats[]` + `requiredScore`, `requiredConditions[]`, `requiredEffects[]` against the candidate state; return a structured result (per-entry: requirement type, target name/uuid, current vs required, status passed/unmet/overridden/unresolvable)
- [x] 1.2 XP budget check: compute candidate stats via `CharacterService.calculateCharacter()` (reuse the preloaded entity maps, CHAR-080..082 — no second formula) and reject when the XP ability value would go below zero; report cost vs available
- [x] 1.3 Resolve the XP ability via the app register config (slug-tolerant, name-match fallback) — no hardcoded UUIDs (`SkillRequirementService::resolveXpAbility()` name-match: "xp" / "experience"; no XP-ability config key exists in this register, so name-match is the resolution path)
- [x] 1.4 Graceful handling of dangling references (deleted prerequisite skill/ability UUIDs): report as unresolvable, never throw (mirrors CALC-006)
- [x] 1.5 Dependent-skill analysis for removals: when an assigned skill is removed, flag still-assigned skills whose requirements it satisfied as dependent-now-unmet (no cascade-delete, write still succeeds)

## 2. Server-authoritative write hook

- [x] 2.1 Create `lib/Listener/CharacterRequirementListener.php` for OR `ObjectCreatingEvent` / `ObjectUpdatingEvent`, scoped to the character schema via the app's register/schema config; on unmet requirements call the event's rejection path (stop propagation + structured errors) so the OR write returns a 4xx with the itemised list
- [x] 2.2 Register the listener in `lib/AppInfo/Application.php` (guard for the event classes existing so older OR deployments degrade to data-only instead of fataling)
- [x] 2.3 Skip validation when the write does not change `skills[]`/`items[]`/`conditions[]`/`requirementOverrides[]` (pre-existing violations must not block unrelated edits)
- [x] 2.4 Override enforcement in the listener: accept assignments covered by a matching `requirementOverrides[]` entry; reject empty `reason`; reject any write that adds/modifies override entries from a user not in the GM group (`gamemasters`). Server-side stamping of `overriddenBy`/`overriddenAt` is deferred `[~]` — the listener authorises override writes but does not yet rewrite the entries' stamps (the event's modified-data path is available; stamping is a follow-up)

## 3. Schema + report endpoint

- [x] 3.1 Add `requirementOverrides[]` (objects: `skill`, `reason`, `overriddenBy`, `overriddenAt`) to the `character` schema in `lib/Settings/larpingapp_register.json`; validate JSON stays well-formed (character schema → v1.3.0)
- [x] 3.2 Add `GET /api/characters/{id}/requirement-report` to `lib/Controller/CharactersController.php` + `appinfo/routes.php` (`#[NoAdminRequired]` + per-object access via the OR-delegated fetch; correct auth posture per gate-5/gate-9): recompute the full report (unmet, overridden, dependent-now-unmet, unresolvable) for one character on demand
- [x] 3.3 Annotate new/changed PHP methods with `@spec openspec/changes/skill-requirement-enforcement/...` (gate-16) and SPDX headers (gate-1)

## 4. Frontend

- [~] 4.1–4.5 Add-Skill/Item/Condition modal pre-checks, GM override path, server-rejection rendering, and the character-detail warning panel are DEFERRED. This app is a fully declarative manifest-v2 renderer app (`src/manifest.json` pages are typed `index`/`detail` primitives rendered by `@conduction/nextcloud-vue`); it ships no bespoke `src/modals/AddSkillToCharacter.vue` to extend. Enforcement is server-authoritative (the core promise), so the feature works for every write path today; the UI affordances need either renderer-level requirement hooks in nc-vue or a custom character-detail section component — tracked as a nc-vue follow-up.
- [x] 4.6 i18n: validation messages are returned by the server in the structured errors payload (English source strings in the listener/service). No new client-side strings were added because no new client component was built.
- [x] 4.7 Bump `appinfo/info.xml` `<version>` (0.1.26 → 0.1.27)

## 5. Tests

- [x] 5.1 PHPUnit `SkillRequirementServiceTest`: prerequisite pass/fail matrix (requiredSkills, requiredStats+requiredScore), same-write prerequisite satisfaction, XP shortfall, dangling-reference unresolvable, dependent-flagging on removal, validator-vs-engine XP agreement, override-marks-overridden, no-XP-ability degrades open (14 tests)
- [x] 5.2 PHPUnit listener test `CharacterRequirementListenerTest`: rejection payload shape, diff-scoping (unrelated edit passes with pre-existing violation), override acceptance / empty-reason rejection / non-GM override rejection, non-character schema ignored (7 tests)
- [~] 5.3 Newman: DEFERRED — requires a live NC + OpenRegister env with the `gamemasters` group and a seeded character/skill graph; the same assertions are covered server-side by PHPUnit (5.1/5.2) without env dependency. The report endpoint shape is covered by `CharactersControllerTest`.
- [x] 5.4 Playwright `tests/e2e/spec-coverage/skill-requirement-enforcement.spec.ts`: requirement-report endpoint reachable + correct auth posture for an authenticated user (the UI pre-check scenarios are deferred with the frontend in 4.x; this is the back-referencing regression net for gate-19)
- [x] 5.5 `composer check:strict` (php -l + PHPUnit 107 green locally); hydra gates green on the diff (gate-22 manifest-validation is a pre-existing baseline failure on the `roadmap` page type, unrelated to this change)

## 6. Spec sync

- [x] 6.1 On archive, sync the rpg-system delta and the new `skill-requirement-enforcement` capability spec into `openspec/specs/` (via `openspec archive`)
- [x] 6.2 `docs/FEATURES.md` wording: enforcement is now real (server-authoritative with explicit GM override)

## Acceptance criteria

- A character write that adds a skill with unmet prerequisites or insufficient XP is rejected at the OpenRegister API level (not just in the UI) with an itemised unmet-requirements payload. ✅ (listener + service)
- The same assignment succeeds with an explicit GM `requirementOverrides[]` entry carrying a non-empty reason. ✅ (override path; audit via the OR object trail)
- Removing a prerequisite skill never deletes dependent skills; the dependents are flagged in the requirement report. ✅ (analyseRemovals + report endpoint)
- The XP value the validator computes equals the persisted engine-recalculated XP for every accepted write (no parallel formula). ✅ (reuses `calculateCharacter()`; `testValidatorXpAgreesWithEngine`)
- PHPUnit covers the matrix; UI affordances + Newman deferred with reasons; `composer check` and hydra gates pass on the diff.
