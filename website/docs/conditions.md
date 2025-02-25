# Conditions

Conditions represent temporary or permanent states that affect characters in the LarpingApp system.

## Overview

Conditions can:
- Provide effects that modify character abilities
- Represent physical states, magical influences, or psychological conditions
- Be temporary or permanent
- Have durations or expiration criteria

## Condition Structure

A condition object typically contains:

'''
{
  "id": "unique-identifier",
  "name": "Condition Name",
  "description": "Condition description",
  "effects": ["effect-id-1", "effect-id-2"],
  "duration": "2 days",  // or "permanent"
  "start_date": "2023-05-15T14:30:00Z",
  "properties": {
    "severity": "moderate",
    "curable": true,
    "contagious": false
  }
}
'''

## Condition Types

Conditions can be categorized into various types, such as:
- Physical conditions (wounded, exhausted)
- Magical conditions (blessed, cursed)
- Psychological conditions (frightened, inspired)
- Status effects (poisoned, stunned)

## Condition Effects

Conditions can have effects that modify character abilities while the character is affected by the condition. For example:
- A "Wounded" condition might provide -2 to Strength and Agility
- A "Blessed" condition might provide +1 to all abilities
- A "Poisoned" condition might provide -1 to Constitution per day

When a character gains or loses a condition, their abilities are automatically recalculated.

## Condition Duration

Conditions can have various duration types:
- Permanent conditions that remain until explicitly removed
- Temporary conditions with a fixed duration
- Conditional durations that expire when certain criteria are met

## Technical Implementation

Conditions are managed by:
- 'ObjectsController' - Handles API requests for condition operations
- 'ObjectService' - Handles generic object operations (create, read, update, delete)

When a condition is updated, the system automatically recalculates the abilities of all characters who are affected by that condition. 