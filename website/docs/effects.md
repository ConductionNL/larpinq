# Effects

Effects are the core mechanism for modifying character abilities in the LarpingApp system.

## Overview

Effects:
- Modify one or more character abilities
- Can be positive or negative
- Are attached to skills, items, conditions, or events
- Are automatically applied when calculating character abilities

## Effect Structure

An effect object typically contains:

'''
{
  "id": "unique-identifier",
  "name": "Effect Name",
  "description": "Effect description",
  "abilities": ["ability-id-1", "ability-id-2"],
  "stat_id": "ability-id-1",  // Legacy field, use abilities instead
  "modifier": 2,
  "modification": "positive",  // or "negative"
  "duration": "permanent",     // or a specific duration
  "properties": {
    "stackable": true,
    "condition": "when_equipped"
  }
}
'''

## Effect Types

Effects can be categorized by their modification type:
- Positive effects (buffs) - Increase ability values
- Negative effects (debuffs) - Decrease ability values

## Effect Application

Effects are applied during character ability calculation:
1. The system identifies which abilities are affected by the effect
2. It determines the modification type (positive or negative)
3. It applies the modifier value to the current ability value
4. It records the change in the audit trail, including the source of the effect

## Effect Sources

Effects can come from various sources:
- Skills that the character has learned
- Items that the character possesses
- Conditions affecting the character
- Events that the character has experienced

## Technical Implementation

Effects are managed by:
- 'ObjectsController' - Handles API requests for effect operations
- 'ObjectService' - Handles generic object operations (create, read, update, delete)
- 'CharacterService' - Applies effects during ability calculation

When an effect is updated, the system automatically recalculates the abilities of all characters who are affected by that effect through any source.

For detailed information on how effects are applied during ability calculation, see the [Abilities documentation](abillities.md). 