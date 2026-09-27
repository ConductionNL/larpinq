# Design: players-attendance-history

## Context

Read at development `77c85f0`.

- `larping_attendance` (`lib/Settings/register.d/event-checkin-roster.json`):
  `event`, `character`, `status` (registered, checked-in, no-show),
  `checkedInAt`, `checkedInBy`; GM-only writes.
- `character.ocName` is the player uuid; `character` already uses
  `x-openregister-references` (player on `ocName`) and
  `x-openregister-calculations` (`ownerUid` from `@ref.player.userUid`,
  `materialise: true`).
- PlayerDetail (`src/manifest.json`, around line 839): data, contacts leaf,
  related, and the `player-characters` object-list (name, type, approved).
- Repair steps live in `lib/Repair/` and are registered in `appinfo/info.xml`.

## Goals / Non-Goals

**Goals**: one list per player of the events they were at, across characters
and years.

**Non-Goals**: pre-check-in history, trends.

## Decisions

### D1. Derived fields on attendance

Fragment: `attendance` gains `x-openregister-references`
`{"character": {"schema": "character", "mode": "relatedObject", "field":
"character"}, "event": {"schema": "larping_event", "mode": "relatedObject",
"field": "event"}}` and `x-openregister-calculations`
`{"player": {"expression": {"prop": "@ref.character.ocName"}, "materialise":
true}, "eventStartDate": {"expression": {"prop": "@ref.event.startDate"},
"materialise": true}}`, with `player` (uuid) and `eventStartDate` (date-time)
as read-only, hidden properties. Alternative: a larpinq endpoint joining
characters and attendance per player; rejected because a materialised field
lets the standard object-list filter and sort in the database.

### D2. The player page

PlayerDetail (in place): object-list "Events attended" on `attendance`,
filter `{"player": "@objectId", "status": "checked-in"}`, sort
`eventStartDate desc`, columns event, character, event date; and a stats-block
entry counting the same filter. The row rules of `attendance` apply: game
masters read all; the player reads their own through
`characters-player-visibility`'s owner rule when a matching read rule is added
to attendance (`player` materialised uid match), which this fragment declares.

### D3. Backfill

`BackfillAttendanceDerivedFields` repair step re-saves each existing
attendance record once (bounded pages) so the calculations materialise.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Player and date on attendance | Declarative, references and calculations | OpenRegister extensions already used in this register. |
| The list and count | Declarative, manifest widgets | Filter and sort over one schema. |
| Backfill | Imperative, a one-off repair step | Existing rows predate the calculation. |

## Seed data

Anna checked in at "Summer Siege 2025" with "Mirela the Wanderer" and at
"Spring Moot 2025" with "Old Captain Harrow" (borrowed); her player page lists
both, newest first, count 2.

## Risks / Trade-offs

- [Character changes player] A character later transferred to another player
  moves its old attendance with it on the next re-save. Attendance is about the
  character that was there; the list is labelled "as recorded".

## Migration

The repair step runs once on upgrade.

## Open Questions

None.
