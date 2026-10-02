# event-registration Specification

## Purpose

A sign-up on an event becomes a registration with a status, the event keeps
its capacity with a waiting list, game masters can approve, and the player
says which character they bring. From larpinq matrix rows `reg-signup-form`,
`evt-capacity`, `reg-waitlist`, `reg-choose-character` and
`reg-approve-registrations`. It delivers the requirement "Capacity and
waiting-list ordering MUST stay in Larpinq" of the `event-signup-to-forms-leaf`
spec. A registration also records the ticket type, options and code a player
chose, at the listed prices (rows `reg-tickets`, `reg-discount-codes` and
`reg-meal-choice`).

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

### Requirement: Events offer ticket types with a role and a listed price (REQ-RTO-001)

A game master SHALL be able to define ticket types per event with a name, a
role (player, crew, npc, other), a listed price, an optional sale window and an
optional place limit. A player MUST choose one ticket type on their
registration, and larpinq SHALL record the listed price at the moment of
choosing.

#### Scenario: Anna picks a player ticket

- GIVEN "Winter Court 2026" offers "Player" at EUR 110 and "Crew" at EUR 45
- WHEN Anna chooses "Player" on her registration
- THEN her registration records ticket type "Player" with a line of EUR 110

### Requirement: Early bird ends on time (REQ-RTO-002)

A ticket type with a sale window SHALL be offered only inside that window, and
a choice of it outside the window MUST be refused.

#### Scenario: Too late for the early bird

- GIVEN "Player early bird" is on sale until 2026-11-01
- WHEN Pieter opens his registration on 2026-11-02
- THEN "Player early bird" is not offered and "Player" is

### Requirement: Codes unlock hidden ticket types (REQ-RTO-003)

A game master SHALL be able to create codes that unlock hidden ticket types,
with a valid window and a maximum number of uses. A hidden ticket type MUST be
offered only after a valid code is entered, and a code MUST stop working after
its last use.

#### Scenario: A crew friend uses the code

- GIVEN code "LANTERN" unlocks "Crew friends" at EUR 30 and has 5 uses left
- WHEN Sanne enters "LANTERN" on her registration
- THEN "Crew friends" is offered and she can choose it

### Requirement: Prices come from the event, not from the player (REQ-RTO-004)

The price lines of a registration MUST be written by larpinq from the chosen
ticket type and options; a price sent by a client SHALL be ignored.

#### Scenario: A client tries its own price

- GIVEN Anna chooses "Player"
- WHEN a client sends her registration with a line of EUR 1 through the API
- THEN the stored line is EUR 110

### Requirement: Scarce places and options are limited (REQ-RTO-005)

A ticket type with a place limit SHALL waitlist registrations beyond it, and an
option with a place limit MUST be refused once it is full.

#### Scenario: Crew is full

- GIVEN all 20 "Crew" places are taken
- WHEN Joris chooses "Crew"
- THEN his registration is waitlisted

### Requirement: Organisers see what was chosen (REQ-RTO-006)

The event page SHALL show, for accepted registrations, how many chose each
ticket type and each option, and MUST NOT show a money total.

#### Scenario: The kitchen plans

- GIVEN 12 accepted registrations chose vegan catering and 30 meat
- WHEN a game master opens "Winter Court 2026"
- THEN the page shows 12 vegan and 30 meat
