# setting-management delta

## ADDED Requirements

### Requirement: The world page shows its description (REQ-SMD-001)

A world SHALL carry a free-text description of its setting and tone, and the world page MUST show it to everyone who can read the world.

#### Scenario: A player reads what Aldmoor is like

- GIVEN world Aldmoor has the description "A rain-soaked river kingdom where two queens claim one crown"
- WHEN player Anna opens the world page of Aldmoor
- THEN she reads "A rain-soaked river kingdom where two queens claim one crown"

### Requirement: A world keeps a timeline of its history (REQ-SMD-002)

A game master SHALL be able to record dated history entries for a world, each with an in-game date, a title, a text and a visibility. The world page MUST show a timeline that lists those entries together with the world's past events, in date order. Entries meant for game masters MUST NOT reach players.

#### Scenario: The founding comes before the siege

- GIVEN world Aldmoor has history entry "The founding of Aldmoor" and past event "Summer Siege 2025"
- WHEN a game master opens the timeline of Aldmoor
- THEN "The founding of Aldmoor" is listed before "Summer Siege 2025"

#### Scenario: A hidden entry stays hidden

- GIVEN history entry "The murder of the old king" has visibility game masters
- WHEN player Anna opens the timeline of Aldmoor
- THEN the entry is not listed
