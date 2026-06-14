---
status: active
---
# Game Mechanics Implementation

## Why

The game-mechanics change was previously marked complete, but reconciliation
(2026-06-14) found the ticked tasks — "entity unit tests for Ability/Effect/
Skill/Item/Condition" and an "effect-chain integration test" — never existed.
LarpingApp stores every game entity as an OpenRegister object (associative
array), not a local PHP entity class, so the original task framing did not map
to the real codebase. The mechanics actually live in `CharacterService`
(effect-chain resolution + stat derivation over OR objects) and need genuine
tests against that model.

## What Changes

Add the tests that verify the LarpingApp game-mechanics engine: per-mechanic
coverage for Ability, Effect, Skill, Item and Condition, and an end-to-end
verification of effect-chain integrity. The tests are written against the real
OpenRegister object model via `CharacterService`, not against non-existent
entity classes, and are registered in both PHPUnit configurations.
