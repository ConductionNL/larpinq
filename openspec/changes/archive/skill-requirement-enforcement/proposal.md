---
status: draft
---

# Skill requirement enforcement (XP budget + prerequisites)

## Why

The app description promises rule enforcement: info.xml says "Automatically
calculate abilities and XP" and `docs/FEATURES.md` says "Define XP costs for
skills and abilities, and **automatically apply restrictions**". Today this is
false advertising: `openspec/specs/rpg-system/spec.md` SKILL-014 explicitly
documents that "prerequisite validation is data-only -- the system stores
prerequisites but does NOT enforce them during character assignment". A GM can
assign "Advanced Swordplay" to a character that lacks "Basic Swordplay" and a
skill whose XP-cost effect drives the character's XP ability negative, and
nothing pushes back. This is the highest-severity gap in the 2026-06-11
feature re-evaluation (`FEATURE-REEVALUATION-2026-06-11/larpingapp.md`).

The data model already carries everything needed: skills declare
`requiredSkills[]`, `requiredStats[]`, `requiredConditions[]`,
`requiredEffects[]`, and `requiredScore`; XP costs are expressed as negative
effects targeting the XP ability; the stat engine
(`CharacterService.calculateCharacter()`) already computes the resulting XP
deterministically. What is missing is the enforcement layer on character
assignment writes.

Enforcement MUST be server-authoritative: characters are written through
OpenRegister's generic objects/GraphQL API, so a frontend-only check is
trivially bypassable by any API client.

## What Changes

- **Server-side validation on character assignment writes.** A new
  `SkillRequirementService` validates the candidate character state whenever
  `skills[]`, `items[]`, or `conditions[]` change. It is hooked into
  OpenRegister's vetoable pre-write events (`ObjectCreatingEvent` /
  `ObjectUpdatingEvent`, both `StoppableEventInterface` with structured
  errors), scoped to the character schema, so every write path (UI, REST,
  GraphQL) is covered.
- **XP budget check.** The candidate character's stats are computed with the
  existing `CharacterService.calculateCharacter()` (extend, don't duplicate —
  see design.md); if the XP ability's resulting value would go below zero, the
  write is rejected with the shortfall listed.
- **Prerequisite check.** For each newly assigned skill: `requiredSkills[]`
  must all be present in the candidate `skills[]`, `requiredStats[]` /
  `requiredScore` must be met by the candidate computed stats, and
  `requiredConditions[]` / `requiredEffects[]` must be satisfied. Unmet
  entries are returned as a structured, human-readable list.
- **Clear UI feedback.** The Add-Skill/Item/Condition modals pre-check the
  selection and list every unmet requirement (and the XP shortfall) before
  save; server rejections render the same structured list. Validation
  messaging ships nl + en (English source keys).
- **Explicit, audited GM override.** A GM can override per assignment via a
  `requirementOverrides[]` field on the character (skill ref + reason +
  overriddenBy + timestamp). Overrides are part of the character object, so
  the existing OpenRegister object audit trail (CHAR-110) records who
  overrode what, when, and why. No silent bypass: the override must be
  explicit in the write.
- **Prerequisite-removal validation report.** Removing a skill that other
  assigned skills depend on does NOT cascade-delete and is NOT hard-blocked;
  the dependent skills are flagged in a validation report (new
  `GET /api/characters/{id}/requirement-report` endpoint) and surfaced as a
  warning on the character detail page until resolved or overridden.

## Impact

- Affected specs: `skill-requirement-enforcement` (new capability);
  `rpg-system` (MODIFIED — SKILL-014 flips from data-only to enforced, with
  override and report requirements added).
- Affected code (apply phase, NOT here):
  - `lib/Service/SkillRequirementService.php` (new) + PHPUnit tests
  - `lib/Listener/CharacterRequirementListener.php` (new) registered in
    `lib/AppInfo/Application.php` for OR `ObjectCreatingEvent` /
    `ObjectUpdatingEvent`
  - `lib/Controller/CharactersController.php` + `appinfo/routes.php`
    (requirement-report endpoint)
  - `lib/Settings/larpingapp_register.json` (character schema gains
    `requirementOverrides[]`)
  - `src/modals/AddSkillToCharacter.vue`, `AddItemToCharacter.vue`,
    `AddConditionToCharacter.vue`, character detail view (warning surface)
  - `l10n/` nl + en strings; `appinfo/info.xml` version bump (cache-bust)
- Depends on: OpenRegister pre-write hook events (already shipped —
  `ObjectCreatingEvent`/`ObjectUpdatingEvent` implement
  `StoppableEventInterface` with an errors payload).
- Relates to: `larpingapp-notifications` (the `gamemasters` group concept),
  ABIL-011 (`allowed_negative` engine bug — XP floor is enforced here at the
  write boundary for the XP budget; the general engine flag stays a separate
  known bug).
- No change to the stat-calculation order or audit-trail format (CALC-001..007
  untouched); validation consumes the engine, it does not fork it.
