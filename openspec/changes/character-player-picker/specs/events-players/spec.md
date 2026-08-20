# events-players Specification

## ADDED Requirements

### Requirement: Player-to-Nextcloud-account link

The `player` schema MUST declare a `userUid` field
(`type:"string", format:"user"`) so a game master can link a `player` object to
the Nextcloud user account that plays them. This field is complementary to the
existing player↔contacts leaf (`player-to-contacts-leaf` capability, ADR-001)
which holds real-world person data (name, email, address) — `userUid` holds only
the Nextcloud login identity used for ownership-based notifications and access,
and does not duplicate or replace any contacts-leaf field.

#### Scenario: Link a player to a Nextcloud account

- GIVEN a game master is editing player "Alice"
- WHEN they select Nextcloud user "alice" in the "Nextcloud user" field
- THEN `player.userUid` MUST be set to `"alice"`
- AND any character whose `ocName` resolves to this player MUST derive
  `ownerUid = "alice"` via the `x-openregister-calculations` chain
  (`character-management` REQ-092 / CHAR-011)

#### Scenario: userUid is independent of the contacts leaf

- GIVEN player "Bob" has a linked OR contact with email "bob@example.com"
  (via the `player-to-contacts-leaf` integration)
- WHEN a game master separately sets `player.userUid` to `"bob"`
- THEN both the contacts-leaf person data and `userUid` MUST coexist on the same
  player object without conflict
- AND removing the contacts-leaf link MUST NOT clear `userUid`, and vice versa
