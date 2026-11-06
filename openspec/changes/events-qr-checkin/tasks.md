# Tasks: events-qr-checkin

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Code

- [ ] 1.1 Fragment `registration.checkinCode` with its read rule; the code set on acceptance in `RegistrationService`; a repair step for already accepted registrations (REQ-EQC-001). Verify: `npm run check:register`; `npm run check:schema-l10n`; PHPUnit for the code format and the repair; the repair-step-registration gate passes.

## 2. Endpoint

- [ ] 2.1 `EventsController::checkinByCode()` and its route with the game master guard and rate limit (REQ-EQC-003, REQ-EQC-004). Verify: PHPUnit for each result (checked in, already, not accepted, unknown, other event, player 403); route-auth, no-admin-idor and public-endpoint-throttling gates pass.

## 3. Pages

- [ ] 3.1 `src/components/RegistrationQrCode.vue` with `qrcode` on My registrations, with print styles (REQ-EQC-002). Verify: vitest renders an SVG for a code; `npm run lint`.
- [ ] 3.2 Scan panel in `src/views/EventRoster.vue`: camera when supported, code field always (REQ-EQC-003). Verify: vitest with a mocked `BarcodeDetector` and without one; the nc-input-labels and semantic-controls gates pass.
- [ ] 3.3 Playwright `tests/e2e/qr-checkin.spec.ts`: type Anna's code in the scan field, see her checked in on the roster; type it again, see "already checked in" (REQ-EQC-003, REQ-EQC-004). Verify: passes locally.

## 4. Strings and docs

- [ ] 4.1 Dutch and English strings (REQ-EQC-003). Verify: `npm run test:l10n`.
- [ ] 4.2 `docs/features/qr-checkin.md` with a screenshot of the scan panel (ADR-010). Verify: the docs build renders it.
- [ ] 4.3 Add `qrcode` to `package.json` with its licence noted. Verify: `npm ci` and `npm run build` pass; the dependency-integrity gates pass.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
