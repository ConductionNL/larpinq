# Tasks: rules-rulebook-and-spell-lists

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Lists and budget

- [ ] 1.1 Fragment: `skillList`, `skill.list`, `hiddenFromRulebook` on skill, item and condition (REQ-RRS-001, REQ-RRS-004). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [ ] 1.2 `SkillRequirementService`: one budget per cost ability, `budgets[]` in the report, refusal on any negative pool (REQ-RRS-002). Verify: the existing SkillRequirementService PHPUnit suite stays green; new tests for a spell refused on mana while XP is fine.
- [ ] 1.3 Seed the Aldmoor lists, spells and chapters of design.md. Verify: the seed imports cleanly.

## 2. Pages

- [ ] 2.1 `src/manifest.d/rules-rulebook-and-spell-lists.json`: Skill lists index and detail, Rulebook route and menu entries; Skills index list column in `src/manifest.json` (REQ-RRS-001, REQ-RRS-003). Verify: `npm run check:manifest`.
- [ ] 2.2 `src/views/Rulebook.vue` as a registry `page` with print styles (REQ-RRS-003, REQ-RRS-004). Verify: vitest renders chapters then reference and leaves out hidden objects; Playwright `tests/e2e/rulebook.spec.ts` as a player reads "Casting a spell" and sees "Firebolt, 3 mana".

## 3. Portal

- [ ] 3.1 `rulebookChapters` and `skillLists` in `PortalContributionProvider` (REQ-RRS-005). Verify: provider PHPUnit shape and whitelist tests.

## 4. Strings and docs

- [ ] 4.1 Dutch and English strings (REQ-RRS-001). Verify: `npm run test:l10n`.
- [ ] 4.2 `docs/features/rulebook-and-spell-lists.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
