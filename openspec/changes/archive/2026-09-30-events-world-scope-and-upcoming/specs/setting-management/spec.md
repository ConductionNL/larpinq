# setting-management Specification (delta)

## Purpose

An event's world is set on the event, users narrow lists to one world, and
upcoming events come first. From larpinq matrix rows `evt-world-scoping`,
`adm-user-preferences` and `evt-upcoming-list`. The active-world lens itself is
the existing requirement "A per-user active setting MUST filter lists
server-side", which this change delivers except for listing shared (world-less)
entities beside the active world's own: OpenRegister cannot yet filter "this
world or none" in one query (design.md D4).

## ADDED Requirements

### Requirement: A game master sets an event's world (REQ-EWU-001)

The event form SHALL let a game master choose the event's world, and the event
page and the Events index MUST show it.

#### Scenario: Winter Court belongs to Aldmoor

- GIVEN event "Winter Court 2026" has no world
- WHEN a game master sets its world to Aldmoor on the event form
- THEN the event page shows World: Aldmoor
- AND the Events index shows Aldmoor in the World column

### Requirement: Upcoming events come first (REQ-EWU-002)

The Events index SHALL open on events starting today or later, soonest first,
with a way to show past events, and the dashboard SHALL show the next 6
upcoming events.

#### Scenario: A player checks what is next

- GIVEN "Summer Siege 2025" is past and "Winter Court 2026" is upcoming
- WHEN a player opens the dashboard
- THEN "Upcoming events" lists "Winter Court 2026" and not "Summer Siege 2025"

### Requirement: The active world is visible wherever it narrows a list (REQ-EWU-003)

Every list narrowed by the active world SHALL show which world is active and
offer a way back to all worlds, including when the narrowed list is empty.

#### Scenario: An empty list under a world

- GIVEN Outer Rim is the active world and it has no items
- WHEN a game master opens the Items index
- THEN the page says there are no items in Outer Rim
- AND offers to show all worlds
