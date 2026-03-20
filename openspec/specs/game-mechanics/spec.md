---
status: enriched
---

# Game Mechanics Specification

## Purpose

Game mechanics covers the interconnected system of Skills, Items, Conditions, Effects, and Abilities (stats) that form the LARP rule engine. Effects are the fundamental building blocks -- they modify ability scores via positive or negative modifiers. Skills, items, conditions, and events each contain arrays of effect references that are applied to characters during stat calculation by `CharacterService.calculateCharacter()`. Abilities define the numeric stats that effects target. Skills additionally support a prerequisite system. This specification documents the CRUD operations, data models, effect chain integrity, and interactions between these entity types.

**Key source files:**
- `lib/Db/Skill.php`, `lib/Db/Item.php`, `lib/Db/Condition.php`, `lib/Db/Effect.php`, `lib/Db/Ability.php` -- Entity classes
- `lib/Service/CharacterService.php` -- Effect application logic (`applyEntityEffects()`, `applyEffects()`, `calculateEffect()`, `applyModifierToAbility()`)
- `src/store/modules/object.js` -- Generic object store
- `docs/Schema/Skill.json`, `docs/Schema/Item.json`, etc. -- JSON schemas

## Requirements

---

### Requirement: Ability (Stat) CRUD

The system MUST support creating, reading, updating, and deleting abilities. Abilities represent the numeric stats that characters are scored on and that effects target.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-001 | System MUST support CRUD for abilities via `/api/objects/ability` | MUST | Implemented |
| MECH-002 | Ability MUST require a `name` field | MUST | Implemented |
| MECH-003 | Ability `base` MUST default to 0 if not specified | MUST | Implemented |
| MECH-004 | Abilities MUST be listable with search, pagination, and facets | MUST | Implemented |
| MECH-005 | Ability `allowed_negative` MUST default to false if not specified | MUST | Implemented |
| MECH-006 | Abilities MUST be accessible from the Settings navigation area with ShieldSwordOutline icon | MUST | Implemented |
| MECH-007 | Ability base value MUST be used by `CharacterService.initializeAbilityScores()` to set starting values for all characters | MUST | Implemented |
| MECH-008 | Deleting an ability MUST NOT crash the stat engine (orphaned ability references are skipped) | MUST | Implemented |
| MECH-009 | The `allowed_negative` field exists in the database (migration `Version0Date20241015141612`) but is NOT used by the stat calculation engine | SHOULD | Bug |
| MECH-010 | The `Ability.php` entity class only has `name` and `description` properties -- `base` and `allowed_negative` are NOT exposed via `getJsonFields()` in internal mode | MUST | Bug |

#### Scenario: Create an ability

- GIVEN a game master is configuring the LARP setting
- WHEN they POST to `/api/objects/ability` with name "Healing Mana" and base 5
- THEN the system MUST create the ability object
- AND all new characters MUST use base value 5 for "Healing Mana" in stat calculations

#### Scenario: Update an ability base value

- GIVEN an ability "XP" with base 0 exists
- WHEN the game master updates base to 15 (all characters start with 15 XP)
- THEN future stat calculations for all characters MUST use the new base value 15
- AND existing character stats MUST reflect the new base on recalculation

#### Scenario: Delete an ability referenced by effects

- GIVEN ability "Mana" is targeted by 3 effects
- AND those effects are assigned to skills on characters
- WHEN "Mana" is deleted
- THEN the stat engine MUST skip the missing ability during calculation
- AND no errors MUST be thrown
- AND the effects MUST still exist but target a non-existent ability

#### Scenario: List abilities with search

- GIVEN abilities "Strength", "Dexterity", "Mana", and "HP" exist
- WHEN the user searches for "mana"
- THEN only "Mana" MUST appear in the results

#### Scenario: Ability initialization in stat engine

- GIVEN abilities "Strength" (base 10), "Dexterity" (base 8), "Mana" (base 0) exist
- WHEN `CharacterService.initializeAbilityScores()` is called
- THEN the result MUST contain entries for all 3 abilities
- AND each MUST have `value` equal to its `base`
- AND each MUST have an empty `audit` array

