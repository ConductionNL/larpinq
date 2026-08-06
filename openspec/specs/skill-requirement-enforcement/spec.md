---
status: implemented
---

# Skill Requirement Enforcement

## Purpose

Enforce skill XP costs and prerequisites when skills, items, or conditions are
assigned to a character, server-authoritatively, with clear UI feedback, an
explicit audited GM override, and a flag-don't-cascade report when a
prerequisite is later removed. Fulfils the docs/FEATURES.md promise
"automatically apply restrictions" that rpg-system SKILL-014 documents as
unenforced. Validation consumes the existing stat engine
(`CharacterService.calculateCharacter()`, rpg-system CALC-001..007) — it does
not introduce a second calculation.

## Requirements

### Requirement: Character assignment writes MUST be validated server-side

Whenever a character write changes `skills[]`, `items[]`, or `conditions[]`, the candidate character state MUST be validated by `SkillRequirementService` before the write is persisted. The validation MUST be hooked into OpenRegister's vetoable pre-write events (`ObjectCreatingEvent` / `ObjectUpdatingEvent`) scoped to the character schema, so that every write path (SPA, REST objects API, GraphQL) is covered. A rejected write MUST return a structured error payload listing every unmet requirement. Writes that do not change these association arrays MUST NOT be blocked by pre-existing violations.

#### Scenario: Direct API write bypassing the UI is rejected

- GIVEN skill "Advanced Swordplay" with requiredSkills=["basic-swordplay"]
- AND character "Squire" does not have "Basic Swordplay"
- WHEN a client PUTs the character via the OpenRegister objects API with skills += ["advanced-swordplay"] and no override
- THEN the write MUST be rejected before persistence
- AND the error payload MUST list "Basic Swordplay" as an unmet required skill
- AND the character's stored skills[] MUST be unchanged

#### Scenario: Unrelated edit is not blocked by a pre-existing violation

- GIVEN character "Legacy Hero" already violates a prerequisite (assigned before enforcement shipped)
- WHEN a GM updates only the character's background text
- THEN the write MUST succeed
- AND the pre-existing violation MUST appear only in the requirement report, not as a write error

### Requirement: XP budget MUST be validated using the existing stat engine

When validating an assignment, the candidate character's stats MUST be computed via `CharacterService.calculateCharacter()` on the candidate data. If the XP ability's resulting value would be below zero, the write MUST be rejected with the XP shortfall stated. The validator MUST NOT implement a parallel XP formula; earned-minus-spent is by definition the engine's computed XP value (XP costs are negative effects targeting the XP ability, applied in CALC-002 order). The XP ability MUST be identified via the app's register configuration (with a name-match fallback), not a hardcoded UUID.

#### Scenario: Assignment rejected when XP budget is exceeded

- GIVEN ability "XP" with base 0 and character "Novice" with earned XP totalling 10
- AND skill "Master Smithing" carries effect "XP Cost -15" (negative, targeting XP)
- WHEN "Master Smithing" is assigned to "Novice" without an override
- THEN the candidate stats MUST be computed via calculateCharacter()
- AND the write MUST be rejected because XP would be -5
- AND the error MUST state the shortfall (cost 15, available 10)

#### Scenario: Affordable assignment passes

- GIVEN character "Veteran" with computed XP 40
- AND skill "Shield Mastery" with effect "XP Cost -10"
- WHEN "Shield Mastery" is assigned
- THEN validation MUST pass
- AND the persisted character's recalculated XP MUST be 30, identical to the value the validator computed

### Requirement: Skill prerequisites MUST be enforced on assignment

For each skill newly added to a character, the validator MUST check: `requiredSkills[]` are all present in the candidate `skills[]`; `requiredStats[]` with `requiredScore` are met by the candidate computed stats; `requiredConditions[]` are present in the candidate `conditions[]`; and `requiredEffects[]` are satisfied by the candidate's active effects. All checks MUST evaluate the candidate (post-write) state, so prerequisites satisfied by other entries in the same write count. Any unmet entry MUST reject the write (absent an override) and be itemised in the error payload.

#### Scenario: Missing required skill blocks assignment

- GIVEN skill "Advanced Swordplay" with requiredSkills=["basic-swordplay"], requiredScore=5
- AND character "Page" has no skills and Strength 3
- WHEN "Advanced Swordplay" is assigned without an override
- THEN the write MUST be rejected
- AND the error payload MUST itemise both unmet entries: required skill "Basic Swordplay" and required score 5 (current 3)

