# Tasks: characters-player-visibility

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Rules

- [x] 1.1 `lib/Settings/register.d/characters-player-visibility.json` with the character `authorization` block of D1 (REQ-CPV-001, REQ-CPV-002). Verify: `npm run check:register`; `tests/unit/Settings/CharacterVisibilityRulesTest.php` on the merged register; live: `tests/e2e/workflows/player-visibility.workflow.spec.ts` (draft hidden, PATCH on another sheet refused).
- [x] 1.2 Property `authorization` blocks of D2 in the same fragment (REQ-CPV-003, REQ-CPV-004). Verify: same unit test (field rules, and exactly name, type, description, approved and setting readable on a cast entry); live: the workflow spec above.
- [x] 1.3 Add the new `gamemasters` and `larpers` occurrences to the inventory in `openspec/architecture/adr-002-gm-authorization-single-group.md`. Verify: the inventory lists the fragment.

## 2. Cast page

- [x] 2.1 `src/manifest.d/characters-player-visibility.json` with the `Cast` index page and its menu entry (REQ-CPV-005). Verify: `npm run check:manifest`; Dutch and English labels via `npm run test:l10n`.

## 3. Proof

- [x] 3.1 PHPUnit or Newman for each server path in the D4 table (REQ-CPV-006). Verify: the run sheet stays game master only (`EventsControllerTest::testReturns403ForNonGm`) and the PDF admin only (`CharactersControllerTest::testDownloadPdfReturns403ForNonAdminUser`); both read through the mapper, so a game master's context keeps `slNotesPrivate`. See design, build notes.
- [x] 3.2 API workflow `tests/e2e/workflows/player-visibility.workflow.spec.ts` as player Anna: edits her own background, cannot open the private note, sees "Sir Bertram" on the Cast page without his background (REQ-CPV-001 to REQ-CPV-005). Verify: live run on an instance with this build; not run in this build lane (no instance), recipe in the PR.
- [x] 3.3 `docs/features/player-visibility.md`: who sees what (ADR-010; no screenshot, no instance on this build). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push; seed the two demo players of design.md.
