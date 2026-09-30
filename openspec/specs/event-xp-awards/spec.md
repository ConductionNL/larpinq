---
status: implemented
---

# Event XP Awards

## Purpose

Give game masters a recorded, per-participant XP awarding workflow after an
event: explicit `xpAward` records (who, how much, why, by whom, when) that
the stat engine sums onto the XP ability, GM-only writes via OpenRegister
schema RBAC, and a batch awarding UI on the event detail page. Fulfils the
description promise "Handle event subscription and experience score" on the
income side of the XP ledger; the spend side is `skill-requirement-
enforcement`. Ambient event effects (EVT-007/008) keep working for
world-level modifiers and are not changed.

@e2e exclude xpAward write/validation/RBAC and the fifth stat-engine application stage are server-authoritative (OpenRegister schema RBAC + CharacterService); proven by PHPUnit CharacterServiceXpAwardTest. The batch "Award XP" surface on the event detail page is covered by tests/e2e/workflows/xp-batch-award.workflow.spec.ts (events-xp-batch-award). The shipped browser surface (the type:index XP Awards page) is covered by tests/e2e/spec-coverage/event-xp-award-workflow.spec.ts.

## Requirements

### Requirement: XP awards MUST be recorded as first-class objects

The system MUST provide an `xpAward` object type in the larpingapp register
with properties: `event` (UUID, required), `character` (UUID, required),
`amount` (positive number, required), `reason` (string, optional),
`awardedBy` (user id), and `awardedAt` (ISO timestamp). `awardedBy` and
`awardedAt` MUST be stamped server-side and MUST NOT be client-settable.
Each grant is one record; multiple awards for the same character and event
MUST be allowed (e.g. attendance plus a plot bonus). Awards benefit from the
standard OpenRegister object audit trail.

#### Scenario: Award carries the full provenance

- GIVEN event "Summer LARP 2026" and participating character "Sir Lancelot"
- WHEN GM "alice" awards 3 XP with reason "Full attendance"
- THEN an xpAward MUST be stored with event, character, amount 3, reason "Full attendance"
- AND awardedBy MUST be "alice" and awardedAt the server time, regardless of any client-supplied values

#### Scenario: Non-positive amount is rejected

- WHEN a GM submits an award with amount 0 or a negative amount
- THEN the write MUST be rejected by schema validation

#### Scenario: Multiple awards per character per event

- GIVEN "Sir Lancelot" already has a 3 XP attendance award for "Summer LARP 2026"
- WHEN the GM grants an additional 1 XP award with reason "Carried the finale plot"
- THEN both awards MUST exist as separate records

### Requirement: The stat engine MUST apply XP awards to the XP ability

`CharacterService.calculateCharacter()` MUST apply the character's XP awards
as a fifth application stage after skills, items, conditions, and events
(extending CALC-002), summing each award's `amount` onto the XP ability and
producing an audit entry `{type: "xpAward", award: {...}, old, new}` per
award. The XP ability MUST be resolved via the app's register configuration
with a name-match fallback (shared with `skill-requirement-enforcement`),
never a hardcoded UUID. Awards MUST be preloaded into indexed maps following
the entity-preloading pattern (CHAR-080..082). Existing four-stage
arithmetic and audit ordering MUST remain unchanged.

#### Scenario: Awards raise the computed XP

- GIVEN ability "XP" with base 0 and character "Novice" with no XP-affecting effects
- AND xpAwards of 3 ("Summer LARP") and 2 ("Autumn LARP") for "Novice"
- WHEN stats are calculated
- THEN XP MUST equal 5
- AND the XP audit trail MUST contain two entries of type "xpAward", applied after any effect entries

#### Scenario: Awards and effects combine in deterministic order

- GIVEN character "Veteran" with a skill effect "XP Cost -10", an event effect "+2 XP", and an xpAward of 5
- WHEN stats are calculated
- THEN the audit order on XP MUST be: skill effect, event effect, then the xpAward
- AND the final XP MUST reflect base - 10 + 2 + 5

#### Scenario: Dangling award references are skipped gracefully

- GIVEN an xpAward whose event no longer exists and another whose character no longer exists
- WHEN stats are calculated
- THEN the award with the missing event MUST still count for its character (provenance, not validity)
- AND the award whose character is missing MUST be ignored without error

#### Scenario: Awards feed the skill-purchase budget

- GIVEN `skill-requirement-enforcement` is active and character "Novice" has computed XP 5 from awards
- WHEN the GM assigns a skill carrying effect "XP Cost -5"
- THEN the budget validation MUST pass (the engine-computed XP includes the awards)

### Requirement: Award writes MUST be restricted to game masters

Create, update, and delete on `xpAward` objects MUST be restricted
server-side to the GM group via OpenRegister schema-level RBAC configured in
the register configuration — authorization stays OR-delegated (ADR-022), and
no app-local authorization code re-implements it. Reading awards through the
OpenRegister objects API MUST be restricted the same way: the `xpAward` schema
grants `read` to the GM group only, so game masters and the award's owner read
it, and no other signed-in user and no anonymous visitor does (DECISIONS row
30). Players see the XP of their own characters through the app's pages (the
character stat sheet), not through the objects API. UI visibility
of the awarding surface MUST be limited to GM-group members, as
presentation only.

