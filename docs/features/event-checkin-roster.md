# Event Check-in Roster

## Overview

The event check-in roster fills the operational gap between event sign-up
(owned by the Forms leaf) and post-event XP awards (owned by the XP-award
workflow): the day-of act of a game master ticking players in at the door.

Attendance is a first-class OpenRegister object of the `larping_attendance`
schema (namespaced slug per the cross-app schema-slug collision rule) — one
record per `(event, character)` pair with a `status`
(`registered` | `checked-in` | `no-show`) plus server-stamped `checkedInAt`
and `checkedInBy`. Storage, audit trail and write RBAC are OpenRegister-owned
(ADR-001 / ADR-022); there is no app-local attendance table and no parallel
auth path — the schema restricts writes to the `gamemasters` group, mirroring
the `xpAward` precedent.

## How to Use

1. Open an event's detail page.
2. Open the **Check-in** sidebar tab — the roster lists every confirmed
   participant (player name, character, type, current attendance status).
3. As a game master, use **Check in** / **No-show** on a participant's row to
   record attendance. The time and your uid are stamped server-side.
4. Players see the same roster read-only, with no controls.

## Behaviour

- **GM-only, server-stamped.** Recording attendance requires the `gamemasters`
  group (or a Nextcloud admin). `checkedInAt` (server clock) and `checkedInBy`
  (acting uid) are never read from the request body — a spoofed value is
  ignored.
- **Participant-scoped.** A game master can only record attendance for a
  character that is a confirmed participant of the event (present in the event
  `players[]` or referencing the event via `character.events[]`).
- **Grounds XP eligibility, enriches the run-sheet.** The GM run-sheet cast list
  surfaces each cast member's attendance status; the "full attendance" XP-award
  reason becomes a fact rather than a guess. Neither integration re-derives
  attendance — both read the `larping_attendance` records.
- **Graceful degradation.** When the `larping_attendance` schema or OpenRegister
  is unavailable, the roster falls back to the read-only participant list, the
  check-in control is hidden, and the event page keeps working (DocuDesk /
  Forms-leaf degradation pattern) — it never throws.

## Endpoints

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| `GET` | `/api/events/{id}/roster` | Authenticated | Read the roster + per-participant attendance status (`isGm` flag drives the UI). |
| `POST` | `/api/events/{id}/attendance` | GM-only | Record/update a participant's attendance (server-stamped, participant-scoped). |

## Screenshots

_Pending — the roster / check-in surface screenshots are captured once the
frontend bundle is built and served on a live instance._

## Related

- `event-xp-award-workflow` — attendance is the eligibility signal for the
  per-participant XP award.
- `event-signup-to-forms-leaf` — confirmed sign-ups are the roster attendance
  operates on.
- `pdf-export` — the GM run-sheet surfaces each cast member's attendance status.
