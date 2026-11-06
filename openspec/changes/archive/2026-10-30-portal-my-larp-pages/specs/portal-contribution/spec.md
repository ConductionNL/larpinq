# portal-contribution Delta: portal-my-larp-pages

## ADDED Requirements

### Requirement: The player has a Mijn larp page in the portal

The larpinq contribution SHALL give the `player` audience a page "Mijn larp" in the group "Verenigingen" that lists the
player's own characters, offers Create a character, and lists the player's own registrations. The registrations SHALL be
scoped by `registration.player` against the `ownerRef` claim and SHALL NOT expose decision stamps, Nextcloud accounts or
transfer bookkeeping. Every block SHALL name a collection or an action of the contribution.

#### Scenario: Sanne sees what she still has to pay

- **GIVEN** Sanne's player profile is linked to her portal account and she registered for Herfstbijeenkomst with payment open
- **WHEN** she opens Mijn larp
- **THEN** she sees her characters and her registration for Herfstbijeenkomst with its payment state and pay-by date

### Requirement: The player reads the rulebook in the portal

The larpinq contribution SHALL give the `player` audience a page "Regelboek" with the rules chapters, ordered, then the
skills. The chapters SHALL be lore pages of category `rules` that players may read and that have no reveal moment.

#### Scenario: A chapter held back until the event

- **GIVEN** a rules chapter visible to players with a reveal moment next week
- **WHEN** a player opens the Regelboek
- **THEN** the chapter is not listed
