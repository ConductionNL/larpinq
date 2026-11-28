# event-xp-awards Specification (delta)

## Purpose

Game masters award XP to a whole event in one save, with the characters who
checked in ticked by default. From larpinq matrix rows `prg-award-xp-bulk` and
`evt-attendance-drives-xp`. The batch surface was specified by the archived
change `event-xp-award-workflow` and never built.

## MODIFIED Requirements

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

## ADDED Requirements

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
