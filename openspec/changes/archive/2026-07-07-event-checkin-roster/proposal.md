---
kind: code
---

# Proposal: event-checkin-roster

## Why

LarpingApp already models the two ends of the event participation lifecycle but
not the middle. Sign-up is owned by the OpenRegister Forms leaf
(`event-signup-to-forms-leaf`), which classifies confirmed sign-ups and feeds
the Event `players[]` roster. Post-event experience is owned by
`event-xp-award-workflow`, whose canonical grant reason is literally **"full
attendance"** (`xpAward.reason` description at HEAD: "Optional reason for the
award (e.g. full attendance, plot bonus)"). Between the two there is **no record
of who actually showed up**.

At HEAD the `event` schema has 8 properties — `name, description, startDate,
endDate, location, players[], effects[], setting` — and nothing that captures
day-of attendance. The GM run-sheet (`EventsController::downloadRunsheet`) prints
a *derived* cast list from `character.events[]`, but a printed cast list is not a
live attendance state: there is no way to mark a confirmed participant as
checked-in or a no-show, no server-stamped check-in time, and therefore no
factual basis for the "full attendance" XP grant the award workflow already
promises. This is the missing operational surface of running a real LARP: the
GM at the door ticking players in.

Market signal (Specter intelligence DB, app_id=10) confirms the demand cluster
without inventing scope: the highest-demand Event Management canonical features
for larpingapp are `event-registration` (302), `event-full` (302) and
`after-event` (302) — the check-in/attendance step is exactly the seam between
`event-registration` (owned by the Forms leaf) and `after-event` (owned by the
XP-award workflow). Direct competitors that ship this as a core loop include
LARP Manager (larpmanager.com). Tender relevance for larpingapp is 0 — this is a
hobby-market feature, scoped to feature completeness, not procurement.

## What Changes

- **New `larping_attendance` object type.** `{event, character, status,
  checkedInAt, checkedInBy}` — one record per participant per event. `status`
  is an enum (`registered` | `checked-in` | `no-show`). `checkedInAt` and
  `checkedInBy` are stamped server-side (a client cannot forge them). Like
  `xpAward`, attendance is an app-domain game-mechanic record, so an app schema
  in the larpingapp register is the right home; storage, audit trail and RBAC
  come from OpenRegister (ADR-001 / ADR-022 — no app-local table, no app-local
  auth). The slug is namespaced (`larping_attendance`, matching the existing
  `larping_event` / `larping_item` convention) to avoid the global cross-app
  schema-slug collision.
- **GM-only server-authoritative check-in endpoint.** A member of the
  `gamemasters` group (or a Nextcloud admin) records/updates a participant's
  attendance for an event. The endpoint stamps `checkedInAt` (server clock) and
  `checkedInBy` (the acting GM uid); a non-GM is rejected. The target character
  MUST be a confirmed participant of the event (present in the Event `players[]`
  or referencing the event via `character.events[]`), so a GM cannot check in an
  unrelated character.
- **Event roster / check-in surface on the event detail page.** A roster view
  lists every confirmed participant with player name, character type, current
  attendance status and a GM check-in control (present / no-show). For non-GMs
  the roster renders read-only. The surface reuses the manifest-driven page host
  and `@conduction/nextcloud-vue` list components (CnDataTable) — no bespoke
  table (ADR-012).
- **Attendance grounds XP-award eligibility and enriches the run-sheet.** The
  `event-xp-award-workflow` "full attendance" reason becomes a fact rather than a
  guess: the batch-award roster MAY pre-select participants whose attendance is
  `checked-in` and de-select `no-show`. The GM run-sheet cast list MAY surface
  each cast member's attendance status. Neither integration re-derives
  attendance — both read the `larping_attendance` records.

## Impact

- Affected specs: `event-checkin-roster` (new capability). Adjacent (referenced,
  not modified here): `event-xp-awards` (attendance is the eligibility signal for
  the existing per-participant award), `event-signup-to-forms-leaf` (confirmed
  sign-ups are the roster attendance operates on), `pdf-export` (the run-sheet
  MAY surface attendance).
- Affected code (apply phase, NOT here):
  - `lib/Settings/register.d/event-checkin-roster.json` — ADR-037 fragment adding
    the `larping_attendance` schema (+ GM-group write RBAC), never editing the
    monolith.
  - `lib/Controller/EventsController.php` — a GM-only `recordAttendance` action
    (server-stamped, participant-scoped) + a roster read.
  - `src/manifest.json` / event detail — a roster / check-in surface; `src/store`
    schema slug registration.
  - `l10n/` nl + en strings; `appinfo/info.xml` version bump (cache-bust).
- Depends on: OpenRegister schema-level RBAC (group write restriction) and object
  audit trails — both shipped and already used by `xpAward`.
- Out of scope: self check-in by players (attendance is a GM act at the door);
  QR / kiosk check-in flows; ticket tiers and capacity (owned by
  `event-signup-to-forms-leaf`); automatic XP granting on check-in (the award
  stays an explicit GM act in `event-xp-award-workflow`).
