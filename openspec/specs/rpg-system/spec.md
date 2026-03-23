---
status: implemented
---

# RPG System

## Purpose

Defines the core RPG mechanics of LarpingApp: Skills, Items, Conditions, Effects, and Abilities (stats). These entities form an interconnected system where Effects serve as the bridge between game elements and character stats. Skills, Items, Conditions, and Events each carry Effects that modify Abilities. Skills additionally support a prerequisite system requiring other skills, stats, conditions, effects, or a minimum score. The stat calculation is performed by `CharacterService` which applies effects in a deterministic order (skills -> items -> conditions -> events) with full audit trail. This spec focuses on the RPG system as a cohesive rule engine rather than individual entity CRUD.

## Requirements

---

### Requirement: Ability (Stat) Management

Abilities represent numeric values on which characters are scored (XP, mana types, HP, combat stats, etc.). They provide the base values that effects modify.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| ABIL-001 | Create abilities with name, description, and base value | MUST | Implemented |
| ABIL-002 | Update existing abilities including base value changes | MUST | Implemented |
| ABIL-003 | Delete abilities with confirmation dialog | MUST | Implemented |
| ABIL-004 | List abilities with search and pagination | MUST | Implemented |
| ABIL-005 | View ability details with relations and audit trail tabs | MUST | Implemented |
| ABIL-006 | Abilities MUST serve as targets for Effect modifiers during stat calculation | MUST | Implemented |
| ABIL-007 | Base value MUST default to 0 when not specified | MUST | Implemented |
| ABIL-008 | `allowed_negative` boolean field (from migration) MUST control whether value can go below zero | MUST | Implemented |
| ABIL-009 | `CharacterService.initializeAbilityScores()` MUST use all abilities to create the starting stat map with base values | MUST | Implemented |
| ABIL-010 | Deleting an ability MUST NOT crash the stat engine -- orphaned references are skipped | MUST | Implemented |
| ABIL-011 | The `allowed_negative` field exists in the database but is NOT used by the stat calculation engine | SHOULD | Bug |
| ABIL-012 | `Ability.php` entity class only exposes `name` and `description` via `getJsonFields()` -- `base` and `allowed_negative` are NOT in the internal serialization | MUST | Bug |

#### Scenario: Create an ability and verify stat initialization

- GIVEN no abilities exist
- WHEN a game master creates ability "Strength" with base 10
- AND creates ability "Mana" with base 0
- AND a new character is calculated
- THEN the stats map MUST contain Strength with base=10, value=10 and Mana with base=0, value=0

#### Scenario: Update ability base value propagates to characters

- GIVEN ability "XP" exists with base 0
- AND character "Fighter" has been calculated with XP=0 (no effects)
- WHEN the game master updates XP base to 100 (starting XP)
- AND Fighter's stats are recalculated
- THEN XP MUST equal 100 (new base + any effects)

#### Scenario: Delete an ability referenced by effects

- GIVEN ability "Old Stat" is targeted by 2 effects
- AND those effects are on skills assigned to characters
- WHEN "Old Stat" is deleted
- THEN `initializeAbilityScores()` MUST NOT include "Old Stat" in the starting map
- AND `applyModifierToAbility()` MUST create a new entry with value 0 for the orphaned UUID
- AND no errors MUST be thrown

#### Scenario: Allowed_negative field not enforced

- GIVEN ability "Mana" has base=3 and allowed_negative=false
- AND a negative effect with modifier 5 targets Mana
- WHEN the effect is applied
- THEN Mana MUST become -2 (the allowed_negative flag is not checked by the engine)
- AND this is a known bug -- the flag should prevent going below zero

---

### Requirement: Effect System Core

