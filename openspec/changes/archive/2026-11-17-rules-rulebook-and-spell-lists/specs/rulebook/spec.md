# rulebook Specification

## Purpose

Spells and powers live in their own lists with their own cost pool, and
players read the rules as a book. From larpinq matrix rows
`rul-publish-rulebook` and `rul-spell-lists`.

## ADDED Requirements

### Requirement: Skills can be grouped in lists of their own kind (REQ-RRS-001)

A game master SHALL be able to define skill lists per world with a kind
(skills, spells, powers), a description and the ability their costs are paid
from, and place skills in a list. Skills without a list MUST stay general
skills paid from XP.

#### Scenario: A game master creates the arcane spells

- GIVEN world Aldmoor with ability "Mana"
- WHEN a game master creates list "Arcane spells" of kind spells paid from "Mana" and places "Firebolt" in it
- THEN the Skill lists page shows "Arcane spells" with "Firebolt"

### Requirement: Each list draws on its own pool (REQ-RRS-002)

The skill requirement check SHALL evaluate one budget per cost ability, and a
character write MUST be refused when any pool would go below zero.

#### Scenario: Out of mana, not out of XP

- GIVEN "Mirela the Wanderer" has 30 XP left and 2 mana
- WHEN a game master adds "Firebolt" (3 mana) to her skills
- THEN the write is refused with a shortfall of 1 on "Mana"

### Requirement: Players read the rulebook of their world (REQ-RRS-003)

Larpinq SHALL offer a Rulebook page per world with the rules chapters players
may read, in order, followed by a reference of abilities, skill lists with
their skills and costs, items and conditions, and MUST print cleanly.

#### Scenario: Anna reads about casting

- GIVEN chapters "How combat works" and "Casting a spell" are rules pages for players in Aldmoor
- WHEN Anna opens the Rulebook of Aldmoor
- THEN she reads both chapters in order
- AND the reference lists "Firebolt" under "Arcane spells" with its cost of 3 mana

### Requirement: Secret rules stay out of the rulebook (REQ-RRS-004)

Skills, items and conditions marked hidden from the rulebook MUST NOT appear in
the Rulebook page or its portal collections, and rules chapters MUST follow the
visibility and reveal rules of lore pages.

#### Scenario: The forbidden rite

- GIVEN skill "Blood rite" is hidden from the rulebook
- WHEN Anna opens the Rulebook
- THEN "Blood rite" is not listed

### Requirement: The rulebook is in the portal (REQ-RRS-005)

The larpinq contribution to the portal SHALL offer the player audience the
rules chapters and the skill lists they may read.

#### Scenario: Lotte studies before her first event

- GIVEN Lotte uses the portal with a linked player profile
- WHEN she opens the larpinq rulebook in the portal
- THEN she reads the chapters and the skill lists of the world
