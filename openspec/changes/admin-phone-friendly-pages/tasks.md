# Tasks: admin-phone-friendly-pages

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 6. -->

- [ ] 1.1 Playwright project `phone` and `tests/e2e/phone/overflow.spec.ts` over the listed pages (REQ-APF-001). Verify: the suite runs and reports each page; failing pages listed in the PR.
- [ ] 1.2 `EventRoster.vue` phone layout: stacked rows, 44 pixel buttons, full-width scan field (REQ-APF-002). Verify: the phone suite passes for the Check-in tab; the hydra stylelint and semantic-controls gates pass.
- [ ] 1.3 The same container styles in larpinq's other registered components (REQ-APF-001). Verify: the phone suite passes for CharacterDetail with the Stats tab, and the Casting and Award XP tabs.
- [ ] 1.4 Report overflow in nextcloud-vue components upstream and mark the tests with the issue (REQ-APF-001). Verify: each marked test names an issue number.
- [ ] 1.5 Phone screenshots in `docs/features/phone-use.md` (ADR-010). Verify: the docs build renders it.
- [ ] 1.6 Dutch and English strings for any new labels (REQ-APF-002). Verify: `npm run test:l10n`.

Quality reminders (not tracked as tasks): `npm run lint` and `npm run stylelint` once before push.
