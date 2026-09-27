# Tasks: characters-stat-sheet-panel

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Endpoint

- [ ] 1.1 Record the source type and id in each audit entry of `CharacterService::applyEntityEffects()` (REQ-CSP-001). Verify: the existing CharacterService PHPUnit suite stays green; a new test asserts `source` and `sourceId`.
- [ ] 1.2 `CharacterStatsPresenter` and `CharactersController::stats()` with its route, `#[NoAdminRequired]`, 404 for an unreadable id (REQ-CSP-001, REQ-CSP-003, REQ-CSP-004). Verify: PHPUnit for the shape, and that `xp.left` equals `evaluateBudget()` value for the same character; the route-auth and route-reachability hydra gates pass.

## 2. Tab

- [ ] 2.1 `src/views/CharacterStatSheet.vue` registered as `kind: 'section'`, and the Stats tab on CharacterDetail in `src/manifest.json` (REQ-CSP-001, REQ-CSP-002). Verify: vitest for the rendering of positive, negative and no modifiers; `npm run check:manifest`.
- [ ] 2.2 Playwright `tests/e2e/character-stats.spec.ts` on the seeded "Mirela the Wanderer": strength 14 with two modifiers, XP 40, 10, 30 (REQ-CSP-001, REQ-CSP-002). Verify: passes locally.

## 3. Specs, strings, docs

- [ ] 3.1 Point the two requirements at `openspec/specs/larping-skill-widget/spec.md:182-233` to this change and replace their `@e2e exclude` with the new test. Verify: the hydra spec-coverage and e2e-coverage gates pass on the diff.
- [ ] 3.2 Dutch and English strings for the tab (REQ-CSP-002). Verify: `npm run test:l10n`.
- [ ] 3.3 `docs/features/character-stats.md` with a screenshot of the tab (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
