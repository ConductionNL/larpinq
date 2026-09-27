# Tasks: characters-plot-threads-and-writing

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Data

- [ ] 1.1 Fragment with `plot` (lifecycle), `plotPart` (rules, materialised owner) and `character.writer` / `writingStep` (REQ-CPW-001 to REQ-CPW-004). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [ ] 1.2 Newman: as Anna, the part of "Mirela the Wanderer" returns `playerText` and no `privateText`; the part of "Sir Bertram" is not returned (REQ-CPW-002). Verify: the collection passes.
- [ ] 1.3 Seed data of design.md. Verify: the seed imports cleanly.

## 2. Pages

- [ ] 2.1 `src/manifest.d/characters-plot-threads-and-writing.json`: Plots pages and menu entry; CharacterDetail and CharacterRosterReport edits in `src/manifest.json` (REQ-CPW-001, REQ-CPW-003, REQ-CPW-004). Verify: `npm run check:manifest`.
- [ ] 2.2 Playwright `tests/e2e/plots-and-writing.spec.ts`: a game master creates a plot with two parts and moves it to ready; the report counts it (REQ-CPW-001, REQ-CPW-004). Verify: passes locally.

## 3. Strings and docs

- [ ] 3.1 Dutch and English strings (REQ-CPW-001). Verify: `npm run test:l10n`.
- [ ] 3.2 `docs/features/plots-and-writing.md` with a screenshot (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
