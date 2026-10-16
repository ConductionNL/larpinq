# player-care-details Specification

## Purpose

Players record their allergies, medical notes, dietary needs and emergency
contact safely; each event gets a copy for its team, which is erased after the
event with a record. From larpinq matrix rows `ply-medical-dietary` and
`ply-event-data-erasure`.

## ADDED Requirements

### Requirement: Players keep care details that only they and game masters see (REQ-PCE-001)

A player SHALL be able to record allergies, medical notes, dietary needs and an
emergency contact on their player profile. These fields MUST be stored
encrypted and MUST be readable only by the player and by game masters, on
every read path including search and exports.

#### Scenario: Anna records her allergy

- GIVEN Anna's account is linked to player "Anna de Vries"
- WHEN Anna enters "hazelnuts" as allergy and her brother Joost as emergency contact
- THEN a game master sees them on her player page
- AND player Karel gets neither field, not even in a CSV export of players

### Requirement: Each accepted registration carries a copy for the event (REQ-PCE-002)

When a registration is accepted, larpinq SHALL copy the player's care details
into a care record for that event, readable by game masters only, and MUST
update the copy when the player changes their details before the event starts.
The event page SHALL list the event's care records for game masters.

#### Scenario: First aid checks the list

- GIVEN Anna's registration for "Winter Court 2026" is accepted
- WHEN a game master opens the Care list of the event
- THEN it shows Anna with "hazelnuts" and her emergency contact

### Requirement: Event care records are erased after the event (REQ-PCE-003)

Each event care record SHALL be due for destruction a set number of days
(default 30) after the event ends, and MUST be destroyed through OpenRegister's
retention workflow after approval.

#### Scenario: A month after Winter Court

- GIVEN "Winter Court 2026" ended on 2026-12-06 with the default window
- WHEN OpenRegister's destruction job runs after 2027-01-05 and a game master approves the list
- THEN Anna's care record for the event no longer exists
- AND her player profile keeps her own details

### Requirement: What was erased is recorded (REQ-PCE-004)

The destruction of event care records SHALL leave a destruction record listing
which records were destroyed, when and on whose approval, without the erased
content.

#### Scenario: The organisers show what they erased

- GIVEN the care records of "Winter Court 2026" were destroyed
- WHEN a game master opens the destruction certificate in OpenRegister
- THEN it lists the records, the date and the approver

### Requirement: Data-subject requests go through OpenRegister (REQ-PCE-005)

The player profile and the event care records MUST be declared in scope for
OpenRegister's data-subject workflow, and larpinq MUST NOT offer its own
screen for such requests.

#### Scenario: Anna asks what is held about her

- GIVEN Anna files a request to see her data in OpenRegister's data-subject workflow
- WHEN the request is handled
- THEN the export includes her player profile and any event care records still held
