---
status: active
---
# Tasks
- [ ] Add entity unit tests for Ability, Effect, Skill, Item, Condition
- [ ] Add effect chain integration test

> Reconciliation 2026-06-14: code-presence gate FAILED. No Ability/Effect/Skill/Item/Condition
> entity test files exist under `tests/unit/` (only Character/Dashboard/Settings/RegisterObjectFetcher
> tests are present) and no effect-chain integration test exists (`tests/integration/` holds only the
> Postman collection). LarpingApp has no local entity classes — all game objects live in OpenRegister —
> so these tasks were checked but never built. Tasks reset to unchecked; change kept OPEN.
