# Tasks: events-qr-checkin

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Code

- [x] 1.1 Fragment `registration.checkinCode` with its read rule; the code set on acceptance in `RegistrationService`; a repair step for already accepted registrations (REQ-EQC-001). Verify: `npm run check:register`; `npm run check:schema-l10n`; PHPUnit for the code format and the repair; the repair-step-registration gate passes.

## 2. Endpoint

- [x] 2.1 `EventsController::checkinByCode()` and its route with the game master guard and rate limit (REQ-EQC-003, REQ-EQC-004). Verify: PHPUnit for each result (checked in, already, not accepted, unknown, other event, player 403); route-auth, no-admin-idor and public-endpoint-throttling gates pass.

## 3. Pages

- [x] 3.1 `src/components/RegistrationQrCode.vue` with `qrcode` on My registrations, with print styles (REQ-EQC-002). Verify: vitest renders an SVG for a code; `npm run lint`.
- [x] 3.2 Scan panel in `src/views/EventRoster.vue`: camera when supported, code field always (REQ-EQC-003). Verify: vitest with a mocked `BarcodeDetector` and without one; the nc-input-labels and semantic-controls gates pass.
- [x] 3.3 Playwright `tests/e2e/qr-checkin.spec.ts`: type Anna's code in the scan field, see her checked in on the roster; type it again, see "already checked in" (REQ-EQC-003, REQ-EQC-004). Verify: passes locally.
  - Passed live 17 Nov (`tests/e2e/qr-checkin.spec.ts`). It found two defects: OpenRegister refuses a filter on the reveal-only code (now read in its trusted reveal scope), and an accepted registration's character was missing from the roster (the cast now also takes the event's `players`).

## 4. Strings and docs

- [x] 4.1 Dutch and English strings (REQ-EQC-003). Verify: `npm run test:l10n`.
- [x] 4.2 `docs/features/qr-checkin.md` with a screenshot of the scan panel (ADR-010). Verify: the docs build renders it.
  - Written 21 Nov and listed in `docs/features/README.md`. Screenshot taken 25 Nov on a sandbox instance (Nextcloud 34, MariaDB). Taking it found a defect: a registration without an account answered "Checked in: , Mirela"; the answer now names the player record.
- [x] 4.3 Add `qrcode` to `package.json` with its licence noted. Verify: `npm ci` and `npm run build` pass; the dependency-integrity gates pass.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
