# Skills

Skills represent abilities, talents, or knowledge that characters can acquire in the LarpingApp system.

## Overview

Skills can:
- Provide effects that modify character abilities
- Represent combat techniques, magical abilities, crafting knowledge, etc.
- Be learned or improved by characters over time

## Skill Structure

A skill object typically contains:

'''
{
  "id": "unique-identifier",
  "name": "Skill Name",
  "description": "Skill description",
  "effects": ["effect-id-1", "effect-id-2"],
  "prerequisites": ["skill-id-1"],
  "level": 1,
  "properties": {
    "learningTime": "2 days",
    "difficulty": "medium"
  }
}
'''

## Skill Categories

Skills can be categorized into various types, such as:
- Combat skills
- Magic skills
- Crafting skills
- Social skills
- Survival skills

## Skill Effects

Skills can have effects that modify character abilities when the character possesses the skill. For example:
- A "Swordsmanship" skill might provide +2 to Attack
- A "Meditation" skill might provide +1 to Mana Regeneration
- An "Alchemy" skill might enable crafting of potions

When a character learns or forgets a skill, their abilities are automatically recalculated.

## Skill Prerequisites

Skills can have prerequisites, requiring characters to have certain other skills before they can learn them. This allows for skill trees or progression paths.

## Technical Implementation

Skills are managed by:
- 'ObjectsController' - Handles API requests for skill operations
- 'ObjectService' - Handles generic object operations (create, read, update, delete)

When a skill is updated, the system automatically recalculates the abilities of all characters who possess that skill. 