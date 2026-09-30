# character-builds Specification

## Purpose

A character keeps alternative builds that can be checked against the rules
without changing the sheet, and a game master applies the chosen one. From
larpinq matrix row `chr-multiple-builds`.

## ADDED Requirements

### Requirement: A character has builds (REQ-CMB-001)

A player SHALL be able to create builds for their own character, and a game
master for any character, each with a name, a purpose (plan, test or other
game), notes, and skills, items and conditions from the character's world. The
character page MUST list its builds.

#### Scenario: Anna plans the alchemist path

- GIVEN Anna owns "Mirela the Wanderer"
- WHEN Anna creates build "Alchemist path" with Herbalism and Potion brewing on the character page
- THEN the Builds list of "Mirela the Wanderer" shows "Alchemist path"
- AND the character's own skills are unchanged

### Requirement: A build can be checked without changing the sheet (REQ-CMB-002)

The build page SHALL show the result of the skill requirement check and the XP
budget for the character with the build's skills, items and conditions, using
the character's real XP awards. The check MUST NOT write anything.

#### Scenario: A build that costs too much

- GIVEN build "Winter campaign" needs 5 XP more than "Mirela the Wanderer" has
- WHEN Anna opens the build page
- THEN the check shows the budget short by 5 XP
- AND "Mirela the Wanderer" is unchanged

### Requirement: A game master applies a build (REQ-CMB-003)

A game master SHALL be able to apply a build, which replaces the character's
skills, items and conditions with the build's in one write that passes the
normal requirement check. The modal MUST list what will be added and removed
before confirming.

#### Scenario: The choice is made

- GIVEN build "Alchemist path" passes the check
- WHEN a game master applies it and confirms
- THEN "Mirela the Wanderer" has Herbalism and Potion brewing as skills

### Requirement: Builds follow the character's access (REQ-CMB-004)

A build MUST be readable and writable only by game masters and the owner of its
character, and its report endpoint SHALL answer 404 to anyone else.

#### Scenario: Another player looks

- GIVEN player Karel does not own "Mirela the Wanderer"
- WHEN Karel calls the report endpoint of "Alchemist path"
- THEN the response is 404
