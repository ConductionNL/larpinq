# RPG System

## Overview

The RPG system is the cohesive rule engine that ties together Abilities, Effects, Skills, Items, Conditions, and Events. The `CharacterService` applies effects in a deterministic order with full audit trail.

## Stat Calculation Pipeline

1. `initializeAbilityScores()` — Creates stat map from all abilities with base values
2. `applyEntityEffects(skills)` — Apply skill effects
3. `applyEntityEffects(items)` — Apply item effects
4. `applyEntityEffects(conditions)` — Apply condition effects
5. `applyEntityEffects(events)` — Apply event effects

## Effect Application

Each effect specifies:
- `abilities[]` — Target ability IDs
- `stat_id` — Alternative single ability target
- `modifier` — Integer value to add/subtract
- `modification` — "positive" (add) or "negative" (subtract)

## Entity Preloading

The `CharacterService` constructor preloads ALL entities into memory indexed by ID, avoiding N+1 queries during stat calculation.

## Audit Trail

Every ability modification is recorded with:
- Effect that caused the change
- Old value before modification
- New value after modification

## Known Issues

- `allowed_negative` field exists in DB but is not enforced by the stat engine
- `Ability.php` entity does not expose `base` and `allowed_negative` in `getJsonFields()`

## Technical Details

- Service: `lib/Service/CharacterService.php`
- Batch processing: `calculateAllCharacters()` for all characters
- Single processing: `calculateCharacter(array $character)` for one character
