# character-custom-fields Specification

**Status**: implemented
**Scope**: larpinq
**OpenSpec changes**:
- `characters-custom-fields` (archived 2026-09-30)

## Purpose

Game masters add their own fields to character sheets per world, and players
fill in the fields meant for them. From larpinq matrix row `chr-custom-fields`.

The definition schema, the two value properties and their rules are in
`lib/Settings/register.d/characters-custom-fields.json`, pinned by
`tests/unit/Settings/CustomFieldsFragmentTest.php`; the value check by
`tests/unit/Service/CustomFieldGuardTest.php` and
`tests/unit/Listener/CharacterRequirementListenerTest.php`; the Extra fields
tab and the Character fields pages by `tests/vitest/characterCustomFields.spec.js`;
the browser and API proof is
`tests/e2e/workflows/character-custom-fields.workflow.spec.ts`.

## Requirements

### Requirement: Game masters define extra character fields per world (REQ-CCF-001)

A game master SHALL be able to create, edit and remove character field
definitions with a label, a key, a type (text, number, choice or yes-no),
choices for a choice field, a visibility (game masters only, or game masters
and the owner), and an order. A definition without a world MUST apply to every
world.

#### Scenario: A game master adds a bloodline field

- GIVEN world "Aldmoor"
- WHEN a game master creates field "Bloodline" of type choice with human, elven and dwarven on the Character fields page
- THEN the character page of every Aldmoor character shows a "Bloodline" input with those three choices

### Requirement: The character page shows the fields of its world (REQ-CCF-002)

The character detail page SHALL show an "Extra fields" section with one input
per definition of the character's world, in the defined order, and MUST save
the values on the character.

#### Scenario: A player fills in her patron god

- GIVEN "Mirela the Wanderer" belongs to Aldmoor and is owned by Anna
- WHEN Anna enters "The Grey Lady" in "Patron god" and saves
- THEN the character holds "The Grey Lady" for key `patron-god`

### Requirement: Private fields stay with game masters (REQ-CCF-003)

Values of fields with visibility game masters MUST be readable and writable by
game masters only, on every read path, including the owner's own character.

#### Scenario: A secret allegiance

- GIVEN field "True allegiance" has visibility game masters and Mirela's value is rebels
- WHEN Anna opens "Mirela the Wanderer"
- THEN the section does not show "True allegiance" and the API response has no private values

### Requirement: Values match their definition (REQ-CCF-004)

A character write SHALL be refused, with an error naming the key, when a value
does not match its field's type, is not one of its choices, has no definition in
the character's world, or sits in the property that does not match its
visibility.

#### Scenario: Text in a number field

- GIVEN field "Scars" is a number field
- WHEN a client sets scars to "many" on "Mirela the Wanderer" through the objects API
- THEN the write is refused with an error on key `scars`