Effects are the atomic unit of the RPG system. They define numeric modifiers applied to one or more abilities with a positive or negative direction and cumulative/non-cumulative stacking.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EFF-001 | Create effects with name, description, modifier, modification type, and cumulative flag | MUST | Implemented |
| EFF-002 | Update existing effects including modifier value changes | MUST | Implemented |
| EFF-003 | Delete effects with confirmation dialog | MUST | Implemented |
| EFF-004 | List effects with search and pagination | MUST | Implemented |
| EFF-005 | View effect details with "Used by" relations tab and audit trail | MUST | Implemented |
| EFF-006 | Effects MUST target one or more abilities via `abilities[]` UUID array | MUST | Implemented |
| EFF-007 | Effects MUST have `modification` type: `positive` (adds) or `negative` (subtracts) | MUST | Implemented |
| EFF-008 | Effects MUST have `cumulative` flag: `cumulative` or `non-cumulative` | MUST | Implemented |
| EFF-009 | The modifier MUST be an integer value representing the magnitude | MUST | Implemented |
| EFF-010 | Effects MUST support legacy `stat_id` field for backward compatibility | MUST | Implemented |
| EFF-011 | `collectEffectAbilities()` MUST merge both `abilities[]` and `stat_id` into a single list | MUST | Implemented |
| EFF-012 | `applyModifierToAbility()` MUST add modifier for "positive" and subtract for "negative" | MUST | Implemented |
| EFF-013 | Each application of `applyModifierToAbility()` MUST append an audit entry `{type: "effect", effect, old, new}` | MUST | Implemented |

#### Scenario: Positive effect application

- GIVEN ability "HP" with base 20
- AND effect "Vitality Boost" with modifier 5, modification "positive", abilities ["hp-uuid"]
- WHEN the effect is applied to a character's stats
- THEN HP value MUST change from 20 to 25
- AND audit MUST record `{old: 20, new: 25, effect: {name: "Vitality Boost", ...}}`

#### Scenario: Negative effect application

- GIVEN ability "HP" with base 20
- AND effect "Poison" with modifier 3, modification "negative", abilities ["hp-uuid"]
- WHEN the effect is applied
- THEN HP MUST change from 20 to 17
- AND audit MUST record `{old: 20, new: 17}`

#### Scenario: Effect targeting multiple abilities

- GIVEN abilities "Strength" (base 10) and "Constitution" (base 10)
- AND effect "Hardy" with modifier 2, targeting both
- WHEN applied
- THEN Strength MUST be 12 AND Constitution MUST be 12
- AND both abilities MUST have audit entries

#### Scenario: Effect with both abilities[] and stat_id

- GIVEN effect "Legacy Boost" with abilities=["str-uuid"] and stat_id="dex-uuid"
- WHEN `collectEffectAbilities()` processes it
- THEN the result MUST include both "str-uuid" and "dex-uuid"

#### Scenario: Null effect in entity's effects array

- GIVEN a skill has effects=["valid-uuid", null, "another-uuid"]
- WHEN `applyEffects()` processes the array
- THEN null MUST be skipped
- AND both valid effects MUST be applied

---

### Requirement: Skill Management and Prerequisites

Skills represent learnable abilities that characters acquire. They carry effects and can require prerequisites before a character can take them.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SKILL-001 | Create skills with name, description, effect text, and effects array | MUST | Implemented |
| SKILL-002 | Update existing skills including effect assignments | MUST | Implemented |
| SKILL-003 | Delete skills with confirmation dialog | MUST | Implemented |
| SKILL-004 | List skills with search and pagination | MUST | Implemented |
| SKILL-005 | View skill details with Effects, Characters (relations), and Logging tabs | MUST | Implemented |
| SKILL-006 | Assign one or more Effects to a skill via `effects[]` UUID array | MUST | Implemented |
| SKILL-007 | Skill detail MUST show associated effects with name, modification type, and modifier value | MUST | Implemented |
| SKILL-008 | View which characters use this skill via relations tab | MUST | Implemented |
| SKILL-009 | Skills can require other skills as prerequisites (`requiredSkills[]`) | MUST | Implemented |
| SKILL-010 | Skills can require minimum stat values (`requiredStats[]`) | MUST | Implemented |
| SKILL-011 | Skills can require specific conditions (`requiredConditions[]`) | MUST | Implemented |
| SKILL-012 | Skills can require specific effects (`requiredEffects[]`) | MUST | Implemented |
| SKILL-013 | Skills can require a minimum score threshold (`requiredScore`) | MUST | Implemented |
| SKILL-014 | Prerequisite validation is data-only -- the system stores prerequisites but does NOT enforce them during character assignment | SHOULD | Implemented |

