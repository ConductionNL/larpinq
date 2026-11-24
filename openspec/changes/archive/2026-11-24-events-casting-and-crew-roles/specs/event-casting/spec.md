# event-casting Specification

## Purpose

Players rank written characters, larpinq proposes the best overall casting,
game masters confirm it, and volunteers fill crew and NPC roles. From larpinq
matrix rows `chr-casting-preferences` and `evt-crew-roles`.

## ADDED Requirements

### Requirement: Players rank characters open for casting (REQ-ECC-001)

A game master SHALL be able to open written characters for casting on an
event. A player with a registration for that event SHALL be able to rank up to
5 of them until the casting deadline, and MUST NOT see other players' rankings.

#### Scenario: Anna wants to be the heir

- GIVEN "The Heir" and "The Spy" are open for casting on "Winter Court 2026" and Anna is registered
- WHEN Anna ranks "The Heir" first and "The Spy" second on My registrations
- THEN her ranking is saved
- AND Pieter cannot read it

### Requirement: Larpinq proposes the best overall casting (REQ-ECC-002)

On request, larpinq SHALL propose an assignment of open characters to
registered players that gives each player at most one character and each
character at most one player, and minimises the total rank over all matched
players. Players without a ranked match MUST be listed. The proposal MUST NOT
change any data.

#### Scenario: Three players, one popular heir

- GIVEN Anna ranks The Heir 1 and The Spy 2, Pieter ranks The Heir 1 and The Bard 2, and Sanne ranks The Spy 1
- WHEN a game master asks for a proposal on the Casting tab
- THEN every player gets a character they ranked, and no two players get the same character
- AND the total of the ranks given is the lowest possible for these rankings

### Requirement: Game masters confirm the casting (REQ-ECC-003)

A game master SHALL be able to change the proposal and confirm it; on confirm,
each cast character MUST be owned by its player and set as the character on the
player's registration.

#### Scenario: The heir is cast

- GIVEN the proposal pairs Anna with "The Heir"
- WHEN a game master confirms it
- THEN "The Heir" is owned by Anna's player and is the character on her registration

### Requirement: Volunteers fill crew and NPC roles (REQ-ECC-004)

A game master SHALL be able to define crew and NPC roles per event with a
number of places, and assign registrations with a crew or NPC ticket to them.
The event page MUST show each role with filled and open places, and an
assigned volunteer MUST see their own role.

#### Scenario: The kitchen needs hands

- GIVEN role "Kitchen" (crew, 3 places) on "Winter Court 2026"
- WHEN a game master assigns Joris, who has a crew ticket
- THEN the Casting tab shows "Kitchen" with 1 of 3 places filled
- AND Joris sees "Kitchen" on My registrations
