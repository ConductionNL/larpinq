# Events

Events represent significant occurrences in a character's history that can affect their abilities in the LarpingApp system.

## Overview

Events can:
- Provide effects that modify character abilities
- Represent story milestones, achievements, or significant experiences
- Be permanent or temporary parts of a character's history

## Event Structure

An event object typically contains:

'''
{
  "id": "unique-identifier",
  "name": "Event Name",
  "description": "Event description",
  "effects": ["effect-id-1", "effect-id-2"],
  "date": "2023-05-15T14:30:00Z",
  "duration": "permanent",
  "properties": {
    "location": "Dark Forest",
    "participants": ["character-id-1", "character-id-2"]
  }
}
'''

## Event Types

Events can be categorized into various types, such as:
- Story milestones
- Combat encounters
- Discoveries
- Training sessions
- Magical rituals

## Event Effects

Events can have effects that modify character abilities when the character has experienced the event. For example:
- Defeating a dragon might provide +1 to Courage
- Studying at a magical academy might provide +2 to Arcane Knowledge
- Surviving a curse might provide immunity to similar curses

When a character experiences or forgets an event, their abilities are automatically recalculated.

## Technical Implementation

Events are managed by:
- 'ObjectsController' - Handles API requests for event operations
- 'ObjectService' - Handles generic object operations (create, read, update, delete)

When an event is updated, the system automatically recalculates the abilities of all characters who have experienced that event. 