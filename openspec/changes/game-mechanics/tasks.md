---
status: active
---
# Tasks
- [x] Add per-mechanic unit tests for Ability, Effect, Skill, Item, Condition over the OpenRegister object model (`tests/unit/Service/GameMechanicsTest.php`)
- [x] Add effect-chain integration test driving all mechanics end-to-end (`tests/integration/EffectChainIntegrationTest.php`)
- [x] Register the integration testsuite in `phpunit.xml` and `phpunit-unit.xml`

> Reconciliation 2026-06-14: code-presence gate FAILED. No Ability/Effect/Skill/Item/Condition
> entity test files existed under `tests/unit/` (only Character/Dashboard/Settings/RegisterObjectFetcher
> tests were present) and no effect-chain integration test existed (`tests/integration/` held only the
> Postman collection). LarpingApp has no local entity classes — all game objects live in OpenRegister —
> so the original tasks ("entity unit tests for X") were checked but never built. Tasks were reset.
>
> Resolution 2026-06-14: the mechanics already live in `CharacterService` (effect-chain resolution +
> stat derivation over OR-object arrays). Built the genuine tests the spec requires against that real
> model: a per-mechanic unit suite (`GameMechanicsTest`, 5 tests — one per mechanic) and an end-to-end
> effect-chain integration test (`EffectChainIntegrationTest`, 3 tests covering composition, cumulative
> vs non-cumulative dedup, audit-trail length, and roster independence). Both wired into the PHPUnit
> configs. Suite: 77 -> 85 tests, all green.
