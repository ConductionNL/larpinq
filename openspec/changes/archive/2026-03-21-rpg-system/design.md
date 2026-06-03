---
status: approved
---

# RPG System — Design

## Architecture

The RPG system is the cohesive rule engine that ties together Abilities, Effects, Skills, Items, Conditions, and Events. The `CharacterService` is the core engine that applies effects in a deterministic order with full audit trail.

## Stat Calculation Pipeline

```
1. initializeAbilityScores()
   → Creates stat map from all abilities with base values

2. applyEntityEffects(skills)
   → For each skill on character, apply its effects

3. applyEntityEffects(items)
   → For each item on character, apply its effects

4. applyEntityEffects(conditions)
   → For each condition on character, apply its effects

5. applyEntityEffects(events)
   → For each event on character, apply its effects
```

## Effect Application

Each effect specifies:
- `abilities[]` — Array of ability IDs to target
- `stat_id` — Alternative single ability target
- `modifier` — Integer value to add/subtract
- `modification` — "positive" (add) or "negative" (subtract)

## Entity Preloading

`CharacterService` constructor calls `loadAllEntities()` which preloads ALL skills, items, conditions, events, effects, and abilities into memory indexed by ID. This avoids N+1 queries during stat calculation.

## Known Issues

- `allowed_negative` field exists in DB migration but is NOT enforced by the stat engine
- `Ability.php` entity only exposes `name` and `description` via `getJsonFields()` (not `base` or `allowed_negative`)
