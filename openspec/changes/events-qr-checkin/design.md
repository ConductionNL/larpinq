# Design: events-qr-checkin

## Context

Read at development `2af18d8`, with `registration-intake-and-capacity`.

- Check-in today: `src/views/EventRoster.vue` lists the roster from `GET
  /api/events/{id}/roster` and posts `POST /api/events/{id}/attendance` with a
  character and a status (`checked-in`, `no-show`), buttons at `:58-68`.
- `lib/Controller/EventsController.php:207` `recordAttendance()` is guarded by
  `resolveGameMaster()` (group `gamemasters` or admin) and validates that the
  character participates (`EventRosterService::isParticipant()`); the service
  (`:196-241`) stamps `checkedInAt` and `checkedInBy` on the server and upserts
  one `larping_attendance` per event and character.
- The Check-in tab is the EventDetail sidebar tab `checkin`, component
  `EventRoster`.
- hydra ADR-082: public endpoint throttling; this endpoint is not public but
  gets a per-user rate limit.

## Goals / Non-Goals

**Goals**: scan or type a code, check in the right participant, fast and
clear.

**Non-Goals**: offline mode, PDF tickets, wristbands.

## Decisions

### D1. The code

`registration.checkinCode`: 26 characters of base32 from `random_bytes(16)`,
set by `RegistrationService` when a registration becomes accepted and kept
when it moves (transfer keeps it; the new holder shows the same code). Read
rules: game masters and the registration's player. Property is excluded from
exports for non game masters.

### D2. Showing it

`RegistrationQrCode.vue` renders the code with `qrcode` as an SVG and the text
below it, on My registrations for accepted registrations, with a print style
that shows only the code, name, character and event.

### D3. Scanning

A "Scan" toggle in `EventRoster.vue` opens a panel with a video preview when
`window.BarcodeDetector` supports `qr_code`, and always a code field that
submits on Enter (handheld scanners type and press Enter). Each code goes to
`POST /api/events/{id}/checkin-code` `{code}`. The panel shows the result for
3 seconds and the roster refreshes.

### D4. The endpoint

`EventsController::checkinByCode(string $id)`: `#[NoAdminRequired]`, game
master check first, rate limit (`#[UserRateLimit(limit: 120, period: 60)]`),
then a filtered query for the registration with that code (limit 1). Results:
`200 {status: checked-in, name, character}`; `200 {status: already, at, by}`;
`409 {status: not-accepted}`; `404 {status: unknown}` for no match or another
event. On success it calls `EventRosterService::recordAttendance()` with the
registration's character.

## Revised at build time (lane 20, 2 Oct)

- D1: the code is made by `CheckinCodes`, called from `RegistrationService::beforeCreate()` and `beforeUpdate()` (the registration listener's pre-write), so every path that accepts a registration (a game master's accept, a sign-up into a free place, a promotion from the waiting list) gets one. Only the server sets it: an update that sends another code keeps the stored one. **A transfer now replaces the code** instead of keeping it: the previous holder still has the old QR code, and it must not check anyone in. The property's pattern is `^([A-Z2-7]{26})?$` because a registration that is not accepted carries an empty code.
- D2: My registrations is a list page, so the QR code lives on a **Check-in code** tab of the registration page (component `RegistrationQrCode`, `src/components/`), which players open from My registrations. Names come from the player, character and event objects through the objects API. The spec delta of REQ-EQC-002 says so.
- D4: the endpoint is its own controller, `EventCheckinController::checkinByCode()`, rather than a method on `EventsController`, so EventsController's dependencies and its tests stay as they are. The service is `CodeCheckin`. One more result: `409 {status: no-character}` for an accepted registration without a character, because attendance is recorded per character. Unavailable attendance storage answers `424 {status: unavailable}`.
- The scan panel is `src/components/CheckinScanPanel.vue`, shown in `EventRoster.vue` behind a **Scan codes** toggle for game masters.
- Repair step `BackfillCheckinCodes` reads accepted registrations with the app's authority and writes a code to each one that has none; it is idempotent.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| The code property and who reads it | Declarative, register fragment | A property with a rule. |
| Making the code | Imperative, the existing registration service | A random secret at a state change. |
| Check-in by code | Imperative, an endpoint over the existing attendance service | Reuses the guarded write path. |

## Seed data

Accepted registrations of "Winter Court 2026" get codes; the demo shows Anna's
QR code on My registrations.

## Risks / Trade-offs

- [Camera support] Safari lacks `BarcodeDetector` on some versions; the typed
  field and handheld scanners cover it.

## Migration

Accepted registrations created before this change get a code from a repair
step.

## Open Questions

None.
