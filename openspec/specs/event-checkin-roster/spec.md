# event-checkin-roster Specification

## Purpose
TBD - created by archiving change event-checkin-roster. Update Purpose after archive.
## Requirements
### Requirement: Attendance MUST be an OpenRegister-native record

Day-of attendance MUST be stored as a first-class OpenRegister object of a new
`larping_attendance` schema, one record per `(event, character)` pair, carrying
`event` (uuid ref), `character` (uuid ref), `status` (enum:
`registered` | `checked-in` | `no-show`), `checkedInAt` (date-time) and
`checkedInBy` (Nextcloud uid). Every property MUST carry a gate-28 `title`. The
schema MUST be introduced by an ADR-037 `register.d` fragment (never by editing
the monolithic `larpingapp_register.json` on a build branch), and its slug MUST
be namespaced (`larping_attendance`, consistent with the existing
`larping_event` / `larping_item` slugs) to avoid the global cross-app schema-slug
collision. LarpingApp MUST NOT persist attendance in an app-local database table.

#### Scenario: attendance schema is added via a register fragment

- **GIVEN** the `larpingapp` register at HEAD has no attendance concept
- **WHEN** the `event-checkin-roster` fragment is deep-merged into `larpingapp_register.json`
- **THEN** the register MUST expose a `larping_attendance` schema whose properties are `event`, `character`, `status`, `checkedInAt`, `checkedInBy`, each with a non-empty `title`
- **AND** `status` MUST be constrained to `registered`, `checked-in`, `no-show`
- **AND** no existing schema MUST be removed, renamed, or have a property dropped by the merge
- `@e2e exclude` schema-shape change verified by JSON deep-merge simulation; no larpingapp UI e2e surface exercises the raw register import

#### Scenario: the fragment introduces no live objects

- **WHEN** `lib/Settings/register.d/event-checkin-roster.json` is imported by OpenRegister
- **THEN** it MUST contribute only the `larping_attendance` schema definition (and its RBAC config) and MUST NOT create any attendance object
- `@e2e exclude` static-data guarantee asserted by JSON inspection; register fragment objects would go live, so none are declared

### Requirement: Check-in MUST be a GM-only, server-stamped act

Recording or updating a participant's attendance MUST be restricted to the
`gamemasters` group (or a Nextcloud admin) and enforced server-side. The
`checkedInAt` timestamp MUST be stamped from the server clock and `checkedInBy`
MUST be set to the acting user's uid — neither MUST be accepted from the client
request body. Write authorization MUST be OR-delegated via schema-level RBAC on
the `larping_attendance` schema (ADR-022); the app MUST NOT implement a parallel
attendance-write auth path.

#### Scenario: a non-GM is refused check-in

- **GIVEN** an authenticated user who is NOT in the `gamemasters` group and is not an admin
- **WHEN** they invoke the record-attendance endpoint for any event and character
- **THEN** the request MUST be rejected with a forbidden response
- **AND** no `larping_attendance` record MUST be created or modified
- `@e2e exclude` pending implementation — authorization e2e authored alongside the apply-phase endpoint

#### Scenario: check-in stamps server-authoritative provenance

- **GIVEN** a GM records attendance `checked-in` for a confirmed participant, sending a spoofed `checkedInAt` and `checkedInBy` in the body
- **WHEN** the record is persisted
- **THEN** the stored `checkedInAt` MUST be the server clock value (the spoofed value ignored)
- **AND** the stored `checkedInBy` MUST equal the acting GM's uid (the spoofed value ignored)
- `@e2e exclude` pending implementation — server-stamping e2e authored alongside the apply-phase endpoint

### Requirement: Check-in MUST be scoped to confirmed participants

The record-attendance endpoint MUST reject a `(event, character)` pair where the
character is not a confirmed participant of the event — i.e. the character MUST
either appear in the Event `players[]` or reference the event through
`character.events[]`. A GM MUST NOT be able to check in a character that is not
part of the event.

#### Scenario: checking in a non-participant is refused

- **GIVEN** a GM and an event whose `players[]` does not contain character "Wanderer" and where "Wanderer".events does not reference the event
- **WHEN** the GM records attendance for "Wanderer" on that event
- **THEN** the request MUST be rejected as an invalid participant
- **AND** no `larping_attendance` record MUST be created
- `@e2e exclude` pending implementation — participant-scoping e2e authored alongside the apply-phase endpoint

### Requirement: The event detail page MUST host a roster / check-in surface

The event detail page MUST surface a roster listing every confirmed participant
with the player name, character type and current attendance status, and — for a
GM — a control to set each participant's status to `checked-in` or `no-show`. For
a non-GM the roster MUST render read-only (status visible, no controls). The
surface MUST be built from `@conduction/nextcloud-vue` list components
(CnDataTable) rather than a bespoke table (ADR-012), and MUST derive the roster
from the confirmed participant link, not a second sign-up store.

#### Scenario: a GM checks a player in from the roster

- **GIVEN** a GM viewing the detail page of an event with three confirmed participants all showing status `registered`
- **WHEN** the GM marks the first participant `checked-in`
- **THEN** that participant's row MUST update to `checked-in`
- **AND** the change MUST persist as a `larping_attendance` record readable on reload
- `@e2e exclude` pending implementation — roster UI e2e authored alongside the apply-phase page

#### Scenario: a player sees the roster read-only

- **GIVEN** an authenticated non-GM player viewing an event detail page
- **WHEN** the roster renders
- **THEN** each participant's attendance status MUST be visible
- **AND** no check-in control MUST be offered to the player
- `@e2e exclude` pending implementation — read-only roster e2e authored alongside the apply-phase page

### Requirement: Attendance MUST ground XP-award eligibility and MAY enrich the run-sheet

The `larping_attendance` records MUST be consumable as the eligibility signal for
the existing `event-xp-award-workflow` "full attendance" grant: the batch-award
roster MAY pre-select participants whose attendance is `checked-in` and de-select
those marked `no-show`. The GM run-sheet (`pdf-export`) MAY surface each cast
member's attendance status. Both integrations MUST read the attendance records
and MUST NOT re-derive attendance from any other source.

#### Scenario: the XP-award roster reflects attendance

- **GIVEN** an event where participant "Aldric" is `checked-in` and "Grimm" is `no-show`
- **WHEN** the GM opens the batch XP-award surface for that event
- **THEN** "Aldric" MUST be pre-selected as an award candidate
- **AND** "Grimm" MUST NOT be pre-selected
- `@e2e exclude` pending implementation — the cross-surface pre-selection e2e is authored with the award-workflow integration task

### Requirement: The roster MUST degrade gracefully when attendance storage is unavailable

The roster MUST degrade gracefully when the `larping_attendance` schema or
OpenRegister is unavailable: it MUST fall back to the existing derived
participant list rendered read-only, the check-in control MUST be hidden, and the
rest of the event detail page MUST render normally — mirroring the DocuDesk /
Forms-leaf graceful-degradation pattern. A missing attendance schema MUST NEVER
throw and break the event page.

#### Scenario: roster degrades when the attendance schema is absent

- **GIVEN** an OpenRegister deployment without the `larping_attendance` schema
- **WHEN** a GM opens an event detail page
- **THEN** the roster MUST list the confirmed participants read-only with no attendance status
- **AND** the check-in control MUST be hidden
- **AND** the event detail page MUST render without error
- `@e2e exclude` pending implementation — degradation e2e authored alongside the apply-phase page

