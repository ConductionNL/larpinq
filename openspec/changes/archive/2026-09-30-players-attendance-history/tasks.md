# Tasks: players-attendance-history

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 5. -->

Design revised at build (design.md, Decisions): DECISIONS row 30 keeps attendance closed to players, so an endpoint behind the ownership check replaces the materialised fields, the repair step and the object-list.

- [x] 1.1 `GET /api/players/{id}/attendance`: `PlayerAttendanceController`, `PlayerAttendanceService`, `CharacterConnectionGuard::owns('player', ...)` (REQ-PAH-001, REQ-PAH-002). Verify: `tests/unit/Controller/PlayerAttendanceControllerTest.php` over OpenRegister's real evaluator (Anna newest first with count 2, a game master the same, Karel 403 with no read made on his behalf, Karel his own one).
- [x] 1.2 PlayerDetail "Events attended" sidebar tab, `src/views/PlayerAttendanceHistory.vue` and `src/services/playerAttendance.js` (REQ-PAH-001). Verify: `tests/vitest/playerAttendance.spec.js`; `npm run lint`.
- [x] 1.3 Playwright `tests/e2e/workflows/player-history.workflow.spec.ts` (written, not run: no isolated instance). Verify: runs against an isolated instance.
- [x] 1.4 Strings in all 37 locales (REQ-PAH-001). Verify: `npm run test:l10n`, `npm run check:l10n-js`.
- [x] 1.5 `docs/features/player-history.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