#### Scenario: Create a skill with effects

- GIVEN effects "Mana +5" and "XP Cost -10" exist
- WHEN a game master creates skill "Basic Healing" with effects=["mana-5", "xp-cost-10"]
- THEN the skill MUST store both effect references
- AND when assigned to a character, both effects MUST apply during stat calculation

#### Scenario: Skill prerequisite chain

- GIVEN skill "Basic Swordplay" exists
- AND skill "Advanced Swordplay" is created with requiredSkills=["basic-swordplay"], requiredScore=5
- WHEN viewing the skill details
- THEN requiredSkills MUST list "Basic Swordplay"
- AND requiredScore MUST show 5

#### Scenario: Skill effects visible in detail

- GIVEN skill "Fireball" has effects "Arcane Mana +5" (positive, modifier 5) and "HP -1" (negative, modifier 1)
- WHEN the user views the skill detail Effects tab
- THEN both effects MUST be listed with their names, modification types, and modifier values

#### Scenario: Characters using a skill

- GIVEN skill "Healing" is assigned to characters "Cleric" and "Paladin"
- WHEN viewing the skill's Characters tab
- THEN both characters MUST be listed

---

### Requirement: Item Management

Items represent magical or special objects that characters hold. They carry effects and have a uniqueness flag.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| ITEM-001 | Create items with name, description, effect text, uniqueness flag, and effects array | MUST | Implemented |
| ITEM-002 | Update existing items | MUST | Implemented |
| ITEM-003 | Delete items with confirmation dialog | MUST | Implemented |
| ITEM-004 | List items with search and pagination | MUST | Implemented |
| ITEM-005 | View item details with relations and audit trail | MUST | Implemented |
| ITEM-006 | Assign one or more Effects to an item | MUST | Implemented |
| ITEM-007 | Items MUST track which characters hold them via `characters[]` | MUST | Implemented |
| ITEM-008 | Items MUST have a `unique` boolean flag for one-of-a-kind artifacts | MUST | Implemented |
| ITEM-009 | Unique items SHOULD only be held by one character at a time (not enforced by backend) | SHOULD | Implemented |

#### Scenario: Create a unique magical item

- GIVEN effect "Arcane Power +10" exists
- WHEN creating item "Hand of Vecna" with unique=true, effects=["arcane-power"]
- THEN the item MUST be flagged as unique
- AND when assigned to a character, Arcane Power MUST increase by 10

#### Scenario: Non-unique item held by multiple characters

- GIVEN item "Generic Magic Sword" with unique=false and effect "Attack +1"
- AND characters "Fighter" and "Ranger" both have this item
- WHEN stats are calculated for each
- THEN both characters MUST receive +1 Attack from the item

#### Scenario: Item effects stacking with skill effects

- GIVEN ability "Strength" (base 10)
- AND skill "Warrior" with effect "+3 Strength"
- AND item "Gauntlets of Power" with effect "+5 Strength"
- AND character "Fighter" has both skill and item
- WHEN stats are calculated
- THEN Strength MUST be 18 (base 10 + 3 from skill + 5 from item)
- AND the audit trail MUST show skill effect applied first, then item effect

---

### Requirement: Condition Management

