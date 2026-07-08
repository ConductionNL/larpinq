# Design: event-checkin-roster

## Context

The event participation lifecycle at HEAD is: **sign-up** (OR Forms leaf →
`event-signup-to-forms-leaf` → confirmed sign-ups feed Event `players[]`) →
*[gap]* → **experience** (`event-xp-award-workflow` → per-participant `xpAward`
whose reason is "full attendance"). The gap is day-of attendance. The
`downloadRunsheet` GM export already computes a cast list from
`character.events[]` but records no state.

This change fills the gap with the smallest OR-native surface that mirrors the
already-shipped `xpAward` pattern: a dedicated schema + a GM-only server-stamped
write + a manifest-driven read surface, all consuming existing abstractions.

## Goals

- One attendance record per `(event, character)`, OR-owned, audited, GM-writable.
- Zero app-local storage or app-local auth (ADR-001 / ADR-022).
- A roster/check-in surface that reuses the manifest host and nc-vue list
  components — no bespoke table (ADR-012).
- Attendance is a *fact* the existing XP-award and run-sheet surfaces can read;
  it does not itself grant XP or mutate the stat engine.

## Data model

`larping_attendance` (namespaced slug per the cross-app collision rule):

| property     | type                | notes                                                        |
|--------------|---------------------|--------------------------------------------------------------|
| `event`      | string (uuid)       | ref to the `larping_event` object                            |
| `character`  | string (uuid)       | ref to the `character` object                                |
| `status`     | string enum         | `registered` (default) \| `checked-in` \| `no-show`          |
| `checkedInAt`| string (date-time)  | server-stamped; ignored from client body                     |
| `checkedInBy`| string (uid)        | server-stamped acting GM uid; ignored from client body       |

Modelled as its own schema (not an `event.attendance[]` array) to match the
`xpAward` precedent, keep the mutable per-participant state out of the event
object, and get OR audit trails + schema-level RBAC for free.

## Authorization

Write RBAC is delegated to OpenRegister schema-level group restriction on
`larping_attendance` (the same mechanism `xpAward` uses for the `gamemasters`
group). The controller additionally guards the acting user (group / admin) and
performs the participant-scope check before delegating the write, so a valid GM
still cannot check in a non-participant. `checkedInAt` / `checkedInBy` are set by
the controller from the server clock and session, never from the request body —
the anti-forgery rule shared with `xpAward.awardedAt` / `awardedBy`.

## Integration seams (read-only, no re-derivation)

- **XP-award roster** (`event-xp-award-workflow`): reads attendance to
  pre-select `checked-in` and de-select `no-show`. Purely a default; the GM can
  still override selection.
- **Run-sheet** (`pdf-export`): `buildRunsheetContext` MAY add each cast entry's
  attendance status. Additive; absence of attendance leaves the cast list
  unchanged.

## Degradation

A missing `larping_attendance` schema or absent OpenRegister MUST leave the event
page working: the roster falls back to the read-only derived participant list and
the check-in control is hidden. This mirrors the Forms-leaf and DocuDesk 424
patterns already in the app — no throw, no broken page.

## Alternatives considered

- **`event.attendance[]` sub-array** — rejected: mutable per-participant state on
  the event object, no per-record audit trail, harder RBAC, inconsistent with the
  `xpAward` precedent.
- **Self check-in by players** — out of scope: attendance is a GM act at the
  door; a player-facing self-check-in is a separate, larger trust surface.
- **Auto-award XP on check-in** — rejected: awarding stays an explicit, auditable
  GM decision in `event-xp-award-workflow`; check-in only supplies the eligibility
  fact.
