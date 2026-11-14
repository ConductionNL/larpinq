# character-management Specification (delta)

## Purpose

A game master and the character's owner see each ability's computed value,
every modifier with its source, and the XP a character earned, spent and has
left. From larpinq matrix rows `chr-stat-breakdown` and `prg-xp-balance`.

## ADDED Requirements

### Requirement: The character page shows each ability with its sources (REQ-CSP-001)

The character detail page SHALL have a Stats tab listing every ability with
its base value, each modifier in the order the engine applied it (source type,
source name, change, old and new value), and the final value, as computed by
`CharacterService::calculateCharacter()`.

#### Scenario: A game master sees why strength is 14

- GIVEN ability "Strength" has base 10
- AND character "Mirela the Wanderer" has skill "Swordsmanship" (+3 strength) and item "Iron shield" (+1 strength)
- WHEN a game master opens the Stats tab of "Mirela the Wanderer"
- THEN "Strength" shows base 10, final 14
- AND the modifiers read "+3 from Swordsmanship (skill)" and "+1 from Iron shield (item)"

#### Scenario: A negative modifier stands out

- GIVEN "Mirela the Wanderer" has condition "Cursed" with -2 agility and agility base 8
- WHEN the Stats tab renders
- THEN "Agility" shows final 6 and the modifier "-2 from Cursed (condition)" marked as negative

### Requirement: An untouched ability says so (REQ-CSP-002)

An ability that no modifier changed SHALL show its base as its final value and
the text "No modifiers".

#### Scenario: A new character

- GIVEN a character with no skills, items, conditions, events or awards
- WHEN a game master opens its Stats tab
- THEN every ability shows its base value and "No modifiers"

### Requirement: XP earned, spent and left are visible (REQ-CSP-003)

The Stats tab SHALL show the character's XP earned, spent and left, where left
MUST equal the XP budget value the skill requirement check uses.

#### Scenario: A game master checks XP before a purchase

- GIVEN "Mirela the Wanderer" received two XP awards of 20 and bought "Swordsmanship" for 10 XP
- WHEN a game master opens her Stats tab
- THEN it shows 40 earned, 10 spent and 30 left

### Requirement: Stats follow the character's read access (REQ-CSP-004)

`GET /api/characters/{id}/stats` MUST answer only to users who can read the
character through OpenRegister, and SHALL answer 404 otherwise.

#### Scenario: Someone without access asks for stats

- GIVEN a user who cannot read character "Sir Bertram"
- WHEN the user calls `GET /api/characters/<uuid>/stats` for "Sir Bertram"
- THEN the response is 404 and carries no stats