Conditions represent positive or negative states applied to characters during gameplay (curses, blessings, diseases, etc.).

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| COND-001 | Create conditions with name, description, effect text, uniqueness flag, and effects array | MUST | Implemented |
| COND-002 | Update existing conditions | MUST | Implemented |
| COND-003 | Delete conditions with confirmation dialog | MUST | Implemented |
| COND-004 | List conditions with search and pagination | MUST | Implemented |
| COND-005 | View condition details with relations and audit trail | MUST | Implemented |
| COND-006 | Assign one or more Effects to a condition | MUST | Implemented |
| COND-007 | Conditions MUST track which characters are affected (`characters[]`, readOnly) | MUST | Implemented |
| COND-008 | Conditions MUST have a `unique` boolean flag for single-character applicability | MUST | Implemented |
| COND-009 | Removing a condition from a character MUST remove its effects on next stat recalculation | MUST | Implemented |

#### Scenario: Condition with negative effects

- GIVEN effect "-2 HP (Curse)" exists
- WHEN creating condition "Vampiric Demeanor" with effects=["curse-effect"]
- THEN when assigned to a character, HP MUST decrease by 2

#### Scenario: Condition removal restores stats

- GIVEN character "Warrior" has condition "Poisoned" (-5 HP) and HP is currently 15 (base 20 - 5)
- WHEN "Poisoned" is removed from the character
- AND stats are recalculated
- THEN HP MUST return to 20 (or base + other modifiers)

#### Scenario: Multiple conditions stacking

- GIVEN character has conditions "Blessed" (+3 HP) and "Cursed" (-2 HP)
- WHEN stats are calculated
- THEN HP MUST include both: base + 3 - 2

#### Scenario: Unique condition enforcement

- GIVEN condition "The Chosen One" with unique=true
- AND it is assigned to character "Hero"
- THEN the characters[] array MUST contain only Hero's UUID
- AND the unique flag SHOULD prevent assigning it to another character (not enforced by backend)

---

### Requirement: Stat Calculation Order and Audit

The stat calculation engine MUST apply effects in a deterministic order and produce a complete audit trail.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CALC-001 | `calculateCharacter()` MUST initialize ability scores from base values via `initializeAbilityScores()` | MUST | Implemented |
| CALC-002 | Effects MUST be applied in order: skills first, then items, then conditions, then events | MUST | Implemented |
| CALC-003 | Within each entity type, effects MUST be applied in the order the entities appear in the character's association array | MUST | Implemented |
| CALC-004 | Each effect application MUST produce an audit entry with `{type: "effect", effect: {...}, old: number, new: number}` | MUST | Implemented |
| CALC-005 | The final stats object MUST contain per-ability entries with `{name, base, value, audit[]}` | MUST | Implemented |
| CALC-006 | Missing entities (referenced by UUID but not found in preloaded maps) MUST be silently skipped | MUST | Implemented |
| CALC-007 | Empty or null entity association arrays MUST be skipped without error by `applyEntityEffects()` | MUST | Implemented |

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

---

### Requirement: Shared OpenRegister Features

All RPG entities MUST support OpenRegister-specific features when backed by OpenRegister storage.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| RPG-001 | All RPG entities MUST support audit trail viewing | MUST | Implemented |
| RPG-002 | All RPG entities MUST support relation discovery | MUST | Implemented |
| RPG-003 | All RPG entities MUST support lock/unlock | MUST | Implemented |
| RPG-004 | All RPG entities MUST support revert to previous state | MUST | Implemented |

#### Scenario: View effect "Used by" relations

- GIVEN effect "Strength +5" is referenced by skills "Warrior" and "Barbarian"
- WHEN viewing the effect detail "Used by" tab
- THEN both skills MUST be listed as relations

#### Scenario: Revert a skill to previous version

- GIVEN skill "Healing" had its effects changed incorrectly
- WHEN the game master reverts to the previous version
- THEN the effects array MUST be restored
- AND characters with this skill MUST use the reverted effects on next stat calculation

#### Scenario: Lock an item during editing

