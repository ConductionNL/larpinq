# Tasks: characters-multiple-builds

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Data and check

- [ ] 1.1 `lib/Settings/register.d/characters-multiple-builds.json`: `characterBuild` with rules and materialised owner (REQ-CMB-001, REQ-CMB-004). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [ ] 1.2 `GET /api/builds/{id}/report` in `CharactersController` with `#[NoAdminRequired]` and 404 for unreadable builds (REQ-CMB-002, REQ-CMB-004). Verify: PHPUnit asserts no write happens and the report equals `validate()` on the composed candidate; route-auth and route-reachability hydra gates pass.
- [ ] 1.3 Seed data of design.md. Verify: the seed imports cleanly.

## 2. Pages

- [ ] 2.1 `src/manifest.d/characters-multiple-builds.json` with BuildDetail, `src/views/BuildReport.vue` (`kind: 'section'`), and the Builds list on CharacterDetail in `src/manifest.json` (REQ-CMB-001, REQ-CMB-002). Verify: `npm run check:manifest`; vitest for the report rendering.
- [ ] 2.2 `src/modals/ApplyBuildModal.vue` for game masters (REQ-CMB-003). Verify: Playwright `tests/e2e/character-builds.spec.ts` applies "Alchemist path" and the character's skills change; applying "Winter campaign" shows the budget refusal.

## 3. Strings and docs

- [ ] 3.1 Dutch and English strings (REQ-CMB-001). Verify: `npm run test:l10n`.
- [ ] 3.2 `docs/features/character-builds.md` with a screenshot (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
