# character-connections Specification

**Status**: implemented
**Scope**: larpinq
**OpenSpec changes**:
- `characters-factions-and-relationships` (archived 2026-09-30)

## Purpose

Characters belong to factions and player groups and have relationships with
each other. From larpinq matrix rows `chr-faction-membership`,
`chr-relationships` and `chr-player-groups`.

## Requirements

### Requirement: Factions list their members (REQ-CFR-001)

A game master SHALL be able to create factions per world and place characters
in them with a role. The faction page MUST list its active members, and the
character page MUST list the character's factions and groups.

#### Scenario: A game master fills the guard

- GIVEN faction "The Crown's Guard" in world Aldmoor
- WHEN a game master adds "Sir Bertram" as a member on the faction page
- THEN the faction page lists "Sir Bertram" as an active member
- AND the character page of "Sir Bertram" lists "The Crown's Guard"

### Requirement: Secret factions stay secret (REQ-CFR-002)

A faction with visibility secret, and its memberships, MUST be readable only by
game masters and by the owners of its member characters.

#### Scenario: A player looks for the Ash Circle

- GIVEN "Mirela the Wanderer" is an active member of secret faction "The Ash Circle"
- WHEN player Karel, who owns no member, searches the Factions page for "Ash"
- THEN no result is returned
- AND the character page of "Mirela the Wanderer" shows Karel no faction

### Requirement: Players run their own groups (REQ-CFR-003)

A player SHALL be able to create a group with their own character as leader,
invite characters, accept or decline requests to join, and remove members. A
player MUST NOT be able to make a character an active member of a group without
the leader's invitation or approval.

#### Scenario: A leader invites and the invitee accepts

- GIVEN Tomas leads group "The Lantern Bearers"
- WHEN Tomas invites "Lady Venn" on the group page
- AND the owner of "Lady Venn" accepts the invitation on her character page
- THEN "Lady Venn" is an active member of "The Lantern Bearers"

#### Scenario: A player cannot join without asking

- GIVEN player Karel owns "Old Captain Harrow"
- WHEN Karel creates an active membership of "Old Captain Harrow" in "The Lantern Bearers" through the API
- THEN the write is refused

### Requirement: Membership changes leave a record (REQ-CFR-004)

Joining, leaving and removal SHALL be recorded as status changes of the
membership (requested, invited, active, left, removed), not as deletions, and a
game master MUST be able to see a character's past memberships.

#### Scenario: A member leaves

- GIVEN "Lady Venn" is an active member of "The Lantern Bearers"
- WHEN her owner chooses to leave the group
- THEN her membership shows status left
- AND a game master still sees it in her membership history

### Requirement: Characters have relationships (REQ-CFR-005)

A relationship SHALL link one character to another with a kind (family, ally,
rival, romance, enemy, mentor, other) and a description. A relationship known
to the owners MUST be readable by game masters and the owners of both
characters only; a relationship known to game masters MUST be readable by game
masters only.

#### Scenario: A player records a rival

- GIVEN Anna owns "Mirela the Wanderer"
- WHEN Anna adds a relationship "rival" from "Mirela the Wanderer" to "Sir Bertram" known to owners
- THEN the owner of "Sir Bertram" sees it under "Named by others" on his character page

#### Scenario: A hidden family tie

- GIVEN a game master records "Tomas" and "Lady Venn" as family, known to game masters
- WHEN the owner of "Tomas" opens his character page
- THEN the relationship is not shown
