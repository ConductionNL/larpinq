# PDF/Runsheet Export — Scoped Cast Query Delta

**Spec refs**: `pdf-export` (runsheet export requirements)

## ADDED Requirements

### Requirement: Runsheet Cast Query MUST Be Scoped to the Target Event

`EventsController::buildRunsheetContext()` MUST NOT load every `character` and every `player` in
the OpenRegister register to compute one event's cast list. The character query MUST be narrowed
with a server-side filter (or a bounded, paginated scan if the OpenRegister filter contract does
not support the required array-contains match) so the query cost scales with the target event's
cast size, not with the total number of characters ever created across all events and seasons.
The player lookup MUST be limited to the ids referenced by the (already scoped) cast, not every
player in the register.

**Feature tier**: MVP

#### Scenario: Runsheet query does not scan unrelated characters

- GIVEN an OpenRegister `character` register containing characters linked to many different
  events, including characters with no `events` reference to the target event
- WHEN an event's runsheet PDF is downloaded via `downloadRunsheet()`
- THEN the underlying OpenRegister query for characters MUST be filtered (or paginated with an
  early stop) rather than an unconditional `findAll()` over the whole register
- AND the resulting cast list MUST still be correct: it MUST include exactly the characters whose
  `events` array references the target event, and no others

#### Scenario: Player lookup is scoped to the cast, not the whole register

- GIVEN a `player` register containing players unrelated to the target event's cast
- WHEN the runsheet context is built
- THEN the player lookup MUST resolve only the player ids referenced by the scoped cast list
- AND MUST NOT issue an unfiltered `findAll()` over the entire `player` register