---

### Requirement: Effect CRUD and Mechanics

The system MUST support creating, reading, updating, and deleting effects. Effects are the atomic modifiers that change ability scores.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-020 | System MUST support CRUD for effects via `/api/objects/effect` | MUST | Implemented |
| MECH-021 | Effect MUST require a `name` field | MUST | Implemented |
| MECH-022 | Effect `modification` MUST default to `positive` if not specified | MUST | Implemented |
| MECH-023 | Effect `cumulative` MUST default to `non-cumulative` if not specified | MUST | Implemented |
| MECH-024 | Effect MUST support targeting multiple abilities via the `abilities[]` array | MUST | Implemented |
| MECH-025 | Effect MUST support legacy `stat_id` field for single-ability targeting | MUST | Implemented |
| MECH-026 | `calculateEffect()` MUST call `collectEffectAbilities()` to gather all targeted abilities from both `abilities[]` and `stat_id` | MUST | Implemented |
| MECH-027 | `applyModifierToAbility()` MUST add modifier for "positive" and subtract for "negative" modification | MUST | Implemented |
| MECH-028 | `applyModifierToAbility()` MUST append an audit entry with `{type, effect, old, new}` for each modification | MUST | Implemented |
| MECH-029 | Effects MUST be accessible from the Settings navigation area with MagicStaff icon | MUST | Implemented |

#### Scenario: Create a positive effect targeting one ability

- GIVEN ability "Healing Mana" exists
- WHEN a game master creates effect "+5 Healing Mana" with modifier 5, modification "positive", abilities ["healing-mana-uuid"]
- THEN the effect MUST be created
- AND when assigned to a skill/item/condition on a character, it MUST add 5 to Healing Mana

#### Scenario: Create a negative effect

- GIVEN ability "HP" exists
- WHEN a game master creates effect "-2 HP (Curse)" with modifier 2, modification "negative", abilities ["hp-uuid"]
- THEN the effect MUST be created
- AND when applied via a condition, it MUST subtract 2 from HP

#### Scenario: Create an effect targeting multiple abilities

- GIVEN abilities "Arcane Mana" and "Spiritual Mana" exist
- WHEN a game master creates effect "Universal Mana +3" with modifier 3, abilities ["arcane-uuid", "spiritual-uuid"]
- THEN the effect MUST be created
- AND when applied, it MUST add 3 to BOTH abilities
- AND both abilities MUST have audit entries

#### Scenario: Effect with legacy stat_id

- GIVEN ability "Dexterity" exists
- AND an effect has stat_id pointing to Dexterity but no abilities[] array
- WHEN `collectEffectAbilities()` processes this effect
- THEN Dexterity MUST be included in the affected abilities list

#### Scenario: Effect with both abilities[] and stat_id

- GIVEN an effect has abilities=["strength-uuid"] and stat_id="dexterity-uuid"
- WHEN `collectEffectAbilities()` processes this effect
- THEN both Strength and Dexterity MUST be in the affected list

---

### Requirement: Skill CRUD and Prerequisites

The system MUST support creating, reading, updating, and deleting skills. Skills represent learnable abilities and can have prerequisite requirements.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-030 | System MUST support CRUD for skills via `/api/objects/skill` | MUST | Implemented |
| MECH-031 | Skill MUST require a `name` field | MUST | Implemented |
| MECH-032 | Skill MUST support assigning multiple effects via `effects[]` UUID array | MUST | Implemented |
| MECH-033 | Skill MUST support prerequisite skills via `requiredSkills[]` UUID array | MUST | Implemented |
| MECH-034 | Skill MUST support prerequisite stat levels via `requiredStats[]` | MUST | Implemented |
| MECH-035 | Skill MUST support prerequisite conditions via `requiredConditions[]` | MUST | Implemented |
| MECH-036 | Skill MUST support prerequisite effects via `requiredEffects[]` | MUST | Implemented |
| MECH-037 | Skill MUST support minimum score threshold via `requiredScore` | MUST | Implemented |
| MECH-038 | Skills MUST be accessible from the Settings navigation area with SwordCross icon | MUST | Implemented |
| MECH-039 | Skill detail view MUST show Effects, Characters (relations), and Logging tabs | MUST | Implemented |

