# event-registration Specification

## Purpose

A sign-up on an event becomes a registration with a status, the event keeps
its capacity with a waiting list, game masters can approve, and the player
says which character they bring. From larpinq matrix rows `reg-signup-form`,
`evt-capacity`, `reg-waitlist`, `reg-choose-character` and
`reg-approve-registrations`. It delivers the requirement "Capacity and
waiting-list ordering MUST stay in Larpinq" of the `event-signup-to-forms-leaf`
spec.

## Requirements

### Requirement: A form sign-up becomes a registration (REQ-RIC-001)

When a signed-in user submits the sign-up form of an event, larpinq SHALL
create one registration for that event with the user, their player when one is
linked to their account, and the submission's id. The form answers MUST stay
in Nextcloud Forms. An anonymous submission MUST NOT create a registration.

#### Scenario: Anna signs up for the winter event

- GIVEN event "Winter Court 2026" takes sign-ups from form "Winter Court sign-up"
- AND Anna's account is linked to player "Anna de Vries"
- WHEN Anna submits the form on the event page
- THEN a registration of "Anna de Vries" for "Winter Court 2026" exists
- AND her answers are only in Nextcloud Forms

### Requirement: An event has a capacity (REQ-RIC-002)

A game master SHALL be able to set a capacity on an event. Places taken MUST
be counted as accepted registrations plus participants a game master added by
hand, and larpinq MUST NOT accept more registrations than the capacity, also
when two sign-ups arrive at the same moment.

#### Scenario: The last place

- GIVEN "Winter Court 2026" has capacity 3 and 2 places taken, without approval
- WHEN Sanne and Pieter submit the form at the same moment
- THEN one of them is accepted and the other is waitlisted

### Requirement: A full event keeps a waiting list in order (REQ-RIC-003)

A registration that finds no free place SHALL be waitlisted with its position
by submission time. When a place frees up, the oldest waitlisted registration
MUST be accepted.

#### Scenario: A place frees up

- GIVEN Pieter is first on the waiting list of "Winter Court 2026"
- WHEN an accepted registration of the event is cancelled
- THEN Pieter's registration becomes accepted

### Requirement: Game masters approve registrations (REQ-RIC-004)

On an event that requires approval, a new registration SHALL be pending until a
game master accepts or declines it on the registration page. Accepting MUST
give a place when one is free and put the registration on the waiting list
otherwise.

#### Scenario: A game master declines a sign-up for a retired character

- GIVEN "Winter Court 2026" requires approval and Karel's registration is pending
- WHEN a game master declines it on the registration page
- THEN Karel's registration shows status declined and takes no place

### Requirement: Accepted registrations are the participants (REQ-RIC-005)

When a registration with a character becomes accepted, larpinq SHALL add the
character to the event's participants; when it leaves accepted, larpinq SHALL
remove the character, so the roster, run sheet and check-in follow the
registrations.

#### Scenario: The roster follows

- GIVEN Anna's registration with "Mirela the Wanderer" is accepted
- WHEN a game master opens the Check-in tab of "Winter Court 2026"
- THEN "Mirela the Wanderer" is on the roster

### Requirement: The player picks the character they bring (REQ-RIC-006)

A player SHALL be able to choose, on their own registration, one of their own
active characters in the event's world. A player MUST NOT be able to choose a
character that is not theirs, retired or dead.

#### Scenario: Anna chooses Mirela

- GIVEN Anna owns active character "Mirela the Wanderer" in Aldmoor and retired "Old Captain Harrow" belongs to Karel
- WHEN Anna opens her registration on "My registrations"
- THEN the character choice offers "Mirela the Wanderer" and not "Old Captain Harrow"
