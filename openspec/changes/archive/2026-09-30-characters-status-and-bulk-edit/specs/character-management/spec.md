# character-management Specification (delta)

## Purpose

A character is active, retired or dead, and a game master changes many
characters in one action. From larpinq matrix rows `chr-status-lifecycle` and
`chr-bulk-edit`.

## ADDED Requirements

### Requirement: A character has a status (REQ-CSB-001)

Every character SHALL have a status of `active`, `retired` or `dead`, with
`active` as the default. The status MUST show on the character detail page
and be available as a column and a filter on the Characters index.

#### Scenario: A game master marks a fallen character dead

- GIVEN character "Brother Aldric" is active
- WHEN a game master sets his status to dead on the character detail page
- THEN "Brother Aldric" shows status dead
- AND filtering the Characters index on dead lists him

### Requirement: Retired and dead characters stay out of new events (REQ-CSB-002)

A character whose status is not `active` MUST NOT be offered in the
participant picker of an event, and a write that adds an event to such a
character SHALL be refused with an error on `events`.

#### Scenario: A retired captain is not offered for the next event

- GIVEN "Old Captain Harrow" is retired
- WHEN a game master adds participants to event "Winter Court 2026"
- THEN "Old Captain Harrow" is not in the list of characters to pick

#### Scenario: The API refuses a dead character

- GIVEN "Brother Aldric" is dead
- WHEN a client adds event "Winter Court 2026" to his events through the objects API
- THEN the write is refused with an error on `events`

### Requirement: A game master edits many characters at once (REQ-CSB-003)

On the Characters index a game master SHALL be able to select characters and
set status, type or world on all of them in one confirmed action. Each
character MUST be written through the same write path as a single edit.

#### Scenario: End of season clean-up

- GIVEN a game master selects "Old Captain Harrow", "Lady Venn" and "Tomas" on the Characters index
- WHEN she chooses "Edit selected", sets status to retired and confirms
- THEN all three characters show status retired

### Requirement: A partial bulk edit is reported (REQ-CSB-004)

When some characters in a bulk edit cannot be written, larpinq SHALL apply the
others and list the refused characters by name with the reason.

#### Scenario: One character is locked

- GIVEN "Lady Venn" is locked by another user
- WHEN a game master sets status retired on "Old Captain Harrow" and "Lady Venn"
- THEN "Old Captain Harrow" is retired
- AND the modal names "Lady Venn" as not changed, with the reason
