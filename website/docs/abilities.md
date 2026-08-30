# Character Ability Calculation System

This document explains how character abilities are calculated in the LarpingApp system.

## Overview

The LarpingApp uses a dynamic ability calculation system that determines character statistics based on:

1. Base ability values
2. Skills the character possesses
3. Items the character carries
4. Conditions affecting the character
5. Events the character has experienced

When any of these components changes, the system automatically recalculates all character statistics to ensure they remain up-to-date.

## Calculation Process

### 1. Initialization

When calculating a character's abilities:

- The system starts by loading all abilities defined in the system
- Each ability is initialized with its base value
- An audit trail is created to track how each ability's value changes

### 2. Effect Application

The system then applies effects from various sources in the following order:

1. **Skills**: Effects from all skills the character possesses
2. **Items**: Effects from all items the character carries
3. **Conditions**: Effects from all conditions affecting the character
4. **Events**: Effects from all events the character has experienced

### 3. Effect Calculation

Each effect can modify one or more abilities. When an effect is applied:

- The system identifies which abilities are affected
- It determines the modification type (positive or negative)
- It applies the modifier value to the current ability value
- It records the change in the audit trail, including the source of the change

### 4. Result Storage

After all calculations are complete:

- The updated ability scores are stored in the character's `stats` property
- The character is saved to the database with the updated values

## Automatic Recalculation

The system automatically recalculates all character abilities when:

- An ability is created or updated
- A character is directly modified

This ensures that all character statistics remain consistent with the current state of the game world.

## Example Calculation

Consider a character with the following:

1. A "Strength" ability with base value 10
2. A "Warrior" skill that provides +2 to Strength
3. A "Magic Sword" item that provides +1 to Strength
4. A "Wounded" condition that applies -1 to Strength

The calculation would proceed as follows:

1. Initialize Strength = 10 (base value)
2. Apply "Warrior" skill: 10 + 2 = 12
3. Apply "Magic Sword" item: 12 + 1 = 13
4. Apply "Wounded" condition: 13 - 1 = 12

The final Strength value would be 12, and the audit trail would show each modification with its source.

## Technical Implementation

The calculation is primarily handled by the `CharacterService` class, which:

1. Loads all relevant entities (abilities, skills, items, conditions, events)
2. Provides methods to calculate abilities for a single character or all characters
3. Tracks the audit trail of all ability modifications

The system is designed to be extensible, allowing new types of effects and abilities to be added without changing the core calculation logic. 