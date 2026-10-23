# character-management Delta: my-character-sheet

## ADDED Requirements

### Requirement: A player opens their own character sheet from the menu

larpinq SHALL offer a menu entry "My character sheet" that lists the signed-in user's own characters (`ownerUid` equals
the current user) and opens a character's page from its card.

#### Scenario: Sanne plays one character

- **GIVEN** Sanne owns the character Elandra van de Wilgen
- **WHEN** she opens My character sheet
- **THEN** she sees Elandra's card, and only hers, and a click opens Elandra's page
