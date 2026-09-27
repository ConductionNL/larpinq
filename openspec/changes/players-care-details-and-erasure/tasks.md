# Tasks: players-care-details-and-erasure

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Data

- [ ] 1.1 Fragment: player care fields with `x-openregister-encrypted` and property rules; `eventCareRecord` with rules and retention; `event.careRetentionDays`; data-subject scope (REQ-PCE-001 to REQ-PCE-005). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [ ] 1.2 Newman: another player reads no care field on Anna's player, in a list, a search or a CSV export; the database holds ciphertext (REQ-PCE-001). Verify: the collection passes.
- [ ] 1.3 Seed data of design.md. Verify: the seed imports cleanly.

## 2. Copy

- [ ] 2.1 `RegistrationService`: create or update the care record on acceptance and before the event starts, never after (REQ-PCE-002). Verify: PHPUnit for accept, a change before the start, a change after the start.

## 3. Erasure

- [ ] 3.1 Retention on `eventCareRecord` from `eventEndDate` plus the event's days; a test run of OpenRegister's destruction job on a fixture past its date (REQ-PCE-003, REQ-PCE-004). Verify: the record appears on a destruction list; after approval it is gone and a certificate exists.

## 4. Pages

- [ ] 4.1 Care widget on PlayerDetail and care list with the due count on EventDetail, in `src/manifest.json` (REQ-PCE-001, REQ-PCE-002). Verify: `npm run check:manifest`; Playwright `tests/e2e/care-details.spec.ts` as Anna and as a game master.

## 5. Strings and docs

- [ ] 5.1 Dutch and English strings (REQ-PCE-001). Verify: `npm run test:l10n`.
- [ ] 5.2 `docs/features/care-details-and-erasure.md`, including what a player asks OpenRegister's data-subject workflow for (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
