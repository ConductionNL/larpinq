# Tasks: worlds-copy-ruleset

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 6. -->

## 1. Copy

- [ ] 1.1 `WorldCopyService` with the D1 order, the id map and the two-pass skill save (REQ-WCR-001, REQ-WCR-002). Verify: PHPUnit on a fixture world with a skill cycle and a shared effect: every copied reference points at a copy, the shared one is untouched, holders are empty.
- [ ] 1.2 `WorldsController::copy()` and its route, game master guard via `Application::GM_GROUP`, status handling and the 2000 cap (REQ-WCR-003). Verify: PHPUnit for 201, 403 for a player, 422 above the cap; route-auth, no-admin-idor and semantic-auth hydra gates pass.

## 2. Page

- [ ] 2.1 `src/modals/CopyWorldModal.vue` and the header action on SettingDetail in `src/manifest.json` (REQ-WCR-001, REQ-WCR-003). Verify: `npm run check:manifest`; Playwright `tests/e2e/copy-world.spec.ts` copies Aldmoor to "Aldmoor season 2".

## 3. Strings and docs

- [ ] 3.1 Dutch and English strings (REQ-WCR-001). Verify: `npm run test:l10n`.
- [ ] 3.2 `docs/features/copy-world.md` (ADR-010). Verify: the docs build renders it.
- [ ] 3.3 Add `Application::GM_GROUP` and use it in the new controller (ADR-002 decision 1). Verify: `grep -rn "private const GM_GROUP" lib/Controller/WorldsController.php` finds nothing.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
