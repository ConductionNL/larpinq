# Game Mechanics

## Overview

The game mechanics system encompasses Skills, Items, Conditions, Effects, and Abilities (stats) that form the LARP rule engine. Effects are the fundamental building blocks that modify ability scores.

## Features

### Abilities (Stats)
- Numeric stats that characters are scored on (XP, mana, HP, etc.)
- Base value defaults to 0
- Used by `CharacterService.initializeAbilityScores()` for starting values

### Effects
- Modify ability scores via positive or negative modifiers
- Target one or more abilities via `abilities[]` array or `stat_id`
- Bridge between game elements (skills, items, conditions, events) and character stats

### Skills
- Carry effects that modify character abilities
- Support prerequisite system (other skills, stats, conditions)

### Items
- Equipment and objects that carry effects
- CRUD via generic object store

### Conditions
- Status effects (poisoned, blessed, etc.) that carry effects
- Applied to characters during stat calculation

## Navigation

- Abilities, Skills, Effects: Settings footer area
- Items, Conditions: Main navigation area

## Screenshot

![Game Mechanics](/screenshots/game-mechanics.png)

## Technical Details

- Effect chain: `collectEffectAbilities` -> `calculateEffect` -> `applyModifierToAbility`
- Deterministic order: skills -> items -> conditions -> events
- Full audit trail per ability modification
