## ADDED Requirements

### Requirement: Removing a skill returns its XP (REQ-CSP-005)

When a skill is removed from a character, its XP cost SHALL stop counting as spent on the next calculation, so XP left goes up by that cost. A remaining skill that needed the removed one SHALL be flagged, not removed.

#### Scenario: A player unlearns herb lore

- GIVEN "Mirela the Wanderer" has 30 XP left and the skill "Kruidenkunde" that cost 6 XP
- WHEN a game master removes "Kruidenkunde" from her
- THEN her Stats tab shows 36 XP left
- AND XP spent is 6 lower

### Requirement: The refund shows in the XP history (REQ-CSP-006)

The stats page SHALL list each removed skill in the XP history with the XP it returned, the date and who removed it, as LrKarakterStatistieken draws it. The line SHALL be read from the character's audit trail, so it never adds XP a second time.

#### Scenario: The game master sees why XP went up

- GIVEN game master Anna removed "Kruidenkunde" from "Mirela the Wanderer" today
- WHEN Anna opens Mirela's stats page
- THEN the XP history shows "Kruidenkunde verwijderd: 6 XP teruggegeven" with today's date and her name
- AND XP left is the same as before she opened the page
