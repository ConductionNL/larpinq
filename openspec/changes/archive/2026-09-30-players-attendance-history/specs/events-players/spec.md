# events-players Specification (delta)

## Purpose

A player's page lists the events they were checked in at, across characters
and years. From larpinq matrix row `ply-player-history`.

## ADDED Requirements

### Requirement: The player page lists events attended (REQ-PAH-001)

The player detail page SHALL list every event at which one of the player's
characters was checked in, with the event, the character and the event date,
newest first, and SHALL show how many events the player attended.

#### Scenario: Anna's two seasons

- GIVEN Anna was checked in at "Spring Moot 2025" with "Old Captain Harrow" and at "Summer Siege 2025" with "Mirela the Wanderer"
- WHEN a game master opens the player page of "Anna de Vries"
- THEN "Events attended" lists "Summer Siege 2025" before "Spring Moot 2025", each with its character
- AND the count shows 2

### Requirement: A player sees their own history (REQ-PAH-002)

A player SHALL see the events-attended list on their own player page, and MUST
NOT read the attendance of other players. Attendance stays readable through the
OpenRegister object API by game masters and the record's owner only (DECISIONS
row 30); the list is served by `GET /api/players/{id}/attendance`, which
answers game masters and the player whose stored account it is, and refuses
anyone else with 403 before reading any attendance.

#### Scenario: Anna sees her own history

- GIVEN Anna's account is the account of player "Anna de Vries", who was checked in at two events
- WHEN Anna opens her own player page
- THEN "Events attended" lists both events, newest first, and the count shows 2

#### Scenario: Karel looks at Anna

- GIVEN player Karel is not a game master
- WHEN Karel asks for Anna's history, or for attendance filtered on Anna's characters through the object API
- THEN larpinq refuses with 403 and OpenRegister returns no records
