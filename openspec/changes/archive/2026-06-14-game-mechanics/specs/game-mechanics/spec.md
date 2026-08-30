---
status: active
type: delta
parent: openspec/specs/game-mechanics/spec.md
---
# Game Mechanics Delta Spec

## ADDED Requirements

### Requirement: All game mechanics MUST be unit-tested over the OpenRegister object model

Each game mechanic (Ability, Effect, Skill, Item, Condition) MUST be covered by
a focused unit test that verifies its data shape is correctly consumed and
derived by the character stat-derivation engine (`CharacterService`). LarpingApp
stores every game entity as an OpenRegister object — an associative array — not
a local PHP entity class, so the tests MUST exercise the OR-object model rather
than asserting against non-existent entity classes.

#### Scenario: Ability mechanic serialises into derived stats

- **WHEN** a character is calculated against an Ability object carrying `id`, `name` and `base`
- **THEN** the derived `stats` block contains that ability with its name, base score, equal value and an empty audit trail

#### Scenario: Effect mechanic applies its modifier and records an audit entry

- **WHEN** an Effect object with a positive modification targets an ability via a linked skill
- **THEN** the modifier is added to the ability and a lean audit entry records the effect id, name and before/after delta

#### Scenario: Skill mechanic routes its linked effects

- **WHEN** a character carries a Skill object that lists effects
- **THEN** those effects are applied, and a skill with no resolvable effects contributes nothing

#### Scenario: Item mechanic applies worn effects

- **WHEN** a character carries an Item object that lists effects
- **THEN** those effects are applied independently of the skill route

#### Scenario: Condition mechanic applies a negative modifier

- **WHEN** a character carries a Condition object whose effect uses a negative modification
- **THEN** the targeted ability is reduced and the audit records the debuff delta

### Requirement: The effect chain MUST be verified end-to-end

The system MUST provide an integration test that verifies the full effect
chain end-to-end: character through skills, items, conditions and events, to
effects, to ability modifiers, to derived stats. The test MUST drive all
mechanics in a single derivation pass and assert the chain's integrity
invariants.

#### Scenario: Full chain derives a loaded character across all abilities

- **WHEN** a character carrying skills, items, conditions and events is calculated
- **THEN** effects compose additively across every entity type, producing the expected value for each affected ability

#### Scenario: Cumulative and non-cumulative effects behave correctly in the chain

- **WHEN** a cumulative effect and a non-cumulative effect are each reachable via two entities (skill and item)
- **THEN** the cumulative effect stacks on every encounter while the non-cumulative effect applies exactly once, and the audit-trail length reflects the actual number of applications

#### Scenario: Characters are computed independently across the roster

- **WHEN** `calculateAllCharacters` resolves a roster containing both a loaded character and a bare character
- **THEN** the loaded character is fully derived while the bare character retains pure base scores with empty audits (no cross-character bleed)
