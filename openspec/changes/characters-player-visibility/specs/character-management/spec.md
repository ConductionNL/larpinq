# character-management Specification (delta)

## Purpose

A player edits their own character sheet, sees other characters only as a
short cast entry, and never sees a private game master note. From larpinq
matrix rows `chr-player-edits-own`, `chr-gm-notes` and
`chr-learn-other-characters`.

## ADDED Requirements

### Requirement: A player reads and edits only their own sheet in full (REQ-CPV-001)

A member of `larpers` who is not a game master SHALL read and update a
character in full only when its `ownerUid` is their own user id. Game masters
(members of `gamemasters`, and Nextcloud administrators) MUST read and update
every character. Only game masters SHALL delete a character.

#### Scenario: A player edits her own background

- GIVEN player Anna owns character "Mirela the Wanderer"
- WHEN Anna changes the background on the character detail page of "Mirela the Wanderer"
- THEN the change is saved

#### Scenario: A player cannot edit someone else's sheet

- GIVEN character "Sir Bertram" is owned by another player
- WHEN Anna sends a PUT for "Sir Bertram" to the OpenRegister objects API
- THEN the request is refused
- AND "Sir Bertram" is unchanged

### Requirement: Other characters appear to a player as cast entries only (REQ-CPV-002)

A player SHALL be able to read approved characters of other players, and MUST
receive only `name`, `type`, `description` and `approved` for them, on every
read path including search and exports. Characters that are not approved MUST
NOT be readable by other players.

#### Scenario: A player opens another approved character

- GIVEN "Sir Bertram" is approved and owned by another player
- WHEN Anna opens "Sir Bertram"
- THEN she sees his name, type and description
- AND his background, notes, skills and items are not in the response

#### Scenario: A draft character stays hidden

- GIVEN character "Nameless Stranger" is not approved and belongs to another player
- WHEN Anna searches the Characters index for "Stranger"
- THEN no result is returned

### Requirement: Private game master notes never reach a player (REQ-CPV-003)

`slNotesPrivate` and `requirementOverrides` SHALL be readable and writable by
game masters only, including on the player's own character and in the PDF and
CSV output a player can trigger.

#### Scenario: A secret stays secret on the player's own sheet

- GIVEN "Mirela the Wanderer" has the private note "Secretly the heir of Aldmoor"
- WHEN Anna opens "Mirela the Wanderer"
- THEN the note is not shown and not in the API response

#### Scenario: A game master still sees the note

- GIVEN a game master in `gamemasters`
- WHEN the game master opens "Mirela the Wanderer"
- THEN the private note is shown

### Requirement: A player writes only the story fields of their own sheet (REQ-CPV-004)

On their own character a player SHALL be able to change `name`,
`description`, `background` and `faith`. Changes to any other field by a
player MUST be refused; game masters MUST be able to change every field.

#### Scenario: A player tries to give herself gold

- GIVEN "Mirela the Wanderer" has 3 gold pieces
- WHEN Anna sets gold to 300 through the API
- THEN the write is refused
- AND the character still has 3 gold pieces

### Requirement: Players can browse the cast (REQ-CPV-005)

Larpinq SHALL offer a Cast page listing approved characters with name, type
and description, sorted by name, reachable from the menu for every member of
`larpers`.

#### Scenario: A player studies the cast before an event

- GIVEN approved characters "Mirela the Wanderer" and "Sir Bertram"
- WHEN Anna opens the Cast page
- THEN both characters are listed with their name, type and description

### Requirement: Server paths keep the fields they are entitled to (REQ-CPV-006)

The game master run sheet SHALL keep every character field, and a PDF that a
player downloads of their own character MUST NOT contain `slNotesPrivate`.

#### Scenario: The run sheet still has the secrets

- GIVEN a game master downloads the run sheet of event "Summer Siege 2026"
- WHEN the cast list is built
- THEN each cast entry carries its private notes
