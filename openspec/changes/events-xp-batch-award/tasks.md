# Tasks: events-xp-batch-award

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Server

- [ ] 1.1 `GET /api/events/{id}/xp-award-roster` with attendance and existing awards (event-xp-awards batch requirement). Verify: PHPUnit for the three attendance states and an existing award; route-auth, no-admin-idor and semantic-auth gates pass.
- [ ] 1.2 `XpAwardBatchService` and `POST /api/events/{id}/xp-awards` (REQ-EXB-001). Verify: PHPUnit: created rows, a refused duplicate, an allowed extra award with a reason, a character not on the roster.
- [ ] 1.3 `XpAwardProvenanceListener` for `awardedBy` and `awardedAt` (REQ-EXB-002). Verify: PHPUnit with the real OpenRegister event classes: a client-sent `awardedBy` is overwritten on create and kept on update.

## 2. Tab

- [ ] 2.1 `src/views/EventXpAward.vue` registered as `kind: 'section'` and the "Award XP" tab on EventDetail in `src/manifest.json`, game masters only. Verify: `npm run check:manifest`; vitest for the default ticks.
- [ ] 2.2 Playwright `tests/e2e/xp-batch-award.spec.ts`: on "Summer Siege 2025", the two checked-in characters start ticked, the no-show not; saving with 5 XP creates two awards; re-opening shows them and ticks nothing. Verify: passes locally.

## 3. Specs, strings, docs

- [ ] 3.1 Replace the stale `@e2e exclude` on the batch requirement in `openspec/specs/event-xp-awards/spec.md` with the new test. Verify: the e2e-coverage gate passes on the diff.
- [ ] 3.2 Dutch and English strings. Verify: `npm run test:l10n`.
- [ ] 3.3 `docs/features/xp-batch-award.md` with a screenshot (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
