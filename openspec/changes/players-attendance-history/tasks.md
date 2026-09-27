# Tasks: players-attendance-history

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 5. -->

- [ ] 1.1 Fragment: attendance references, the two calculations and the player read rule (REQ-PAH-001, REQ-PAH-002). Verify: `npm run check:register`; `npm run check:schema-l10n`; Newman: a new attendance record carries `player` and `eventStartDate`.
- [ ] 1.2 `BackfillAttendanceDerivedFields` repair step, registered in `appinfo/info.xml` (REQ-PAH-001). Verify: PHPUnit on a fixture of old records; the repair-step-registration gate passes.
- [ ] 1.3 PlayerDetail "Events attended" list and count in `src/manifest.json` (REQ-PAH-001). Verify: `npm run check:manifest`; Playwright `tests/e2e/player-history.spec.ts` shows Anna's two events newest first.
- [ ] 1.4 Dutch and English strings (REQ-PAH-001). Verify: `npm run test:l10n`.
- [ ] 1.5 `docs/features/player-history.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
