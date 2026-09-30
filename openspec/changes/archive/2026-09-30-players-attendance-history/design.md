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

**Revised 2026-09-30 (build).** The first draft materialised `attendance.player`
and `attendance.eventStartDate` so a declarative object-list could filter and
sort attendance per player, and let the player read their own records through
an owner read rule on attendance. DECISIONS row 30 (30 Sep) settled attendance
as readable by game masters and the record's owner only, with players seeing
their own through the app's pages. The owner of an attendance record is the
game master who checked the character in, so the declarative list would be
empty for every player, and the rule that would have filled it is ruled out.
The materialised fields, the repair step and the object-list therefore have no
reader left; the design below replaces D1 to D3.

### D1. A larpinq endpoint behind the ownership check

`GET /api/players/{id}/attendance` (`PlayerAttendanceController::index`):
401 without a user; game masters (the group or a Nextcloud admin) pass; any
other caller passes only when `CharacterConnectionGuard::ownsPlayer()` finds
the STORED player's `userUid` is theirs, else 403. `PlayerAttendanceService`
then reads the player's characters as the caller (`character.ocName = id`)
and, per character, the `checked-in` attendance records with the app's
authority (`RegisterObjectFetcher::getObjectsWithAppAuthority`, which refuses
an unscoped filter). Events are read as the caller for their name and start
date. Rows sort newest event first; `count` is the number of distinct events.
Alternative (materialised fields plus object-list): rejected, see above.

### D2. The player page

A sidebar tab "Events attended" on PlayerDetail (`PlayerAttendanceHistory`,
a `kind: 'section'` registry entry, like the character Stats tab) shows the
count, and each event with its character and date, linked to the event page.
Another player opening the page is told only game masters and the player see
the list.

### D3. No schema change, no backfill

Attendance keeps its fields; nothing to migrate.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Who may see a player's history | Imperative, CharacterConnectionGuard | OpenRegister's row rules cannot say "the player of this record's character" without a materialised field, and row 30 keeps attendance closed. |
| The list and count | Imperative endpoint, one section component | Reads with the app's authority after the check. |

## Seed data

Anna checked in at "Summer Siege 2025" with "Mirela the Wanderer" and at
"Spring Moot 2025" with "Old Captain Harrow" (borrowed); her player page lists
both, newest first, count 2.

## Risks / Trade-offs

- [Character changes player] A character later transferred to another player
  takes its attendance with it: the list follows `character.ocName` today.
- [Large histories] At most 500 characters per player and 500 check-ins per
  character are read; far beyond any larp's scale.

## Migration

None.

## Open Questions

None.
