# event-registration Specification (delta)

## Purpose

A registration records the ticket type, options and code a player chose, at
the listed prices. From larpinq matrix rows `reg-tickets`,
`reg-discount-codes` and `reg-meal-choice`.

## ADDED Requirements

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