#### Scenario: Create a skill with effects and prerequisites

- GIVEN effects "Healing LvL 1 Mana +5" and "XP Cost -10" exist
- AND skill "Basic Healing" exists
- WHEN a game master creates skill "Advanced Healing" with effects ["mana-effect", "xp-cost"], requiredSkills ["basic-healing"], requiredScore 15
- THEN the skill MUST be created with all relationships
- AND when assigned to a character, both effects MUST be applied during stat calculation

#### Scenario: View skill effects in detail

- GIVEN skill "Sword Mastery" has effects "Attack +3" and "Defense +1"
- WHEN the user views the skill detail and opens the Effects tab
- THEN both effects MUST be listed with name, modification type, and modifier value

#### Scenario: View characters using a skill

- GIVEN skill "Healing" is assigned to characters "Merlin" and "Gandalf"
- WHEN the user views the skill detail and opens the Characters tab
- THEN both characters MUST be listed via the relations endpoint

#### Scenario: List skills with search

- GIVEN 50 skills exist in the system
- WHEN the user navigates to the Skills view and searches "heal"
- THEN only skills containing "heal" in their name or description MUST appear

#### Scenario: Prerequisite chain display

- GIVEN skill "Advanced Swordplay" requires "Basic Swordplay" and minimum score 5 on "Combat"
- WHEN viewing the skill details
- THEN requiredSkills MUST contain "Basic Swordplay" UUID
- AND requiredScore MUST be 5

---

### Requirement: Item CRUD

The system MUST support creating, reading, updating, and deleting items. Items represent magical or special objects that characters can hold.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-040 | System MUST support CRUD for items via `/api/objects/item` | MUST | Implemented |
| MECH-041 | Item MUST require a `name` field | MUST | Implemented |
| MECH-042 | Item MUST support assigning multiple effects via `effects[]` UUID array | MUST | Implemented |
| MECH-043 | Item MUST support `unique` flag to indicate one-of-a-kind artifacts | MUST | Implemented |
| MECH-044 | Item MUST track which characters hold it via the `characters[]` array | MUST | Implemented |
| MECH-045 | Items MUST be accessible from the main navigation sidebar with Sword icon | MUST | Implemented |
| MECH-046 | Item detail view MUST show relations and audit trail tabs | MUST | Implemented |

#### Scenario: Create a unique item

- GIVEN an effect "Arcane Power +10" exists
- WHEN a game master creates item "Hand of Vecna" with unique=true, effects=["arcane-effect"]
- THEN the item MUST be created as a unique artifact
- AND only one character SHOULD hold it at a time

#### Scenario: Create a non-unique item

- GIVEN an effect "Attack +1" exists
- WHEN a game master creates item "Generic Magic Sword" with unique=false, effects=["attack-effect"]
- THEN the item MUST be created as non-unique
- AND multiple characters MAY hold instances of it

#### Scenario: Item effects applied to character

- GIVEN item "Ring of Protection" has effect "+3 Defense"
- AND character "Frodo" has this item assigned
- WHEN stats are calculated
- THEN Defense MUST increase by 3
- AND the audit trail MUST show the item's effect

#### Scenario: Track item holders

- GIVEN item "Excalibur" is assigned to character "Arthur"
- WHEN viewing the item details
- THEN the characters[] array MUST include Arthur's UUID

---

### Requirement: Condition CRUD

