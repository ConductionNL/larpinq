# Items

Items represent physical or virtual objects that characters can possess in the LarpingApp system.

## Overview

Items can:
- Provide effects that modify character abilities
- Be acquired, used, and traded by characters
- Represent equipment, consumables, quest items, or other game objects

## Item Structure

An item object typically contains:

'''
{
  "id": "unique-identifier",
  "name": "Item Name",
  "description": "Item description",
  "effects": ["effect-id-1", "effect-id-2"],
  "type": "weapon",
  "properties": {
    "damage": 5,
    "weight": 2,
    "value": 100
  }
}
'''

## Item Types

Items can be categorized into various types, such as:
- Weapons
- Armor
- Consumables
- Quest items
- Crafting materials
- Magical artifacts

## Item Effects

Items can have effects that modify character abilities when the character possesses the item. For example:
- A sword might provide +2 to Strength
- A magical amulet might provide +1 to Intelligence and -1 to Strength
- A cursed item might apply negative effects to various abilities

When a character acquires or loses an item, their abilities are automatically recalculated.

## Technical Implementation

Items are managed by:
- 'ObjectsController' - Handles API requests for item operations
- 'ObjectService' - Handles generic object operations (create, read, update, delete)

When an item is updated, the system automatically recalculates the abilities of all characters who possess that item. 