#### Scenario: Non-GM write is rejected at the API

- GIVEN user "bob" is not in the GM group
- WHEN bob POSTs an xpAward via the OpenRegister objects API
- THEN the write MUST be rejected with an authorization error
- AND no award MUST be stored

#### Scenario: A player cannot read other awards through the objects API

- GIVEN player-user "carol" is not in the GM group and owns no award
- WHEN carol GETs an xpAward via the OpenRegister objects API
- THEN OpenRegister MUST refuse the read
- AND a game master reading the same award MUST get it

#### Scenario: Player can read awards on their character

- GIVEN player-user "carol" owns character "Sir Lancelot" with two awards
- WHEN carol views the character's XP audit trail
- THEN both awards (amount, reason, event) MUST be visible to her

### Requirement: The event detail MUST offer a GM batch awarding workflow

The event detail page MUST offer a GM-only "Award XP" surface that: lists
the event roster (characters whose `events[]` contain the event) with the
linked player name where available; provides a default amount applied to
all checked rows with per-row amount override and optional per-row reason;
creates one `xpAward` per checked character on save; lists existing awards
for the event inline (character, amount, reason, awardedBy) with edit and
delete; and pre-unchecks roster rows that already have an award for this
event so re-opening the surface does not double-award by default. Rows
without an award MUST start checked when the character's attendance for the
event is checked-in, and unchecked when it is no-show or when no attendance
was recorded, with a hint that no check-in was recorded; the GM MAY change
every tick before saving.

#### Scenario: Batch award after the event

- GIVEN event "Summer LARP 2026" with participating characters "Lancelot", "Merlin", and "Morgana"
- WHEN the GM opens Award XP, sets default amount 3, overrides Morgana to 2 with reason "Saturday only", and saves
- THEN three xpAward records MUST be created: Lancelot 3, Merlin 3, Morgana 2 ("Saturday only")
- AND each character's XP MUST reflect the award after recalculation

#### Scenario: Re-opening does not double-award by default

- GIVEN all three characters already received awards for "Summer LARP 2026"
- WHEN the GM re-opens the Award XP surface
- THEN the existing awards MUST be listed inline
- AND all roster rows MUST be unchecked by default

#### Scenario: Correcting a mistaken award

- GIVEN "Merlin" was awarded 3 XP but attended only one day
- WHEN the GM edits the award to amount 1 with reason "Day guest"
- THEN the award record MUST be updated (not duplicated)
- AND the change MUST be visible in the award's OpenRegister audit trail
- AND Merlin's XP MUST be recalculated

#### Scenario: Non-GM does not see the awarding surface

- GIVEN user "bob" is not in the GM group
- WHEN bob opens the event detail page
- THEN the Award XP surface MUST NOT be offered to him

#### Scenario: Attendance decides the default ticks

- GIVEN at event "Summer Siege 2025" "Mirela the Wanderer" and "Sir Bertram" are checked in and "Old Captain Harrow" is a no-show
- WHEN the GM opens Award XP on the event page
- THEN "Mirela the Wanderer" and "Sir Bertram" are ticked
- AND "Old Captain Harrow" is unticked

### Requirement: Award changes MUST trigger recalculation of the affected character

Creating, updating, or deleting an `xpAward` MUST trigger stat
recalculation of the referenced character (the association-change contract,
CHAR-045 analogue), so the stored XP value and any budget checks reflect the
ledger immediately.

#### Scenario: Deleting an award lowers XP

- GIVEN character "Morgana" with computed XP 2 from a single award
- WHEN the GM deletes that award
- THEN Morgana's stats MUST be recalculated
- AND her stored XP value MUST no longer include the deleted award

### Requirement: A batch award saves each row on its own (REQ-EXB-001)

`POST /api/events/{id}/xp-awards` SHALL create one award per row for
characters on the event's roster, refuse a second award for the same event and
character unless the row is marked extra with a reason, and MUST report which
rows were created and which were refused. Only game masters SHALL call it.

#### Scenario: One duplicate in the batch

- GIVEN "Sir Bertram" already has an award for "Summer Siege 2025"
- WHEN a game master saves a batch with "Mirela the Wanderer" and "Sir Bertram"
- THEN an award for "Mirela the Wanderer" is created
- AND the row for "Sir Bertram" is refused as a duplicate

### Requirement: Award provenance is stamped by the server (REQ-EXB-002)

On every created award larpinq SHALL set `awardedBy` to the acting user and
`awardedAt` to the time of the write, and MUST ignore values sent by the
client; an update MUST keep the original values.

#### Scenario: A client sends its own provenance

- GIVEN game master Joris creates an award through the API with `awardedBy` set to "anna"
- WHEN the award is saved
- THEN its `awardedBy` is "joris" and `awardedAt` is the time of the save
