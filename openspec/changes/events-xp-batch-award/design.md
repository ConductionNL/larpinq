# Design: events-xp-batch-award

## Context

Read at development `2af18d8`.

- `xpAward` (`lib/Settings/larpinq_register.json`): `event`, `character`,
  `amount`, `reason`, `awardedBy`, `awardedAt`, authorization create, update
  and delete by `gamemasters`. EventDetail lists awards (`event-xpawards`
  object-list); CharacterDetail sums them.
- `openspec/specs/event-xp-awards/spec.md:114-151` requires the batch
  surface; the archived `event-xp-award-workflow` tasks 3.1 to 3.4 deferred it
  ("no bespoke event-detail component in src/"), and task 1.3 deferred server
  stamping of `awardedBy` and `awardedAt`.
- `lib/Service/EventRosterService.php` builds the roster
  (`buildRoster()`, characters whose `events[]` contain the event) and loads
  attendance (`loadAttendance()`); `EventsController` guards GM endpoints with
  `resolveGameMaster()`.
- The Check-in tab proves a registered section component can live in the
  EventDetail sidebar (`EventRoster`).

## Goals / Non-Goals

**Goals**: award a whole event in one save, defaulting to who checked in, with
provenance.

**Non-Goals**: non-attendance XP, caps, refusing overrides.

## Decisions

### D1. The roster endpoint

`GET /api/events/{id}/xp-award-roster` (game masters): the roster from
`buildRoster()` with, per character, the player name, attendance status
(`checked-in`, `no-show`, or none) and any existing award for the event. One
read of attendance and one filtered read of awards for the event (bounded,
hydra ADR-058).

### D2. The save endpoint

`POST /api/events/{id}/xp-awards` `{rows: [{character, amount, reason,
extra}]}` (game masters): `XpAwardBatchService` checks each character is on
the roster, amount is above zero, and there is no award for the event and
character unless `extra` is true with a reason; it then saves each award
through `RegisterObjectFetcher::saveObject()` (RBAC on) and returns created
and refused rows. No partial rollback: each row stands or falls alone, and the
response says which.

### D3. The tab

`EventXpAward.vue` (`kind: 'section'`), tab "Award XP" on EventDetail, shown
to game masters: default amount and reason on top, the roster as a table with
tick, amount and reason per row, existing awards shown inline with edit and
delete (plain object writes), and the D1 defaults for ticks.

### D4. Provenance

`XpAwardProvenanceListener` on the pre-write events of `xpAward` sets
`awardedBy` to the acting user and `awardedAt` to now on create, and keeps
both unchanged on update, whatever the client sent.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Which rows start ticked | Imperative, a read endpoint | Joins roster, attendance and awards (hydra ADR-031 exception 2). |
| Creating many awards | Imperative, an endpoint over the object fetcher | One confirmed action writing many objects, each through RBAC. |
| Provenance stamps | Imperative, a pre-write listener | Values from the request context, not from the object. |

## Seed data

"Summer Siege 2025": "Mirela the Wanderer" and "Sir Bertram" checked in,
"Old Captain Harrow" no-show; the demo awards 5 XP to the two who checked in.

## Risks / Trade-offs

- [Spec status] The `event-xp-awards` requirement was archived as done; this
  change's delta restates it with the attendance defaults, so the spec and the
  code agree once it ships.

## Migration

None.

## Open Questions

None.
