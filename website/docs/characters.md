# Characters

Characters are the central entities in the LarpingApp system, representing player avatars within the game world.

## Overview

Characters are defined by:
- Basic information (name, description, etc.)
- Abilities (strength, intelligence, etc.)
- Skills they have learned
- Items they possess
- Conditions affecting them
- Events they have experienced

## Character Structure

A character object typically contains:

'''
{
  "id": "unique-identifier",
  "name": "Character Name",
  "description": "Character description",
  "skills": ["skill-id-1", "skill-id-2"],
  "items": ["item-id-1", "item-id-2"],
  "conditions": ["condition-id-1"],
  "events": ["event-id-1", "event-id-2"],
  "stats": {
    "ability-id-1": {
      "name": "Ability Name",
      "base": 10,
      "value": 12,
      "audit": [...]
    },
    "ability-id-2": {
      "name": "Another Ability",
      "base": 8,
      "value": 9,
      "audit": [...]
    }
  }
}
'''

## Character Creation

When creating a character:
1. Provide basic information (name, description)
2. Optionally assign initial skills, items, conditions, and events
3. The system automatically calculates ability scores based on these assignments

## Character Updates

When updating a character:
1. Modify any character properties as needed
2. The system automatically recalculates ability scores if relevant properties change

## Character Calculation

Character abilities are dynamically calculated based on:
- Base ability values
- Effects from skills, items, conditions, and events

For detailed information on how abilities are calculated, see the [Abilities documentation](abillities.md).

## Character PDF Generation

The system supports generating character sheets as PDFs using templates. This allows for:
- Customized character sheet layouts
- Printing physical character sheets for LARP events
- Sharing character information in a standardized format

## Technical Implementation

Characters are managed by:
- 'ObjectsController' - Handles API requests for character operations
- 'CharacterService' - Provides character-specific business logic
- 'ObjectService' - Handles generic object operations (create, read, update, delete)

The character calculation system is designed to be extensible, allowing for new types of character attributes and effects to be added without changing the core logic. 