The system MUST support creating, reading, updating, and deleting conditions. Conditions represent positive or negative states applied to characters during gameplay.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-050 | System MUST support CRUD for conditions via `/api/objects/condition` | MUST | Implemented |
| MECH-051 | Condition MUST require a `name` field | MUST | Implemented |
| MECH-052 | Condition MUST support assigning multiple effects via `effects[]` UUID array | MUST | Implemented |
| MECH-053 | Condition MUST support `unique` flag to indicate single-character applicability | MUST | Implemented |
| MECH-054 | Condition MUST track affected characters via `characters[]` (readOnly) | MUST | Implemented |
| MECH-055 | Conditions MUST be accessible from the main navigation sidebar with EmoticonSickOutline icon | MUST | Implemented |
| MECH-056 | Condition detail view MUST show relations and audit trail tabs | MUST | Implemented |

#### Scenario: Create a condition with negative effects

- GIVEN an effect "-2 HP per day (Curse)" exists
- WHEN a game master creates condition "Vampiric Demeanor" with effects=["curse-effect"], unique=false
- THEN the condition MUST be created
- AND when assigned to a character, HP MUST decrease by 2 during stat calculation

#### Scenario: Create a unique condition

- GIVEN an effect "Chosen One +100 XP" exists
- WHEN a game master creates condition "The Chosen" with unique=true
- THEN the condition MUST be flagged as unique
- AND only one character SHOULD be affected by it

#### Scenario: Condition removal restores stats

- GIVEN character "Warrior" has condition "Poisoned" with effect "-5 HP"
- AND HP is currently base(20) - 5 = 15
- WHEN "Poisoned" is removed from the character
- AND stats are recalculated
- THEN HP MUST return to base(20) (or base + other modifiers)

#### Scenario: Multiple conditions stacking

- GIVEN character "Paladin" has conditions "Blessed" (+3 HP) and "Cursed" (-2 HP)
- WHEN stats are calculated
- THEN HP MUST be base + 3 - 2 = base + 1

---

### Requirement: Effect Chain Integrity

The effect system MUST maintain integrity across the chain: Ability <- Effect <- Skill/Item/Condition/Event <- Character.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-060 | Modifying an effect's modifier MUST change the calculation result for all characters that have skills/items/conditions containing that effect | MUST | Implemented |
| MECH-061 | Removing an effect from a skill MUST remove that effect's contribution from character stat calculations | MUST | Implemented |
| MECH-062 | Deleting an ability MUST NOT crash the stat engine -- null ability IDs are gracefully skipped by `applyModifierToAbility()` | MUST | Implemented |
| MECH-063 | The stat engine MUST handle null or missing effect IDs without throwing errors -- `applyEffects()` skips null effectIds | MUST | Implemented |
| MECH-064 | The effect application order MUST be: skills effects, then items effects, then conditions effects, then events effects | MUST | Implemented |
| MECH-065 | Each effect application MUST record an audit entry with old value, new value, and effect data | MUST | Implemented |

#### Scenario: Effect chain recalculation after modifier change

- GIVEN ability "Mana" (base 0), effect "Mana +5" (modifier 5, positive, targets Mana)
- AND skill "Basic Magic" contains effect "Mana +5"
- AND character "Merlin" has skill "Basic Magic"
- WHEN the stat engine calculates Merlin's stats
- THEN Mana MUST equal 5 (base 0 + 5)
- WHEN a game master changes the effect modifier from 5 to 10
- AND the character is recalculated
- THEN Mana MUST equal 10 (base 0 + 10)

#### Scenario: Removing effect from skill

- GIVEN skill "Healing" has effects ["heal-effect-1", "heal-effect-2"]
- AND character "Cleric" has skill "Healing"
- WHEN "heal-effect-2" is removed from the skill
- AND Cleric's stats are recalculated
- THEN only "heal-effect-1" MUST apply
- AND the ability modified by "heal-effect-2" MUST revert to its value without that effect

#### Scenario: Multiple effect sources targeting same ability

- GIVEN ability "HP" (base 20)
- AND skill effect "+5 HP", item effect "+3 HP", condition effect "-2 HP", event effect "+1 HP"
- AND character "Tank" has all four entity types
- WHEN stats are calculated
- THEN HP MUST equal 27 (20 + 5 + 3 - 2 + 1)
- AND the HP audit trail MUST contain 4 entries in order: skill, item, condition, event

