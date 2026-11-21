# rpg-system Specification (delta)

## Purpose

A unique item or a unique condition belongs to one character at a time, and
larpinq refuses a write that would give it a second holder. From larpinq
matrix rows `rul-unique-item-not-enforced` and `rul-unique-condition-not-enforced`.

## ADDED Requirements

### Requirement: A unique item has one holder (REQ-UHE-001)

When a character write adds an item whose `unique` flag is true, and another
character already holds that item, larpinq SHALL refuse the write and name
the current holder. A holder MUST be counted from both the character's
`items[]` and the item's `characters[]`.

#### Scenario: A game master gives a held unique item to a second character

- GIVEN unique item "Crown of Aldmoor" is in the items of character "Queen Isolde"
- WHEN a game master adds "Crown of Aldmoor" to the items of character "Sir Bertram" on the character detail page
- THEN the save is refused
- AND the page shows that "Crown of Aldmoor" is held by "Queen Isolde"

#### Scenario: A non-unique item goes to many characters

- GIVEN item "Healing potion" with unique set to false is held by "Queen Isolde"
- WHEN a game master adds "Healing potion" to "Sir Bertram"
- THEN the save succeeds

#### Scenario: An unrelated edit is not blocked by an old conflict

- GIVEN "Crown of Aldmoor" is already held by both "Queen Isolde" and "Sir Bertram" from before this change
- WHEN a game master changes only the background of "Sir Bertram"
- THEN the save succeeds

### Requirement: A unique condition affects one character (REQ-UHE-002)

When a character write adds a condition whose `unique` flag is true, and
another character already has that condition, larpinq SHALL refuse the write
and name the character who has it. Holders MUST be counted from both the
character's `conditions[]` and the condition's `characters[]`.

#### Scenario: The curse of the Ashen King can only rest on one head

- GIVEN unique condition "Curse of the Ashen King" is on character "Mirela"
- WHEN a game master adds it to character "Tomas" through the REST API
- THEN the API answers with a validation error on `conditions`
- AND the error names "Mirela" as the current holder

### Requirement: The item and condition pages keep the rule too (REQ-UHE-003)

An item or condition write SHALL be refused when `unique` is true and the
holders on both sides number more than one, including when the write switches
`unique` from false to true.

#### Scenario: A game master marks a shared item unique

- GIVEN item "Silver key" with unique set to false is held by "Queen Isolde" and "Sir Bertram"
- WHEN a game master switches "Unique artifact" on for "Silver key" on the item detail page
- THEN the save is refused
- AND the page names both holders so the game master can remove one first

### Requirement: The refusal is readable (REQ-UHE-004)

The refusal MUST be returned as a field error on the field the user changed
(`items`, `conditions`, `characters` or `unique`), and the character and item
pages SHALL show it in Dutch and English with the holder's name.

#### Scenario: A Dutch-speaking game master sees the holder

- GIVEN a game master whose Nextcloud language is Dutch
- WHEN the save of "Sir Bertram" is refused because "Queen Isolde" holds "Crown of Aldmoor"
- THEN the message on the character page is in Dutch and names "Queen Isolde"

### Requirement: Today's conflicts can be listed (REQ-UHE-005)

An administrator MUST be able to list every unique item and unique condition
that more than one character holds, with the holders, through
`occ larpinq:unique-holders:check`.

#### Scenario: An administrator finds an old conflict

- GIVEN "Crown of Aldmoor" is held by "Queen Isolde" and "Sir Bertram" from before this change
- WHEN an administrator runs `occ larpinq:unique-holders:check`
- THEN the output lists "Crown of Aldmoor" with both holders
- AND the command exits with a non-zero code while a conflict exists
