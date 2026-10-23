# staff-roles Specification

## Purpose

Team members get the rights their job needs through four staff roles, and a
game master invites people to a role with a link. From larpinq matrix rows
`adm-roles-permissions` and `adm-role-invite`.

## ADDED Requirements

### Requirement: Four staff roles exist next to the game masters (REQ-ASR-001)

Larpinq SHALL provide the staff roles story writer, rules marshal, treasurer
and steward as Nextcloud groups, created on install when missing. The game
master tier MUST remain the single group `gamemasters`.

#### Scenario: A fresh install

- GIVEN larpinq is installed on a Nextcloud without these groups
- WHEN the install finishes
- THEN the four role groups exist and are empty
- AND `gamemasters` is still the only game master group

### Requirement: Each role gets the rights of its work (REQ-ASR-002)

A story writer SHALL write plots, plot parts, lore pages and the story fields
of characters; a rules marshal SHALL write abilities, skills, items, conditions
and effects; a treasurer SHALL read registrations with their payment state. No
staff role MUST be able to delete characters, XP awards or registrations.

#### Scenario: A writer at work

- GIVEN Sanne is a story writer and not a game master
- WHEN she creates lore page "The Ash Circle" and then tries to change the XP cost of skill "Swordsmanship"
- THEN the lore page is saved
- AND the skill change is refused

### Requirement: Stewards check people in (REQ-ASR-003)

A steward SHALL be able to open an event's Check-in tab and record check-in,
and MUST NOT be able to download the run sheet or change characters.

#### Scenario: A steward at the gate

- GIVEN Joris is a steward
- WHEN he checks in "Mirela the Wanderer" on the Check-in tab of "Winter Court 2026"
- THEN her attendance is checked in, recorded by Joris

### Requirement: A game master invites with a link (REQ-ASR-004)

A game master SHALL be able to create an invite link for one role with an
expiry and a number of uses. A signed-in user who opens a valid link and
confirms SHALL join that role and the app's user group. An expired, used-up or
revoked link MUST add nobody.

#### Scenario: A new steward joins

- GIVEN a game master created a steward invite with 1 use, valid 7 days
- WHEN Pieter opens the link, signed in, and confirms
- THEN Pieter is a steward
- AND the invite shows it was used by Pieter

#### Scenario: The link is shared further

- GIVEN the invite was used by Pieter
- WHEN Karel opens the same link
- THEN Karel is told the invite is used up and joins nothing

### Requirement: Game masters see and manage the team (REQ-ASR-005)

The Staff page SHALL show game masters the members of each role and the open
invites, and MUST let them revoke an invite and remove a member from a role.

#### Scenario: A treasurer steps down

- GIVEN Lotte is a treasurer
- WHEN a game master removes her from the role on the Staff page
- THEN Lotte can no longer read payment fields
