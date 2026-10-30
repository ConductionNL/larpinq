# Design: events-casting-and-crew-roles

## Context

Read at development `2af18d8`, with `registration-intake-and-capacity`
(registration with player, character, status) and
`registration-ticket-types-and-options` (ticket type role `crew`, `npc`).

- `character.ocName` is the owning player; `ownerUid` derives from it.
  Characters today are created by or for a player; nothing marks a written
  character as available.
- EventDetail (`src/manifest.json`) has a Check-in sidebar tab that renders the
  registered `EventRoster` section component; a Casting tab can follow that
  pattern.
- Controllers guard game master endpoints in `EventsController`
  (`resolveGameMaster()`, group `gamemasters` or admin).

## Goals / Non-Goals

**Goals**: ranked wishes, a best-overall proposal, confirmation; crew and NPC
roles filled from volunteers.

**Non-Goals**: history weighting, shifts.

## Decisions

### D1. Schemas

- `character.castingEvent` (uuid `$ref` larping_event): the event this written
  character is open for; empty means not open. Game masters only.
- `castingPreference` (slug `larping_casting_preference`): `event`,
  `registration`, `character`, `rank` (1 to 5), `playerUid` (materialised).
  Read and write by game masters and by larpers where `playerUid = $userId`,
  until `event.castingDeadline`.
- `crewRole` (slug `larping_crew_role`): `event`, `name`, `kind` (`crew`,
  `npc`), `places`, `description`.
- `crewAssignment` (slug `larping_crew_assignment`): `crewRole`,
  `registration`, `note`. Game masters write; the assigned player reads their
  own.

### D2. The proposal

`CastingService::propose(eventId)` reads the open characters of the event and
the preferences of accepted registrations (bounded pages), builds a cost
matrix (rank 1 costs 1, rank 5 costs 5, not ranked costs 100), and solves the
assignment with the Hungarian method. It returns pairs and the players left
without a ranked match, and writes nothing. `GET
/api/events/{id}/casting/proposal`, game masters only.

Alternative: first-come first-served by sign-up time. Rejected: the row asks
for the best overall assignment, and first-come punishes late sign-ups for the
same wish.

### D3. Confirming

`POST /api/events/{id}/casting/confirm` with the pairs the game master kept or
changed: for each pair, sets the character's `ocName` to the registration's
player and the registration's `character` to the character, through normal
writes (so the requirement listener and the unique checks run), and clears
`castingEvent`. Game masters only; answers with the written pairs and any
refusal by name.

### D4. The Casting tab

`EventCasting.vue` (`kind: 'section'`) on EventDetail: open characters with
how many ranked each at which rank, the proposal as an editable table, a
confirm button, and the crew roles with filled and open places and an assign
action for registrations with a crew or NPC ticket.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Preferences, roles, assignments, access | Declarative, register fragment | Schemas and row rules. |
| Best assignment | Imperative, a service | An optimisation across players and characters; no declarative form (hydra ADR-031 exception 2). |
| Confirm | Imperative endpoint doing normal object writes | Writes to two schemas in one confirmed step. |

## Seed data

"Winter Court 2026" (casting deadline 2026-11-01): open characters "The
Chancellor", "The Spy", "The Heir", "The Bard". Anna ranks The Heir 1, The
Spy 2; Pieter ranks The Heir 1, The Bard 2; Sanne ranks The Spy 1. Proposal:
Anna The Heir, Pieter The Bard, Sanne The Spy. Crew roles: "Bandits" (NPC, 6
places), "Kitchen" (crew, 3 places); Joris assigned to Kitchen.

## Risks / Trade-offs

- [Ties] Equal total cost picks the pairing with more first choices, then
  earlier sign-ups.

## Migration

None.

## Open Questions

None.
