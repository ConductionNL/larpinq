# Tasks: communication-help-desk

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Data

- [ ] 1.1 Fragment: `helpQuestion` (lifecycle, notifications) and `helpMessage` with rules (REQ-CHD-001 to REQ-CHD-004). Verify: `npm run check:register`; `npm run check:schema-l10n`; notification-dialect gate passes; Newman: another player reads no question of Anna.
- [ ] 1.2 Seed the question of design.md. Verify: the seed imports cleanly.

## 2. Thread

- [ ] 2.1 `HelpMessageListener` and the closed-question check (REQ-CHD-002, REQ-CHD-003). Verify: PHPUnit with the real OpenRegister event classes for answer, reopen, closed.

## 3. Pages and portal

- [ ] 3.1 `src/manifest.d/communication-help-desk.json`: Help and Help desk pages; the Dashboard count in `src/manifest.json` (REQ-CHD-001, REQ-CHD-003). Verify: `npm run check:manifest`.
- [ ] 3.2 `askQuestion` and `myQuestions` in `PortalContributionProvider`, with the declared notification (REQ-CHD-004). Verify: provider PHPUnit shape and whitelist tests.
- [ ] 3.3 Playwright `tests/e2e/help-desk.spec.ts`: Anna asks, Joris answers, Anna is notified and replies, the question reopens (REQ-CHD-001 to REQ-CHD-003). Verify: passes locally.

## 4. Strings and docs

- [ ] 4.1 Dutch and English strings (REQ-CHD-001). Verify: `npm run test:l10n`.
- [ ] 4.2 `docs/features/help-desk.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