#### Scenario: Null effect reference handling

- GIVEN a skill has effects=["valid-uuid", null, "another-valid-uuid"]
- WHEN `applyEffects()` processes this array
- THEN the null entry MUST be skipped
- AND the two valid effects MUST be applied normally

#### Scenario: Missing ability reference handling

- GIVEN an effect targets ability "deleted-ability-uuid" which no longer exists
- WHEN `applyModifierToAbility()` is called
- THEN the ability entry MUST be created with value 0 if not present
- AND the modifier MUST be applied (creating a new entry in the stats)

---

### Requirement: Audit Trail for All Mechanic Entities

All game mechanic entities MUST support audit trail viewing when backed by OpenRegister.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-070 | Each entity type (skill, item, condition, effect, ability) MUST support audit trail retrieval | MUST | Implemented |
| MECH-071 | Each entity type MUST support relations retrieval | MUST | Implemented |
| MECH-072 | Each entity type MUST support uses retrieval | MUST | Implemented |
| MECH-073 | Each entity type MUST support lock/unlock for concurrent editing protection | MUST | Implemented |
| MECH-074 | Each entity type MUST support revert to previous state | MUST | Implemented |

#### Scenario: View skill audit trail

- GIVEN skill "Healing LvL 1" has been edited 3 times
- WHEN the user views the audit trail for this skill
- THEN 3 change records MUST be displayed with timestamps and changed values

#### Scenario: View effect relations (used by)

- GIVEN effect "Strength +5" is used by skills "Warrior Training" and "Barbarian Rage"
- WHEN the user views the effect detail and opens "Used by" tab
- THEN both skills MUST be listed as relations

#### Scenario: Lock an item for editing

- GIVEN item "Excalibur" is being edited
- WHEN the game master locks it
- THEN other users MUST be prevented from editing simultaneously

---

### Requirement: Internal vs OpenRegister Storage

All game mechanics entities have a dual data model with significant differences between internal and OpenRegister storage.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-080 | In **internal storage mode**, all entity types have skeletal PHP entities with only `id`, `name`, and `description` fields | MUST | Implemented |
| MECH-081 | The full data model (effects arrays, prerequisites, unique flags, base values, etc.) MUST only exist in **OpenRegister storage mode** | MUST | Implemented |
| MECH-082 | Internal entity PHP classes do NOT define fields like `effects`, `requiredSkills`, `modifier`, `modification`, `cumulative`, `abilities`, `unique`, `characters` | MUST | Implemented |
| MECH-083 | The Ability entity has `base` and `allowed_negative` database columns but NOT as PHP entity properties | MUST | Bug |
| MECH-084 | Internal storage mode is functionally incomplete -- entities cannot participate in the full game mechanics system | MUST | Implemented |

#### Scenario: Skill in internal mode lacks effects

- GIVEN skill type is configured for internal storage
- WHEN a skill is created with name "Healing" and effects ["effect-uuid"]
- THEN only name and description MUST be stored (internal entity has no `effects` property)
- AND the effects association MUST be lost

#### Scenario: Effect in internal mode lacks modifier

- GIVEN effect type is configured for internal storage
- WHEN an effect is created with name "+5 Mana", modifier 5, modification "positive"
- THEN only name and description MUST be stored
- AND modifier and modification MUST be lost
- AND the effect MUST NOT function during stat calculation

#### Scenario: OpenRegister mode provides full functionality

- GIVEN all entity types are configured for OpenRegister storage
- WHEN skills, items, conditions, effects, and abilities are created with full field data
- THEN all fields MUST be stored as JSON documents
- AND the stat calculation engine MUST have access to all association and modifier data

---

## Data Model

### Ability Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Ability name (e.g., "Strength", "Dexterity", "Mana") |
| description | string | No | "" | Description of the ability |
| base | number | No | 0 | Base starting value for all characters |
| allowed_negative | boolean | No | false | Whether value can go below zero (DB column exists, not used by stat engine) |

