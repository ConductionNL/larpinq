# Tasks: admin-phone-friendly-pages

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 6. -->

- [x] 1.1 Playwright project `phone` and `tests/e2e/phone/overflow.spec.ts` over the listed pages (REQ-APF-001). Verify: the suite runs and reports each page; failing pages listed in the PR.
- [x] 1.2 `EventRoster.vue` phone layout: stacked rows, 44 pixel buttons, full-width scan field (REQ-APF-002). Verify: the phone suite passes for the Check-in tab; the hydra stylelint and semantic-controls gates pass.
  - Built 21 Nov in `src/views/EventRoster.vue`: inside the roster's 600 pixel container query the scan panel spans the width, the code field and its button stack, the code text is 16 pixels so the phone does not zoom, and the scan buttons and check-in actions are at least 44 pixels. The rows themselves are CnDataTable's own narrow layout. `tests/vitest/eventRosterPhoneLayout.spec.js` pins the rules; stylelint passes. The live run of 25 Nov showed the rows did not stack (the Check in button sat at x=877 on a 360 pixel screen): EventRoster now passes CnDataTable's `stackOnPhone`, and the phone suite asserts the actions are on screen. Phone suite 8/8 on a sandbox instance (Nextcloud 34, MariaDB).
- [x] 1.3 The same container styles in larpinq's other registered components (REQ-APF-001). Verify: the phone suite passes for CharacterDetail with the Stats tab, and the Casting and Award XP tabs.
- [x] 1.4 Report overflow in nextcloud-vue components upstream and mark the tests with the issue (REQ-APF-001). Verify: each marked test names an issue number.
  - 25 Nov, from the live phone run: ConductionNL/nextcloud-vue#2355 (sidebar tab labels cut off), #2356 (dashboard widgets stay in columns on a phone), #2357 (index filter label cut off). `tests/e2e/phone/overflow.spec.ts` annotates the dashboard, index, character and Check-in tests with them.
- [x] 1.5 Phone screenshots in `docs/features/phone-use.md` (ADR-010). Verify: the docs build renders it.
  - Written 21 Nov and listed in `docs/features/README.md`; the phone screenshots come from the phone suite (25 Nov, sandbox instance).
- [x] 1.6 Dutch and English strings for any new labels (REQ-APF-002). Verify: `npm run test:l10n`.

Quality reminders (not tracked as tasks): `npm run lint` and `npm run stylelint` once before push.
