---
status: active
---
# Game Mechanics Implementation

Add the tests that verify the LarpingApp game-mechanics engine: per-mechanic
coverage for Ability, Effect, Skill, Item and Condition, and an end-to-end
verification of effect-chain integrity.

LarpingApp stores every game entity as an OpenRegister object (associative
array), not a local entity class, so the mechanics live in `CharacterService`
(effect-chain resolution + stat derivation over those objects). The tests are
written against that real model rather than against non-existent entity classes.
