# Tasks: communication-help-desk

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Data

- [x] 1.1 Fragment: `helpQuestion` (lifecycle, notifications) and `helpMessage` with rules (REQ-CHD-001 to REQ-CHD-004). Verify: `npm run check:register`; `npm run check:schema-l10n`; notification-dialect gate passes; Newman: another player reads no question of Anna.
- [x] 1.2 Seed the question of design.md. Verify: the seed imports cleanly.

## 2. Thread

- [x] 2.1 `HelpMessageListener` and the closed-question check (REQ-CHD-002, REQ-CHD-003). Verify: PHPUnit with the real OpenRegister event classes for answer, reopen, closed.

## 3. Pages and portal

- [x] 3.1 `src/manifest.d/communication-help-desk.json`: Help and Help desk pages; the Dashboard count in `src/manifest.json` (REQ-CHD-001, REQ-CHD-003). Verify: `npm run check:manifest`.
      Help (the player's own questions), Help desk (quick filters open / answered / closed, oldest activity first, an `age` cell widget marks a question without activity for more than 3 days) and the question page with its message thread; the Dashboard stat "Open questions". `npm run check:manifest` passes and the merged manifest validates against the v2 schema; pinned by `tests/vitest/helpDeskManifest.spec.js`, `tests/vitest/ageCell.spec.js` and the re-recorded `effectiveManifest.golden.json`.
- [x] 3.2 `askQuestion` and `myQuestions` in `PortalContributionProvider`, with the declared notification (REQ-CHD-004). Verify: provider PHPUnit shape and whitelist tests.
      `askQuestion` (create, stamped through the `ownerRef` claim; subject, body, event), `myQuestions` and `myQuestionMessages` (the answers, scoped by the `ownerRef` HelpMessageListener now copies onto each message), a portal Help page, and the `larpinq.question.answered` rule on `status` with claim-addressed recipients (portaliq REQ-NAP-012). A portal question names no player, so `player` is no longer required and the listener takes the player from the stamped `ownerRef`. Pinned by `PortalContributionProviderTest` (shape, whitelist against the register, golden) and `HelpMessageListenerTest`.
- [ ] 3.3 Playwright `tests/e2e/help-desk.spec.ts`: Anna asks, Joris answers, Anna is notified and replies, the question reopens (REQ-CHD-001 to REQ-CHD-003). Verify: passes locally.

## 4. Strings and docs

- [x] 4.1 Dutch and English strings (REQ-CHD-001). Verify: `npm run test:l10n`.
      The schema strings and the new page strings are in all 37 catalogues (`npm run test:l10n` at full parity, `npm run check:l10n-js` up to date, `npm run check:schema-l10n` at its baseline).
- [x] 4.2 `docs/features/help-desk.md` (ADR-010). Verify: the docs build renders it.
      Written and listed in `docs/features/README.md`; the docs build was not run here.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
