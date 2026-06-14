---
status: draft
---

# RPG System — delta for event-xp-award-workflow

## MODIFIED Requirements

### Requirement: Stat Calculation Order and Audit

The stat calculation engine MUST apply effects in a deterministic order and
produce a complete audit trail. XP awards (see the `event-xp-awards`
capability) are applied as a fifth stage after the four entity-effect
stages, affecting only the XP ability.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CALC-001 | `calculateCharacter()` MUST initialize ability scores from base values via `initializeAbilityScores()` | MUST | Implemented |
| CALC-002 | Effects MUST be applied in order: skills first, then items, then conditions, then events; XP awards are applied after all entity effects | MUST | Planned (order extension) |
| CALC-003 | Within each entity type, effects MUST be applied in the order the entities appear in the character's association array | MUST | Implemented |
| CALC-004 | Each effect application MUST produce an audit entry with `{type: "effect", effect: {...}, old: number, new: number}` | MUST | Implemented |
| CALC-005 | The final stats object MUST contain per-ability entries with `{name, base, value, audit[]}` | MUST | Implemented |
| CALC-006 | Missing entities (referenced by UUID but not found in preloaded maps) MUST be silently skipped | MUST | Implemented |
| CALC-007 | Empty or null entity association arrays MUST be skipped without error by `applyEntityEffects()` | MUST | Implemented |
| CALC-008 | Each XP award applied MUST produce an audit entry with `{type: "xpAward", award: {...}, old: number, new: number}` on the XP ability, resolved via the app register config (name-match fallback), never a hardcoded UUID | MUST | Planned |
| CALC-009 | XP awards whose character cannot be resolved MUST be skipped; an award whose event is missing MUST still count (provenance, not validity) — never an error | MUST | Planned |

#### Scenario: Full stat calculation with all entity types

- GIVEN ability "HP" (base 20)
- AND character has: skill with effect +5 HP, item with effect +3 HP, condition with effect -2 HP, event with effect +1 HP
- WHEN stats are calculated
- THEN HP MUST equal 27 (20 + 5 + 3 - 2 + 1)
- AND audit MUST contain 4 entries in order: skill effect, item effect, condition effect, event effect
- AND audit[0].old MUST be 20, audit[0].new MUST be 25
- AND audit[1].old MUST be 25, audit[1].new MUST be 28
- AND audit[2].old MUST be 28, audit[2].new MUST be 26
- AND audit[3].old MUST be 26, audit[3].new MUST be 27

#### Scenario: Character with no associations

- GIVEN abilities "Strength" (base 10) and "Mana" (base 5)
- AND a character with no skills, items, conditions, or events
- WHEN stats are calculated
- THEN Strength MUST be 10 and Mana MUST be 5 (base values only)
- AND both audit arrays MUST be empty

#### Scenario: Character with missing entity reference

- GIVEN character has skills=["valid-skill-uuid", "deleted-skill-uuid"]
- AND "deleted-skill-uuid" is not in the preloaded skills map
- WHEN `applyEntityEffects()` processes the skills
- THEN "valid-skill-uuid" MUST be processed normally
- AND "deleted-skill-uuid" MUST be skipped (entity lookup returns null)

#### Scenario: XP awards applied as the fifth stage

- GIVEN ability "XP" (base 0)
- AND character has an event effect "+2 XP" and xpAwards of 3 and 1
- WHEN stats are calculated
- THEN XP MUST equal 6 (0 + 2 + 3 + 1)
- AND the XP audit MUST contain the event-effect entry first, then two entries of type "xpAward"
- AND abilities other than XP MUST be unaffected by the awards
