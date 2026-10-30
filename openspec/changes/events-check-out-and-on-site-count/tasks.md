# Tasks: events-check-out-and-on-site-count

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 3. -->

## 1. Data and endpoint

- [ ] 1.1 `lib/Settings/register.d/event-check-out.json` (new fragment): `larping_attendance.status` gains `checked-out`; new `checkedOutAt` and `checkedOutBy`, each with a `title` (REQ-ECO-001). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [ ] 1.2 `lib/Controller/EventsController.php` `recordAttendance()`: accept `checked-out` only from `checked-in`, stamp `checkedOutAt` and `checkedOutBy` from the server, clear them on a new check-in (REQ-ECO-001). Verify: `tests/unit/Controller/EventsControllerAttendanceTest.php` (check-out stamps, spoofed values ignored, check-out from `registered` refused).

## 2. Roster

- [ ] 2.1 `src/views/EventRoster.vue`: a Check out action on checked-in rows, "uitgecheckt {day} {time}" on the row, and the "nu op locatie" count; strings in `l10n/nl.json` and `l10n/en.json` (REQ-ECO-002). Verify: `tests/vitest/eventRoster.spec.js` (40 in, 2 out: 38); `npm run test:l10n`.