#### Scenario: Prerequisite satisfied within the same write

- GIVEN skill "Advanced Swordplay" requires "Basic Swordplay"
- AND character "Recruit" has neither skill
- WHEN one write adds both "Basic Swordplay" and "Advanced Swordplay" to skills[]
- THEN validation MUST evaluate the candidate skills[] containing both
- AND the write MUST be accepted (XP budget permitting)

#### Scenario: Required stat threshold met by candidate stats

- GIVEN skill "Heavy Armor" with requiredStats=["strength-uuid"] and requiredScore=12
- AND character "Knight" whose candidate computed Strength is 14 (base 10 + skill effects in the same write)
- WHEN "Heavy Armor" is assigned
- THEN the threshold check MUST use the candidate computed value 14
- AND validation MUST pass

### Requirement: Unmet requirements MUST be clearly surfaced in the UI

The Add-Skill/Item/Condition modals MUST pre-check the pending selection and display every unmet requirement (required skills/stats/conditions/effects and XP shortfall) as a human-readable list before save, disabling plain save while unmet entries exist. A server-side rejection MUST be rendered as the same itemised list, never as a generic error. All messages MUST ship in English source keys with nl translations.

#### Scenario: Modal lists unmet requirements before save

- GIVEN character "Page" lacks "Basic Swordplay" and has XP 4
- WHEN the user selects "Advanced Swordplay" (XP cost 10) in the Add-Skill modal
- THEN the modal MUST list: required skill "Basic Swordplay" missing, and XP shortfall (needs 10, has 4)
- AND the plain save action MUST be disabled while unmet requirements exist

#### Scenario: Server rejection renders the itemised list

- GIVEN the frontend pre-check was bypassed or stale
- WHEN the server rejects the assignment write
- THEN the modal MUST render the server's itemised unmet-requirements list
- AND MUST NOT show only a generic "save failed" message

### Requirement: GM override MUST be explicit and audited

A game master MUST be able to override requirement enforcement per assignment via a `requirementOverrides[]` entry on the character (`{skill, reason, overriddenBy, overriddenAt}`) supplied in the write. The reason MUST be non-empty. The validator MUST skip enforcement only for assignments with a matching override entry and MUST report them as "overridden", not "passed". Because overrides are part of the character object, the OpenRegister object audit trail (character-management CHAR-110) MUST record who overrode what, when, and why. Only members of the GM group may add or modify override entries; a non-GM write touching `requirementOverrides[]` MUST be rejected. There MUST be no implicit GM bypass.

#### Scenario: GM override allows an exceptional assignment

- GIVEN character "Chosen One" lacks the prerequisites for skill "Dragon Speech"
- WHEN a GM assigns "Dragon Speech" with requirementOverrides containing {skill: "dragon-speech", reason: "Plot reward from Summer LARP finale"}
- THEN the write MUST be accepted
- AND the override entry (skill, reason, overriddenBy, overriddenAt) MUST be stored on the character
- AND the character's OpenRegister audit trail MUST show the write including the override entry

#### Scenario: Override without a reason is rejected

- GIVEN a GM assigns a skill with an override entry whose reason is empty
- WHEN the write is validated
- THEN it MUST be rejected with an error stating that an override reason is required

#### Scenario: Non-GM cannot write overrides

- GIVEN a user who is not in the GM group
- WHEN their character write contains a new requirementOverrides entry
- THEN the write MUST be rejected

### Requirement: Removing a prerequisite MUST flag dependents, not cascade

