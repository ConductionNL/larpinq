---
status: approved
---

# Game Mechanics — Design

## Architecture

Game mechanics encompasses Skills, Items, Conditions, Effects, and Abilities. All use the generic object store pattern with OpenRegister as the backend. The `CharacterService` ties them together via the stat calculation engine.

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| View | `ObjectList.vue` | Lists for each entity type with search/pagination |
| View | `ObjectDetail.vue` | Detail views with tabs |
| Store | `object.js` | Generic CRUD for all entity types |
| Router | `router/index.js` | Routes for abilities, skills, items, conditions, effects |
| Navigation | `MainMenu.vue` | Nav items in Settings footer area |
| PHP Service | `CharacterService` | Effect chain: collectEffectAbilities → calculateEffect → applyModifierToAbility |
| DB Entities | `Ability.php`, `Skill.php`, `Item.php`, `Condition.php`, `Effect.php` | Internal entity definitions |

## Effect Chain

```
Character → skills[] → effects[] → abilities[]
         → items[]  → effects[] → abilities[]
         → conditions[] → effects[] → abilities[]
         → events[] → effects[] → abilities[]
```

Effects have `modifier` (int), `modification` (positive/negative), and target `abilities[]`/`stat_id`. The engine processes them deterministically: skills → items → conditions → events.

## Navigation Placement

Abilities, Skills, and Effects are placed in the Settings (footer) area of navigation, as they are configuration/setup entities rather than gameplay entities.
