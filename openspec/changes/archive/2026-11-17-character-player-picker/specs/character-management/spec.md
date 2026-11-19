# character-management Specification

## MODIFIED Requirements

### Requirement: Character CRUD Operations

The system MUST support creating, reading, updating, and deleting characters with all required fields.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-001 | Create characters with name, description, background, faith, notice, and notes fields | MUST | Implemented |
| CHAR-002 | Update existing characters with all editable fields | MUST | Implemented |
| CHAR-003 | Delete characters with confirmation dialog | MUST | Implemented |
| CHAR-004 | List characters with search, pagination, and faceted results | MUST | Implemented |
| CHAR-005 | View single character detail page with tabbed interface | MUST | Implemented |
| CHAR-006 | Associate a character with a player profile via `ocName` (`type:"string", format:"uuid", $ref:"player"`) — an inline select-or-create dropdown storing the linked `player` object's UUID, not a free-text name | MUST | Implemented |
| CHAR-007 | Character name is required (validated by Zod on frontend) | MUST | Implemented |
| CHAR-008 | Characters MUST be retrievable via `RegisterObjectFetcher.getObject('character', id)` | MUST | Implemented |
| CHAR-009 | Character lists MUST be retrievable via `RegisterObjectFetcher.getObjects('character')` with pagination and filtering | MUST | Implemented |
| CHAR-010 | Created/updated characters MUST have stats recalculated via `CharacterService.calculateCharacter()` | MUST | Implemented |
| CHAR-011 | `character.ownerUid` MUST be a read-only field (`visible:false, readOnly:true`) materialised by `x-openregister-calculations` from `@ref.player.userUid` — it is never manually editable | MUST | Implemented |
| CHAR-012 | `character.approved` MUST declare `"widget":"switch"` and render as a toggle, keeping its `enum:["no","approved"]` and `x-openregister-lifecycle` transitions unchanged | MUST | Implemented |
<!-- Previous behavior: ocName was a free-text string with no $ref, ownerUid was a manually-editable free-text field never actually set by any code path, and approved rendered as a select box. -->

#### Scenario: Create a new character

- GIVEN the user is on the Characters page
- WHEN they click "Karakter toevoegen" and fill in name "Sir Lancelot" and description "A noble knight"
- AND select player "John Doe" from the OC Name dropdown (or type a new name to create the player inline)
- AND click "Aanmaken"
- THEN a new character MUST be created via the character store
- AND `ocName` MUST store "John Doe"'s player object UUID, not the display name
- AND `CharacterService.calculateCharacter()` MUST compute the stats (empty stats if no skill/item/condition associations)
- AND the character list MUST refresh to include "Sir Lancelot"
- AND the modal MUST close after showing a success notification

#### Scenario: approved renders as a toggle

- GIVEN a character form is open for editing
- WHEN the GM looks at the "Approved" field
- THEN it MUST render as a toggle, not a select box
- AND flipping the toggle and saving MUST drive the same `x-openregister-lifecycle`
  `no`↔`approved` transition, and the same `character-approved` notification
  behavior, as the select box it replaces

#### Scenario: Update an existing character

- GIVEN character "Sir Lancelot" exists
- WHEN the user opens the character detail, clicks Edit, and changes the background to "Born in Camelot"
- AND saves the character
- THEN the character MUST be updated with the new background
- AND stats MUST be recalculated
- AND the detail view MUST refresh to show the new background

#### Scenario: Delete a character

- GIVEN character "Sir Lancelot" exists in the list
- WHEN the user clicks the delete action and confirms in the dialog
- THEN the character MUST be removed via DELETE /api/objects/character/{id}
- AND the character list MUST refresh
- AND the active character MUST be cleared

#### Scenario: Search characters by name

- GIVEN characters "Sir Lancelot", "Merlin", and "Dragonborn" exist
- WHEN the user types "dragon" into the search field
- THEN after a 500ms debounce the character list MUST refresh with only "Dragonborn"
- WHEN the user clears the search field
- THEN the full character list MUST be displayed again

#### Scenario: Create character with required name validation

- GIVEN the user opens the character creation modal
- WHEN they leave the name field empty and attempt to save
- THEN the Zod validation MUST prevent submission
- AND an error indicator MUST appear on the name field

#### Scenario: ownerUid is derived, not entered

- GIVEN a character form is open for editing
- WHEN the GM looks at the form fields
- THEN `ownerUid` MUST NOT appear as an editable field (`visible:false`)
- AND saving the character with `ocName` set to player "Alice" (Nextcloud uid `alice`)
- THEN `character.ownerUid` MUST be materialised to `"alice"` via
  `x-openregister-calculations` reading `@ref.player.userUid`, without any form
  input for `ownerUid`

## ADDED Requirements

### Requirement: Player reference resolution on character objects

The `character` schema MUST declare `x-openregister-references.player`
(`schema:"player", mode:"relatedObject", field:"ocName"`) so that OpenRegister
resolves the `player` object referenced by `ocName` and exposes its fields as
`@ref.player.<field>` for use in the same character's declarative expressions
(currently consumed by the `ownerUid` calculation, REQ per CHAR-011 above).

#### Scenario: Character resolves its linked player via @ref

- GIVEN a character with `ocName` set to player "Bob"'s UUID
- AND player "Bob" has `userUid` set to `"bob"`
- WHEN OpenRegister evaluates the character's declarative expressions
- THEN `@ref.player.userUid` MUST resolve to `"bob"`
- AND `@ref.player.name` MUST resolve to `"Bob"`