When a skill is removed from a character while other assigned skills list it in `requiredSkills[]` (or its removal breaks their stat/condition/effect requirements), the write MUST succeed, the dependent skills MUST NOT be auto-removed, and the validation result MUST flag each dependent skill as dependent-now-unmet. A `GET /api/characters/{id}/requirement-report` endpoint MUST recompute the full report on demand (also catching drift from later edits to a skill's prerequisite definition), and the character detail page MUST show a persistent warning while unresolved, listing the affected skills and the resolution options (restore prerequisite, remove dependent, or override).

#### Scenario: Prerequisite removal flags the dependent skill

- GIVEN character "Duellist" has "Basic Swordplay" and "Advanced Swordplay" (which requires it)
- WHEN "Basic Swordplay" is removed from the character
- THEN the write MUST succeed
- AND "Advanced Swordplay" MUST remain assigned (no cascade-delete)
- AND the validation result MUST flag "Advanced Swordplay" as dependent-now-unmet
- AND the character detail page MUST show a warning listing "Advanced Swordplay"

#### Scenario: Requirement report catches later prerequisite tightening

- GIVEN character "Old Guard" has skill "War Veteran" assigned, valid at assignment time
- WHEN a GM later edits the "War Veteran" skill to add requiredScore=20 the character does not meet
- AND the requirement report for "Old Guard" is requested
- THEN the report MUST flag "War Veteran" as no-longer-met
- AND the character's stored data MUST be unchanged by the report

### Requirement: Validation MUST stay consistent with the stat engine

All stat-dependent checks (XP budget, `requiredStats[]`/`requiredScore`) MUST consume `CharacterService.calculateCharacter()` output for the candidate state. The change MUST NOT alter the calculation order, audit-entry format, or graceful-skip semantics defined in rpg-system CALC-001..007, and MUST NOT introduce a second effect-application implementation. Orphaned references (deleted prerequisite skills/abilities) MUST be skipped gracefully in validation, mirroring CALC-006: a dangling `requiredSkills[]` UUID is reported as unmet-but-unresolvable in the report, never a crash.

#### Scenario: Validator and engine agree on the persisted value

- GIVEN any accepted assignment write
- WHEN the character is persisted and stats recalculated (CHAR-010)
- THEN the persisted XP value MUST equal the value the validator computed for the candidate state

#### Scenario: Dangling prerequisite reference does not crash validation

- GIVEN skill "Relic Lore" has requiredSkills=["deleted-skill-uuid"] for a skill that no longer exists
- WHEN "Relic Lore" is assigned to a character
- THEN validation MUST NOT throw
- AND the dangling prerequisite MUST be reported as unresolvable (GM decides via override or skill cleanup)

### Requirement: The enforcement listeners MUST actually be registered, regardless of app load order

`AppInfo\Application::register()` MUST put OpenRegister's PSR-4 prefix on the
composer autoloader — via `OpenRegisterAutoloader::register()`, which calls
`OC_App::registerAutoloading('openregister', …)` — BEFORE any
`class_exists('OCA\OpenRegister\…')` probe that gates a listener registration.

Nextcloud registers apps in sorted order: `OC_App::getEnabledApps()` does
`sort($apps)` and `Coordinator::registerApps()` walks that list calling
`OC_App::registerAutoloading($appId, $path)` and then `$app->register()` for one
app at a time. `larpingapp` sorts before `openregister`, so `OCA\OpenRegister\`
is not autoloadable inside LarpingApp's own `register()` on a healthy instance
with OpenRegister enabled. Without the prelude every probe answers `false` — not
"not loaded yet", just `false`, indistinguishable from OpenRegister being absent
— and the `ObjectCreatingEvent` / `ObjectUpdatingEvent` listeners that carry this
capability's server-side enforcement are never registered at all.

A validation that is never invoked is indistinguishable from having no
validation: the enforcement here is server-side precisely because the client
cannot be trusted, so its silent absence is a security gap, not a missing
feature.

`OC_App::registerAutoloading()` is idempotent and touches only the autoloader.
`IAppManager::loadApp('openregister')` MUST NOT be used instead: it marks
OpenRegister loaded and calls `Coordinator::bootApp()`, booting OpenRegister
before its own `register()` has run. The prelude MUST NOT throw under any
instance state — an exception escaping it would abort the whole `register()` and
leave every listener unregistered.

#### Scenario: OpenRegister enabled, LarpingApp registering ahead of it

- GIVEN an instance with OpenRegister enabled, and LarpingApp's `register()`
  running at its sorted position ahead of `openregister`
- WHEN `class_exists('OCA\OpenRegister\Event\ObjectCreatingEvent')` is evaluated
- THEN it MUST answer `true`, because the prelude has already registered
  OpenRegister's prefix
- AND the `CharacterRequirementListener` MUST be registered for both
  `ObjectCreatingEvent` and `ObjectUpdatingEvent`
- @e2e exclude composition-root load order — observable only during the app
  registration phase, before any HTTP request or browser session exists;
  asserted by tests/unit/AppInfo/OpenRegisterAutoloaderTest.php

#### Scenario: OpenRegister genuinely absent

- GIVEN an instance with OpenRegister not installed
- WHEN the prelude runs
- THEN it MUST return `false` rather than throw, and the `class_exists()` guards
  MUST then correctly skip the OpenRegister-dependent listeners
- @e2e exclude composition-root load order — asserted by
  tests/unit/AppInfo/OpenRegisterAutoloaderTest.php
