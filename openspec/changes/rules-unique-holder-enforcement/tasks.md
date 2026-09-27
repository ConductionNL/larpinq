# Tasks: rules-unique-holder-enforcement

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. The check

- [ ] 1.1 `UniqueHolderService::otherHolders()` with the bounded lookup on both sides of the relation (REQ-UHE-001, REQ-UHE-002). Verify: PHPUnit with a fake fetcher, including the batch fallback and the stop at the first other holder.
- [ ] 1.2 `UniqueHolderListener` for character writes that add an item or condition, registered in `Application::register()` behind `class_exists()` (REQ-UHE-001, REQ-UHE-002). Verify: PHPUnit with the real `ObjectCreatingEvent` and `ObjectUpdatingEvent` classes, not fakes.
- [ ] 1.3 The same listener for item and condition writes: `characters[]` with two holders, and `unique` switched on while two hold it (REQ-UHE-003). Verify: PHPUnit per row of the D3 table.

## 2. Surfaces

- [ ] 2.1 Error messages on the character and item pages, Dutch and English (REQ-UHE-004). Verify: `npm run test:l10n`; Playwright `tests/e2e/unique-holder.spec.ts` adds the seeded unique item to a second character and sees the holder named.
- [ ] 2.2 `occ larpinq:unique-holders:check` listing today's conflicts (REQ-UHE-005). Verify: PHPUnit for the command output on a fixture with one conflict.
- [ ] 2.3 Seed one unique item with one holder in the demo data. Verify: the Playwright test above finds the seeded holder.
- [ ] 2.4 `docs/features/unique-items-and-conditions.md` explains the rule and the check command (ADR-010). Verify: the page renders in the docs build.

Quality reminders (not tracked as tasks): `composer check:strict` once before push; one live write against a running OpenRegister before hand-back.