### Effect Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Effect name |
| description | string | No | "" | Effect description |
| modifier | number | No | 0 | Integer modifier value |
| modification | enum | Yes | "positive" | `positive` or `negative` |
| cumulative | enum | No | "non-cumulative" | `cumulative` or `non-cumulative` |
| abilities | string[] (UUIDs) | Yes | [] | Target ability IDs |
| stat_id | string (UUID) | No | null | Legacy single-ability target |

### Skill Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Skill name |
| description | string | No | "" | Skill description |
| effect | string | No | "" | Free-text effect description |
| effects | string[] (UUIDs) | No | [] | Effect IDs that this skill grants |
| requiredSkills | string[] (UUIDs) | No | [] | Prerequisite Skill IDs |
| requiredStats | string[] (UUIDs) | No | [] | Prerequisite Ability IDs |
| requiredConditions | string[] (UUIDs) | No | [] | Prerequisite Condition IDs |
| requiredEffects | string[] (UUIDs) | No | [] | Prerequisite Effect IDs |
| requiredScore | number | No | null | Minimum score threshold |

### Item Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Item name |
| description | string | No | "" | Item description |
| effect | string | No | "" | Free-text effect description |
| effects | string[] (UUIDs) | No | [] | Effect IDs |
| unique | boolean | No | false | One-of-a-kind flag |
| characters | string[] (UUIDs) | No | [] | Holding characters |

### Condition Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Condition name |
| description | string | No | "" | Condition description |
| effect | string | No | "" | Free-text effect description |
| effects | string[] (UUIDs) | No | [] | Effect IDs |
| unique | boolean | No | false | Single-character flag |
| characters | string[] (UUIDs) | No | [] | Affected characters |

### Effect Chain Diagram

```
Skill/Item/Condition/Event
    +-- effects[] (UUID references)
            +-- Effect
                |-- modifier: 5
                |-- modification: "positive"
                +-- abilities[] (UUID references)
                        +-- Ability
                            |-- base: 10
                            +-- computed value: 15 (base + modifier)
```

## User Interface

### Navigation Placement

- **Abilities**: Settings area (ShieldSwordOutline icon)
- **Skills**: Settings area (SwordCross icon)
- **Effects**: Settings area (MagicStaff icon)
- **Items**: Main navigation (Sword icon)
- **Conditions**: Main navigation (EmoticonSickOutline icon)

### Per-Entity Views

Each entity type has: List view with search, Detail view with tabs (effects/relations/logging), Edit/Delete modals.

### Stores

Each entity has a dedicated section in the Pinia object store following the pattern: item selection, list management, audit trails, relations, uses, CRUD operations, and debounced search.

## API Endpoints

All entity types use the generic `/api/objects/{objectType}` pattern with CRUD + OpenRegister extensions:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/objects/ability` | List/Create abilities |
| GET/PUT/DELETE | `/api/objects/ability/{id}` | Get/Update/Delete ability |
| GET/POST | `/api/objects/skill` | List/Create skills |
| GET/PUT/DELETE | `/api/objects/skill/{id}` | Get/Update/Delete skill |
| GET/POST | `/api/objects/item` | List/Create items |
| GET/PUT/DELETE | `/api/objects/item/{id}` | Get/Update/Delete item |
| GET/POST | `/api/objects/condition` | List/Create conditions |
| GET/PUT/DELETE | `/api/objects/condition/{id}` | Get/Update/Delete condition |
| GET/POST | `/api/objects/effect` | List/Create effects |
| GET/PUT/DELETE | `/api/objects/effect/{id}` | Get/Update/Delete effect |

All types additionally support: `{id}/lock`, `{id}/unlock`, `{id}/revert`, `{id}/audit`, `{id}/relations`, `{id}/uses`, `{id}/files`.

## Dependencies

- **RegisterObjectFetcher**: Data retrieval for all entity types via OpenRegister
- **CharacterService**: Consumes effects from skills/items/conditions/events during stat calculation
- **Pinia object store**: Frontend state management for all entity types
- **OpenRegister** (optional): Schema validation, audit trails, relations, locking
