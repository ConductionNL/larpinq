# Tasks — skill-requirement-enforcement

## 1. Backend validation service

- [ ] 1.1 Create `lib/Service/SkillRequirementService.php`: given old + candidate character arrays, diff `skills[]`/`items[]`/`conditions[]`, and for each newly added skill check `requiredSkills[]`, `requiredStats[]` + `requiredScore`, `requiredConditions[]`, `requiredEffects[]` against the candidate state; return a structured result (per-entry: requirement type, target name/uuid, current vs required, status passed/unmet/overridden/unresolvable)
- [ ] 1.2 XP budget check: compute candidate stats via `CharacterService.calculateCharacter()` (reuse the preloaded entity maps, CHAR-080..082 — no second formula) and reject when the XP ability value would go below zero; report cost vs available
- [ ] 1.3 Resolve the XP ability via the app register config (slug-tolerant, name-match fallback) — no hardcoded UUIDs
- [ ] 1.4 Graceful handling of dangling references (deleted prerequisite skill/ability UUIDs): report as unresolvable, never throw (mirrors CALC-006)
- [ ] 1.5 Dependent-skill analysis for removals: when an assigned skill is removed, flag still-assigned skills whose requirements it satisfied as dependent-now-unmet (no cascade-delete, write still succeeds)

## 2. Server-authoritative write hook

- [ ] 2.1 Create `lib/Listener/CharacterRequirementListener.php` for OR `ObjectCreatingEvent` / `ObjectUpdatingEvent`, scoped to the character schema via the app's register/schema config; on unmet requirements call the event's rejection path (stop propagation + structured errors) so the OR write returns a 4xx with the itemised list
- [ ] 2.2 Register the listener in `lib/AppInfo/Application.php` (guard for the event classes existing so older OR deployments degrade to data-only instead of fataling)
- [ ] 2.3 Skip validation when the write does not change `skills[]`/`items[]`/`conditions[]`/`requirementOverrides[]` (pre-existing violations must not block unrelated edits)
- [ ] 2.4 Override enforcement in the listener: accept assignments covered by a matching `requirementOverrides[]` entry; reject empty `reason`; reject any write that adds/modifies override entries from a user not in the GM group; stamp `overriddenBy`/`overriddenAt` server-side

## 3. Schema + report endpoint

- [ ] 3.1 Add `requirementOverrides[]` (objects: `skill`, `reason`, `overriddenBy`, `overriddenAt`) to the `character` schema in `lib/Settings/larpingapp_register.json`; validate JSON stays well-formed
- [ ] 3.2 Add `GET /api/characters/{id}/requirement-report` to `lib/Controller/CharactersController.php` + `appinfo/routes.php` (`#[NoAdminRequired]` + per-object access via the OR-delegated fetch; correct auth posture per gate-5/gate-9): recompute the full report (unmet, overridden, dependent-now-unmet, unresolvable) for one character on demand
- [ ] 3.3 Annotate new/changed PHP methods with `@spec openspec/changes/skill-requirement-enforcement/...` (gate-16) and SPDX headers (gate-1)

## 4. Frontend

- [ ] 4.1 `src/modals/AddSkillToCharacter.vue`: pre-check the pending selection via the requirement-report logic (candidate state), list every unmet requirement + XP shortfall, disable plain save while unmet entries exist
- [ ] 4.2 Same pre-check pattern for `AddItemToCharacter.vue` and `AddConditionToCharacter.vue` (XP budget; condition/effect prerequisites where applicable)
- [ ] 4.3 GM override path in the Add-Skill modal: visible only to GM-group members; checkbox per unmet assignment + mandatory reason field; submits `requirementOverrides[]` with the write
- [ ] 4.4 Render server-side rejections (structured errors payload) as the same itemised list in the modals — never a generic "save failed"
- [ ] 4.5 Character detail: persistent warning panel fed by `GET /api/characters/{id}/requirement-report` listing dependent-now-unmet / no-longer-met skills with resolution hints (restore prerequisite, remove dependent, override)
- [ ] 4.6 i18n: English source keys for all new strings + nl translations (ADR-007/ADR-025)
- [ ] 4.7 Bump `appinfo/info.xml` `<version>` (immutable-cache bust for the new bundle)

## 5. Tests

- [ ] 5.1 PHPUnit `SkillRequirementServiceTest`: prerequisite pass/fail matrix (requiredSkills, requiredStats+requiredScore, requiredConditions, requiredEffects), same-write prerequisite satisfaction, XP shortfall, dangling-reference unresolvable, dependent-flagging on removal, validator-vs-engine XP agreement
- [ ] 5.2 PHPUnit listener test: rejection payload shape, diff-scoping (unrelated edit passes with pre-existing violation), override acceptance/empty-reason rejection/non-GM override rejection
- [ ] 5.3 Newman (`tests/integration/*.postman_collection.json`): direct API write without override → 4xx with itemised errors; with valid GM override → success; requirement-report endpoint shape (API assertions belong in Newman, not Playwright)
- [ ] 5.4 Playwright `tests/e2e/spec-coverage/`: Add-Skill modal lists unmet requirements and disables save; GM override flow with reason; detail-page warning after prerequisite removal (covers the UI scenarios in the delta spec; backend-only scenarios get `@e2e exclude` on their own line)
- [ ] 5.5 `composer check:strict` green; run hydra gates (incl. gate-16 spec coverage, gate-19 e2e coverage) — fix any pre-existing issues encountered in touched files

## 6. Spec sync

- [ ] 6.1 On archive, sync the rpg-system delta (SKILL-014 MODIFIED to enforced, SKILL-015/016 added) and the new `skill-requirement-enforcement` capability spec into `openspec/specs/`
- [ ] 6.2 Update `docs/FEATURES.md` wording if needed so "automatically apply restrictions" now matches reality (enforced with explicit GM override)

## Acceptance criteria

- A character write that adds a skill with unmet prerequisites or insufficient XP is rejected at the OpenRegister API level (not just in the UI) with an itemised unmet-requirements payload.
- The same assignment succeeds with an explicit GM `requirementOverrides[]` entry carrying a non-empty reason, and the override is visible in the character's OR audit trail.
- Removing a prerequisite skill never deletes dependent skills; the dependents are flagged in the requirement report and on the character detail page.
- The XP value the validator computes equals the persisted engine-recalculated XP for every accepted write (no parallel formula).
- All new strings ship in en + nl; PHPUnit, Newman, and Playwright coverage as in section 5; `composer check:strict` and hydra gates pass.
