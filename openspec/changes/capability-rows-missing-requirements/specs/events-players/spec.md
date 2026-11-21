# events-players delta

## ADDED Requirements

### Requirement: A player is linked to their Nextcloud user account (REQ-EPL-001)

A player record SHALL hold the user id of the Nextcloud account of the person it describes, and the player page MUST show it. Ownership checks and notifications for that player's characters use this account.

#### Scenario: A game master links Anna's account

- GIVEN player "Anna de Vries" and Nextcloud user `anna`
- WHEN a game master sets the account of "Anna de Vries" to `anna` and saves
- THEN the player page of "Anna de Vries" shows `anna` as her account

### Requirement: The player page lists the player's characters (REQ-EPL-002)

The player page SHALL list every character whose player is this player, with each character's name, type and approval, and each entry MUST open the character.

#### Scenario: Anna's characters

- GIVEN player "Anna de Vries" plays "Mirela the Wanderer" and played "Old Captain Harrow"
- WHEN a game master opens the player page of "Anna de Vries"
- THEN the characters list shows "Mirela the Wanderer" and "Old Captain Harrow"
- AND choosing "Mirela the Wanderer" opens her character page
