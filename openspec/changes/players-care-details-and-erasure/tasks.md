# Tasks: players-care-details-and-erasure

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Data

- [x] 1.1 Fragment: player care fields with `x-openregister-encrypted` and property rules; `eventCareRecord` with rules and retention; `event.careRetentionDays`; data-subject scope (REQ-PCE-001 to REQ-PCE-005). Verify: `npm run check:register`; `npm run check:schema-l10n`.
  - `lib/Settings/register.d/players-care-details-and-erasure.json`. The five care fields on `player` are `x-openregister-encrypted`, readable and editable by `gamemasters` and by `larpers` matching `userUid = $userId`. It adds `event.careRetentionDays` (default 30) and `eventCareRecord` (`larping_event_care_record`, gamemasters only, fields encrypted). Its `archive` block is `vernietigen`, `afleidingswijze: eigenschap` on `eraseAfter`, `P0D`; `eraseAfter` is the event end plus the event's days, so a per-event period works with the documented dialect. `npm run check:register` passes and `check:schema-l10n` is at its baseline. Not done: the data-subject scope of D4. OpenRegister finds a subject's objects through its own PII index, and no per-schema scope declaration exists in its spec to declare.
- [ ] 1.2 Newman: another player reads no care field on Anna's player, in a list, a search or a CSV export; the database holds ciphertext (REQ-PCE-001). Verify: the collection passes.
- [ ] 1.3 Seed data of design.md. Verify: the seed imports cleanly.

## 2. Copy

- [x] 2.1 `RegistrationService`: create or update the care record on acceptance and before the event starts, never after (REQ-PCE-002). Verify: PHPUnit for accept, a change before the start, a change after the start.
  - `EventCareRecords` (`copyForRegistration`, `refreshForPlayer`), with the app's authority, through a new `RegisterObjectFetcher::getObjectWithAppAuthority()`. It is called from `RegistrationService::afterWrite()` when a registration becomes accepted, and from `PlayerCareListener` when a player's care fields change. Nothing is written once the event has started. PHPUnit: `EventCareRecordsTest` (accept, a change before the start, a change after the start, empty details, failures), `RegistrationCareCopyTest`, `PlayerCareListenerTest` and `CareDetailsFragmentTest`.

## 3. Erasure

- [ ] 3.1 Retention on `eventCareRecord` from `eventEndDate` plus the event's days; a test run of OpenRegister's destruction job on a fixture past its date (REQ-PCE-003, REQ-PCE-004). Verify: the record appears on a destruction list; after approval it is gone and a certificate exists.

## 4. Pages

- [ ] 4.1 Care widget on PlayerDetail and care list with the due count on EventDetail, in `src/manifest.json` (REQ-PCE-001, REQ-PCE-002). Verify: `npm run check:manifest`; Playwright `tests/e2e/care-details.spec.ts` as Anna and as a game master.
  - Not done in this build: a manifest fragment can only add pages, and build branches may not edit `src/manifest.json`. PlayerDetail's data widget already shows every readable property, so the care fields appear there for the player and game masters. The EventDetail care list needs a change to `manifest.json`.

## 5. Strings and docs

- [ ] 5.1 Dutch and English strings (REQ-PCE-001). Verify: `npm run test:l10n`.
  - Partly: English and Dutch strings for every new schema title and description are in `l10n/en.json`/`nl.json` (and `.js`). `npm run test:l10n` parity now fails for the other 34 locales on these 22 strings, which still need translating.
- [ ] 5.2 `docs/features/care-details-and-erasure.md`, including what a player asks OpenRegister's data-subject workflow for (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
