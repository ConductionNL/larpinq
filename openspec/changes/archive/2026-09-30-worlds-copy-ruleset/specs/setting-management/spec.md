# setting-management Specification (delta)

## Purpose

A game master starts a new world from a copy of an existing world's rules.
From larpinq matrix row `wld-copy-ruleset`.

## ADDED Requirements

### Requirement: A game master copies a world's rules (REQ-WCR-001)

A game master SHALL be able to copy a world under a new name. The copy MUST
contain a copy of every ability, effect, skill, item and condition of that
world, and, when those schemas exist, its character field definitions and lore
pages. Characters, players, events, XP awards and attendance MUST NOT be copied.

#### Scenario: A new season on the same rules

- GIVEN world Aldmoor with 6 abilities, 40 effects, 55 skills, 30 items and 12 conditions
- WHEN a game master chooses "Copy world" on the Aldmoor page and names it "Aldmoor season 2"
- THEN world "Aldmoor season 2" exists with 6 abilities, 40 effects, 55 skills, 30 items and 12 conditions
- AND it has no characters or events

### Requirement: Copied rules point at each other (REQ-WCR-002)

In the copy, every reference between copied objects SHALL point at the copy,
references to objects outside the world MUST stay unchanged, and item and
condition holders MUST be empty.

#### Scenario: A prerequisite follows the copy

- GIVEN skill "Master swordsman" requires skill "Swordsmanship" in Aldmoor
- WHEN Aldmoor is copied
- THEN the copy of "Master swordsman" requires the copy of "Swordsmanship", not the original

### Requirement: Only game masters copy, and a failed copy leaves nothing half made (REQ-WCR-003)

`POST /api/worlds/{id}/copy` SHALL answer only to game masters. A copy that
fails MUST NOT leave an active half-copied world.

#### Scenario: A player tries to copy

- GIVEN player Anna, who is not a game master
- WHEN Anna calls `POST /api/worlds/<uuid>/copy` for Aldmoor
- THEN the response is 403 and no world is created
