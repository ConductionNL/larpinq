---
status: active
---
# Game Mechanics Design

Game entities (Ability, Effect, Skill, Item, Condition, Event) are OpenRegister
objects — associative arrays fetched via `RegisterObjectFetcher`. There are no
local entity classes to serialise, so "entity serialization" is tested as: each
mechanic's array shape is correctly consumed and derived by
`CharacterService::calculateCharacter()` / `calculateAllCharacters()`.

- **Per-mechanic unit tests** (`tests/unit/Service/GameMechanicsTest.php`): one
  focused test per mechanic, each driving a minimal world through
  `CharacterService` with a mocked `RegisterObjectFetcher`.
- **Effect-chain integration test** (`tests/integration/EffectChainIntegrationTest.php`):
  a full world (multiple abilities, mixed positive/negative and
  cumulative/non-cumulative effects, all entity types) resolved in one pass.
  Asserts additive composition, non-cumulative dedup across entities, cumulative
  stacking, audit-trail length, and per-character independence.

Both suites are registered in `phpunit.xml` and `phpunit-unit.xml`.
