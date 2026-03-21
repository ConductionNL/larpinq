# Game Mechanics Specification

## Problem
Game mechanics covers the interconnected system of Skills, Items, Conditions, Effects, and Abilities (stats) that form the LARP rule engine. Effects are the fundamental building blocks -- they modify ability scores via positive or negative modifiers. Skills, items, conditions, and events each contain arrays of effect references that are applied to characters during stat calculation by `CharacterService.calculateCharacter()`. Abilities define the numeric stats that effects target. Skills additionally support a prerequisite system. This specification documents the CRUD operations, data models, effect chain integrity, and interactions between these entity types.
**Key source files:**
- `lib/Db/Skill.php`, `lib/Db/Item.php`, `lib/Db/Condition.php`, `lib/Db/Effect.php`, `lib/Db/Ability.php` -- Entity classes
- `lib/Service/CharacterService.php` -- Effect application logic (`applyEntityEffects()`, `applyEffects()`, `calculateEffect()`, `applyModifierToAbility()`)
- `src/store/modules/object.js` -- Generic object store
- `docs/Schema/Skill.json`, `docs/Schema/Item.json`, etc. -- JSON schemas

## Proposed Solution
Implement Game Mechanics Specification following the detailed specification. Key requirements include:
- Requirement: Ability (Stat) CRUD
- Requirement: Effect CRUD and Mechanics
- Requirement: Skill CRUD and Prerequisites
- Requirement: Item CRUD
- Requirement: Condition CRUD

## Scope
This change covers all requirements defined in the game-mechanics specification.

## Success Criteria
- Create an ability
- Update an ability base value
- Delete an ability referenced by effects
- List abilities with search
- Ability initialization in stat engine
