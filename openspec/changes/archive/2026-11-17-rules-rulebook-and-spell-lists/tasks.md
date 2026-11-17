# Tasks: rules-rulebook-and-spell-lists

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Lists and budget

- [x] 1.1 Fragment: `skillList`, `skill.list`, `hiddenFromRulebook` on skill, item and condition (REQ-RRS-001, REQ-RRS-004). Verify: `npm run check:register`; `npm run check:schema-l10n`.
  > `register.d/rules-rulebook-and-spell-lists.json`: `skillList` (`larping_skill_list`; read by game masters, rules marshals and larpers, written by game masters and rules marshals, deleted by game masters; exportable), `skill.list` and `hiddenFromRulebook` on skill, item and condition. `check:schema-l10n` stays at its baseline: the 12 new schema strings are in `l10n/en.json` and `l10n/nl.json`.
- [x] 1.2 `SkillRequirementService`: one budget per cost ability, `budgets[]` in the report, refusal on any negative pool (REQ-RRS-002). Verify: the existing SkillRequirementService PHPUnit suite stays green; new tests for a spell refused on mana while XP is fine.
  > `SkillListPools::budgets()` gives one block per pool (XP, then each list's `costAbility`); the result keeps `budget` and adds `budgets[]` and `overspentPools`; any overspent pool refuses the write, and `CharacterRequirementListener` reports the overspent pools. Tests: `tests/unit/Service/SkillRequirementPoolsTest.php` (out of mana while XP is fine, shortfall 1 on Mana), two cases in the listener golden; the existing SkillRequirementService suite stays green.
- [x] 1.3 Seed the Aldmoor lists, spells and chapters of design.md. Verify: the seed imports cleanly.
  > In the demo descriptor `lib/Settings/larpinq_mock_register.json`: world Aldmoor, ability Mana, lists Martial skills (XP), Arcane spells (Mana) and Divine powers (XP), Arcane lore, Firebolt (3 mana) and Ward (2 mana) through cost effects on Mana, Blood rite hidden from the rulebook, and the rules chapters "How combat works" and "Casting a spell" for players, linked by `@ref:` tokens. `tests/unit/Settings/RulebookSeedTest.php` checks the shape, that every token names a seeded object, and validates each against its merged schema; the live import is left for an instance.

## 2. Pages

- [x] 2.1 `src/manifest.d/rules-rulebook-and-spell-lists.json`: Skill lists index and detail, Rulebook route and menu entries; Skills index list column in `src/manifest.json` (REQ-RRS-001, REQ-RRS-003). Verify: `npm run check:manifest`.
  > Skill lists index (world-scoped, ordered) and detail with the skills in the list, the Rulebook route `/rulebook/:world?`, menu entries relocated into Mechanics and World in `src/menu-layout.json`, and Name, List, Effect and World columns on the Skills index. `npm run check:manifest` passes; `tests/vitest/fixtures/effectiveManifest.golden.json` records the three new pages, the two menu entries and the changed Skills page.
- [x] 2.2 `src/views/Rulebook.vue` as a registry `page` with print styles (REQ-RRS-003, REQ-RRS-004). Verify: vitest renders chapters then reference and leaves out hidden objects; Playwright `tests/e2e/rulebook.spec.ts` as a player reads "Casting a spell" and sees "Firebolt, 3 mana".
  > `src/views/Rulebook.vue` (registry `page`), built by `src/services/rulebook.js`: rules chapters in order through `cnRenderMarkdown`, then abilities, skill lists with cost ("Firebolt, 3 mana"), pool and prerequisites by name, listless skills under General skills, items and conditions, hidden objects left out; print starts each chapter on its own page. `tests/vitest/rulebook.spec.js` (11 tests). The Playwright scenario needs a live instance and is not written here.

## 3. Portal

- [x] 3.1 `rulebookChapters` and `skillLists` in `PortalContributionProvider` (REQ-RRS-005). `rulebookChapters` shipped with portal-my-larp-pages; `skillLists` waits on 1.1. Verify: provider PHPUnit shape and whitelist tests.
  > `skillLists` (public, `hiddenFromRulebook` false, ordered) on the rulebook portal page before the skill catalogue, which now carries each skill's `list`. Provider tests: collection order, public scope, the whitelist-against-register drift test, `testSkillListsAreInThePortalWithoutTheHiddenOnes`, and the recorded golden. The existing skill, item and condition catalogues keep their unfiltered reads: objects saved before the flag existed may hold no value, which an equality filter on false would drop.

## 4. Strings and docs

- [x] 4.1 Dutch and English strings (REQ-RRS-001), and the 27 rulebook strings in the other 35 shipped locales so parity holds. Verify: `npm run test:l10n`.
  > English and Dutch are done for every new string (schema strings, the Rulebook page, the manifest labels); `test:l10n` finds every used key. Left: its parity step requires the other 34 locales of `l10n/*.json` too, and they lack the 27 keys this change adds (development is at full parity), so `npm run test:l10n` stays red until they are translated.
- [x] 4.2 `docs/features/rulebook-and-spell-lists.md` (ADR-010). Verify: the docs build renders it.
  > Lists, one pool per list, the rulebook and printing, secret rules, the portal and the demo data; linked from `docs/features/README.md`. The docs site build was not run here.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