- GIVEN game master A is editing item "Excalibur"
- WHEN A locks the item
- THEN game master B MUST see the item as locked
- AND B MUST be prevented from editing until A unlocks it

---

### Requirement: Internal vs OpenRegister Storage

All RPG entities have a dual data model. Internal storage is skeletal; full functionality requires OpenRegister.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| RPG-010 | In **internal mode**, all entities have only `id`, `name`, and `description` PHP entity properties | MUST | Implemented |
| RPG-011 | The full data model (effects, prerequisites, unique flags, base values, modifier, modification, cumulative) MUST only exist in **OpenRegister mode** | MUST | Implemented |
| RPG-012 | Internal storage mode is functionally incomplete for the RPG system | MUST | Implemented |
| RPG-013 | Ability entity has `base` and `allowed_negative` DB columns but NOT as PHP properties | MUST | Bug |
| RPG-014 | Internal mapper `findAll()` signatures are inconsistent and incompatible with generic calling patterns | MUST | Bug |

#### Scenario: RPG system in OpenRegister mode

- GIVEN all entity types are configured for OpenRegister
- WHEN a game master creates skills with effects, items with unique flags, conditions with effects
- AND assigns them to characters
- THEN the full stat calculation engine MUST work correctly
- AND all effect chains MUST function as designed

#### Scenario: RPG system in internal mode (broken)

- GIVEN all entity types are configured for internal storage
- WHEN a game master creates an effect with modifier=5, modification="positive"
- THEN only name and description MUST be stored
- AND the modifier and modification MUST be lost
- AND stat calculation MUST NOT produce meaningful results

---

## Data Model

### Effect Chain Diagram

```
Character
  +-- skills[] (UUID refs)
  |     +-- Skill -> effects[] -> Effect -> abilities[] -> Ability
  +-- items[] (UUID refs)
  |     +-- Item -> effects[] -> Effect -> abilities[] -> Ability
  +-- conditions[] (UUID refs)
  |     +-- Condition -> effects[] -> Effect -> abilities[] -> Ability
  +-- events[] (UUID refs)
        +-- Event -> effects[] -> Effect -> abilities[] -> Ability

Calculation order: skills -> items -> conditions -> events
Each effect: modifier (int) + modification (positive/negative) = delta on ability value
Result: character.stats = { ability_uuid: { name, base, value, audit[] } }
```

### Entity Summary

| Entity | Key Fields (OpenRegister) | Internal Fields |
|--------|---------------------------|-----------------|
| Ability | name, description, base, allowed_negative | name, description |
| Effect | name, description, modifier, modification, cumulative, abilities[], stat_id | name, description |
| Skill | name, description, effect, effects[], requiredSkills/Stats/Conditions/Effects[], requiredScore | name, description |
| Item | name, description, effect, effects[], unique, characters[] | name, description |
| Condition | name, description, effect, effects[], unique, characters[] | name, description |

## User Interface

### Navigation Placement

- **Abilities**: Settings area (ShieldSwordOutline icon)
- **Skills**: Settings area (SwordCross icon)
- **Effects**: Settings area (MagicStaff icon)
- **Items**: Main navigation (Sword icon)
- **Conditions**: Main navigation (EmoticonSickOutline icon)

### Per-Entity Views

Each entity type has: List view with search, Detail view with entity-specific tabs, Edit/Delete modals.

## API Endpoints

All RPG entity types use the generic `/api/objects/{objectType}` pattern. Each type supports CRUD plus OpenRegister extensions (`/audit`, `/relations`, `/uses`, `/lock`, `/unlock`, `/revert`, `/files`).

## Dependencies

- **RegisterObjectFetcher**: Data retrieval for all entity types via OpenRegister
- **CharacterService**: Core stat calculation engine consuming effects from all entity types
- **Pinia object store**: Frontend state management
- **OpenRegister** (required for full functionality): Schema validation, audit trails, relations, locking
