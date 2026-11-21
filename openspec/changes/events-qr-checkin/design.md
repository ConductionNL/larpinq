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
