# Game Mechanics Specification

## Purpose

Game mechanics covers the interconnected system of Skills, Items, Conditions, Effects, and Abilities (stats) that form the LARP rule engine. Effects are the fundamental building blocks -- they modify ability scores. Skills, items, conditions, and events each contain arrays of effects that are applied to characters. Abilities define the numeric stats that effects target. This specification documents the CRUD operations, data models, and interactions between these entity types.

**Key source files:**
- `lib/Db/Skill.php`, `lib/Db/Item.php`, `lib/Db/Condition.php`, `lib/Db/Effect.php`, `lib/Db/Ability.php` -- Entity classes
- `lib/Service/CharacterService.php` -- Effect application logic
- `src/entities/skill/`, `src/entities/item/`, `src/entities/condition/`, `src/entities/effect/`, `src/entities/ability/` -- TypeScript entities
- `docs/Schema/Skill.json`, `docs/Schema/Item.json`, `docs/Schema/Condition.json`, `docs/Schema/Effect.json`, `docs/Schema/Stats.json` -- JSON schemas

## Data Models

### Ability (Stat)

Abilities represent numeric values on which characters are scored. Common examples include XP, mana (healing, spiritual, elemental), HP, DEX, CHA, or any trackable numeric value like money or material components.

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| id | string (UUID) | Auto | Unique identifier |
| name | string | YES | The name of this ability (e.g., "Experience Points", "Healing Mana") |
| description | string | No | Description of what this ability represents in the setting |
| base | integer (default 0) | No | Starting value for all characters |
| allowed_negative | boolean (default false) | No | Whether this ability value can go below zero during stat calculation. Added in migration `Version0Date20241015141612`. **Note:** This field exists in the database but is NOT defined as a property on the PHP entity class (`lib/Db/Ability.php` only has `name` and `description`), and is NOT currently used by the stat calculation engine. |

### Effect

Effects are numeric modifiers to one or more abilities. They are the atomic unit of the game mechanics system -- skills, items, conditions, and events all contain arrays of effect references.

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| id | string (UUID) | Auto | Unique identifier |
| name | string | YES | Descriptive name (e.g., "+ 5 Healing Mana") |
| description | string | No | Rule-technical description of what the effect does |
| modifier | integer | No | The numeric modifier value (e.g., 5) |
| modification | enum: positive, negative (default: positive) | No | Whether the modifier adds or subtracts |
| cumulative | enum: cumulative, non-cumulative (default: non-cumulative) | No | Whether a character can take this effect more than once |
| abilities | array of Ability UUIDs | No | The abilities targeted by this effect |
| stat_id (legacy) | string (UUID) | No | Legacy single-ability targeting field |

### Skill

Skills represent learnable actions characters can perform (e.g., healing, spellcasting). They typically provide positive effects and cost experience points. Skills can have prerequisites.

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| id | string (UUID) | Auto | Unique identifier |
| name | string | YES | Name of the skill (e.g., "Healing LvL 1") |
| description | string (max 2555) | No | Setting-appropriate flavor text (visible to players) |
| effect | string | No | Technical/rule description of what the skill does |
| effects | array of Effect UUIDs | No | Automated effects applied to characters with this skill |
| requiredSkills | array of Skill UUIDs | No | Prerequisite skills needed before taking this skill |
| requiredStats | array of Stat UUIDs | No | Prerequisite stat levels needed |
| requiredConditions | array of Condition UUIDs | No | Prerequisite conditions needed |
| requiredEffects | array of Effect UUIDs | No | Prerequisite effects needed |
| requiredScore | integer | No | Minimum ability score required (when prerequisites are abilities) |

### Item

Items represent objects characters own that can have effects. Items are intended for magic/special items, not mundane tracking. Items can be unique (one-of-a-kind artifacts) or non-unique (generic magic swords).

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| id | string (UUID) | Auto | Unique identifier |
| name | string | YES | Name of the item (e.g., "Hand of Vecna") |
| description | string (max 2555) | No | Flavor text visible to the character holding the item |
| effect | string (max 2555) | No | Technical description for game masters (not visible to characters) |
| effects | array of Effect UUIDs | No | Automated effects applied to the character holding this item |
| unique | boolean (default: true) | No | Whether only one instance of this item can exist |
| characters | array of Character UUIDs | No | Characters currently holding this item |

### Condition

Conditions are positive or negative effects that target characters, typically as consequences of gameplay actions. Unlike skills, conditions are not "bought" with skill points -- they are applied during play.

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| id | string (UUID) | Auto | Unique identifier |
| name | string | YES | Name of the condition (e.g., "Vampiric Demeanor") |
| description | string | No | Setting-appropriate flavor text |
| effect | string | No | Game description of what the condition does |
| effects | array of Effect UUIDs | No | Automated effects caused by this condition |
| unique | boolean (default: false) | No | Whether this condition can apply to only one character |
| characters | array of Character UUIDs (readOnly) | No | Characters affected by this condition |

### Internal vs OpenRegister Storage

All game mechanics entities have a **dual data model**:

1. **Internal storage (PHP entities)**: Each entity class in `lib/Db/` is skeletal, with only `id`, `name`, and `description` fields. The PHP entities do NOT define fields like `effects`, `requiredSkills`, `modifier`, `modification`, `cumulative`, `abilities`, `unique`, `characters`, `base`, or `allowed_negative` as class properties. The database tables created by migrations similarly have minimal columns.

2. **OpenRegister storage**: The full data model (with all the rich fields documented above) only exists when using OpenRegister schemas. In OpenRegister mode, objects are stored as JSON documents with full field support.

This means that **internal storage mode is functionally incomplete** -- entities stored internally will only have basic name/description fields and cannot participate in the full game mechanics system (effects, prerequisites, etc.). The app is designed to be used with OpenRegister for production use.

**Specific inconsistencies:**
- `Ability.php` entity: only `name`, `description` properties, but DB table has `base` and `allowed_negative` columns (not exposed via `getJsonFields()`)
- `Effect.php` entity: only `name`, `description` -- no `modifier`, `modification`, `cumulative`, `abilities`, `stat_id`
- `Skill.php` entity: only `name`, `description` -- no `effect`, `effects`, `requiredSkills`, etc.
- `Item.php` entity: only `name`, `description` -- no `effects`, `unique`, `characters`
- `Condition.php` entity: only `name`, `description` -- no `effects`, `unique`, `characters`

## Requirements

---

### Requirement: Ability CRUD

The system MUST support creating, reading, updating, and deleting abilities (stats) via the generic objects API.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-001 | System MUST support CRUD for abilities via `/api/objects/ability` | MUST | Implemented |
| MECH-002 | Ability MUST require a `name` field | MUST | Implemented |
| MECH-003 | Ability `base` MUST default to 0 if not specified | MUST | Implemented |
| MECH-004 | Abilities MUST be listable with search, pagination, and facets | MUST | Implemented |
| MECH-005 | Ability `allowed_negative` MUST default to false if not specified (from migration `Version0Date20241015141612`) | MUST | Implemented |

#### Scenario: Create an ability

- GIVEN a game master configuring the setting
- WHEN they POST to `/api/objects/ability` with name "Healing Mana" and base 5
- THEN the system MUST create the ability object
- AND all new characters MUST use base value 5 for "Healing Mana" in stat calculations

#### Scenario: Update an ability base value

- GIVEN an ability "XP" with base 0
- WHEN the game master updates base to 15 (all characters start with 15 XP)
- THEN the system MUST save the updated ability
- AND future stat calculations for all characters MUST use the new base value 15

---

### Requirement: Effect CRUD

The system MUST support creating, reading, updating, and deleting effects. Effects define the numeric modifications applied to abilities.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-010 | System MUST support CRUD for effects via `/api/objects/effect` | MUST | Implemented |
| MECH-011 | Effect MUST require a `name` field | MUST | Implemented |
| MECH-012 | Effect `modification` MUST default to `positive` if not specified | MUST | Implemented |
| MECH-013 | Effect `cumulative` MUST default to `non-cumulative` if not specified | MUST | Implemented |
| MECH-014 | Effect MUST support targeting multiple abilities via the `abilities` array | MUST | Implemented |
| MECH-015 | Effect MUST support legacy `stat_id` field for single-ability targeting | MUST | Implemented |

#### Scenario: Create a positive effect

- GIVEN an ability "Healing Mana" exists
- WHEN a game master creates an effect with name "+5 Healing Mana", modifier 5, modification "positive", and abilities ["healing-mana-uuid"]
- THEN the effect MUST be created
- AND when assigned to a skill/item/condition on a character, it MUST add 5 to Healing Mana

#### Scenario: Create a negative effect

- GIVEN an ability "HP" exists
- WHEN a game master creates an effect with name "-2 HP (Curse)", modifier 2, modification "negative", and abilities ["hp-uuid"]
- THEN the effect MUST be created
- AND when applied via a condition, it MUST subtract 2 from the character's HP

#### Scenario: Create an effect targeting multiple abilities

- GIVEN abilities "Arcane Mana" and "Spiritual Mana" exist
- WHEN a game master creates an effect with name "Universal Mana +3", modifier 3, modification "positive", abilities ["arcane-uuid", "spiritual-uuid"]
- THEN the effect MUST be created
- AND when applied, it MUST add 3 to BOTH abilities

---

### Requirement: Skill CRUD

The system MUST support creating, reading, updating, and deleting skills. Skills MUST support effect assignments and prerequisite definitions.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-020 | System MUST support CRUD for skills via `/api/objects/skill` | MUST | Implemented |
| MECH-021 | Skill MUST require a `name` field | MUST | Implemented |
| MECH-022 | Skill MUST support assigning multiple effects | MUST | Implemented |
| MECH-023 | Skill MUST support prerequisite definitions (requiredSkills, requiredStats, requiredConditions, requiredEffects, requiredScore) | MUST | Implemented |
| MECH-024 | Skills MUST be accessible from the navigation sidebar under "Skills" in the settings area | MUST | Implemented |
| MECH-025 | Skills list MUST support text search with debounce | MUST | Implemented |

#### Scenario: Create a skill with effects and prerequisites

- GIVEN effects "Healing LvL 1 Mana +5" and "XP Cost -10" exist
- AND skill "Basic Healing" exists
- WHEN a game master creates skill "Advanced Healing" with effects ["mana-effect-uuid", "xp-cost-uuid"], requiredSkills ["basic-healing-uuid"], requiredScore 15
- THEN the skill MUST be created with all relationships
- AND when assigned to a character, both effects MUST be applied during stat calculation

#### Scenario: List all skills

- GIVEN 10 skills exist in the system
- WHEN the user navigates to the Skills view
- THEN all 10 skills MUST be listed
- AND each skill MUST show its name and description

---

### Requirement: Item CRUD

The system MUST support creating, reading, updating, and deleting items. Items MUST support effect assignments and uniqueness tracking.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-030 | System MUST support CRUD for items via `/api/objects/item` | MUST | Implemented |
| MECH-031 | Item MUST require a `name` field | MUST | Implemented |
| MECH-032 | Item MUST support assigning multiple effects | MUST | Implemented |
| MECH-033 | Item MUST support `unique` flag to indicate one-of-a-kind artifacts | MUST | Implemented |
| MECH-034 | Item MUST track which characters hold it via the `characters` array | MUST | Implemented |
| MECH-035 | Items MUST be accessible from the main navigation sidebar | MUST | Implemented |

#### Scenario: Create a unique item

- GIVEN an effect "Arcane Power +10" exists
- WHEN a game master creates item "Hand of Vecna" with unique true, effects ["arcane-effect-uuid"]
- THEN the item MUST be created as a unique artifact
- AND only one character SHOULD hold it at a time

#### Scenario: Create a non-unique item

- GIVEN an effect "Attack +1" exists
- WHEN a game master creates item "Generic Magic Sword" with unique false, effects ["attack-effect-uuid"]
- THEN the item MUST be created as non-unique
- AND multiple characters MAY hold instances of it

---

### Requirement: Condition CRUD

The system MUST support creating, reading, updating, and deleting conditions. Conditions MUST support effect assignments.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-040 | System MUST support CRUD for conditions via `/api/objects/condition` | MUST | Implemented |
| MECH-041 | Condition MUST require a `name` field | MUST | Implemented |
| MECH-042 | Condition MUST support assigning multiple effects | MUST | Implemented |
| MECH-043 | Condition MUST support `unique` flag to indicate single-character applicability | MUST | Implemented |
| MECH-044 | Condition MUST track affected characters (readOnly) | MUST | Implemented |
| MECH-045 | Conditions MUST be accessible from the main navigation sidebar | MUST | Implemented |

#### Scenario: Create a condition with negative effects

- GIVEN an effect "-2 HP per day (Curse)" exists
- WHEN a game master creates condition "Vampiric Demeanor" with effects ["curse-effect-uuid"], unique false
- THEN the condition MUST be created
- AND when assigned to a character, the effect MUST subtract from the character's HP during stat calculation

#### Scenario: Create a unique condition

- GIVEN an effect "Chosen One +100 XP" exists
- WHEN a game master creates condition "The Chosen" with unique true, effects ["chosen-effect-uuid"]
- THEN the condition MUST be flagged as unique
- AND only one character SHOULD be affected by it

---

### Requirement: Effect Chain Integrity

The effect system MUST maintain integrity across the chain: Ability <- Effect <- Skill/Item/Condition/Event <- Character.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-050 | Modifying an effect's modifier MUST change the calculation result for all characters that have skills/items/conditions containing that effect | MUST | Implemented |
| MECH-051 | Removing an effect from a skill MUST remove that effect's contribution from character stat calculations | MUST | Implemented |
| MECH-052 | Deleting an ability MUST NOT crash the stat engine (null ability IDs are gracefully skipped) | MUST | Implemented |
| MECH-053 | The stat engine MUST handle null or missing effect IDs without throwing errors | MUST | Implemented |

#### Scenario: Effect chain recalculation

- GIVEN ability "Mana" (base 0), effect "Mana +5" (modifier 5, positive, targets Mana)
- AND skill "Basic Magic" contains effect "Mana +5"
- AND character "Merlin" has skill "Basic Magic"
- WHEN the stat engine calculates Merlin's stats
- THEN Mana MUST equal 5 (base 0 + 5)
- WHEN a game master changes the effect modifier from 5 to 10
- AND the character is recalculated
- THEN Mana MUST equal 10 (base 0 + 10)

---

### Requirement: Audit Trail for All Mechanic Entities

All game mechanic entities MUST support audit trail viewing when backed by OpenRegister.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-060 | Each entity type (skill, item, condition, effect, ability) MUST support audit trail retrieval via `/api/objects/{type}/{id}/audit` | MUST | Implemented |
| MECH-061 | Each entity type MUST support relations retrieval via `/api/objects/{type}/{id}/relations` | MUST | Implemented |
| MECH-062 | Each entity type MUST support uses retrieval via `/api/objects/{type}/{id}/uses` | MUST | Implemented |

#### Scenario: View skill audit trail

- GIVEN a skill "Healing LvL 1" that has been edited 3 times
- WHEN the user views the audit trail for this skill
- THEN the system MUST display all 3 change records with timestamps and changed values

## User Interface

### Navigation Placement

- **Abilities**: Settings area at bottom of sidebar (ShieldSword icon)
- **Skills**: Settings area at bottom of sidebar (SwordCross icon)
- **Effects**: Settings area at bottom of sidebar (MagicStaff icon)
- **Items**: Main navigation area (Sword icon)
- **Conditions**: Main navigation area (EmoticonSick icon)

### Per-Entity Views

Each entity type has a consistent set of views:
- **List view** (`{type}s/` directory): Shows all entities with search
- **Detail view**: Shows entity properties, audit trails, relations
- **Modal** (`modals/{type}/` directory): Create/edit form

### Stores

Each entity has a dedicated Pinia store (`store/modules/{type}.js`) following the same pattern:
- `{type}Item` -- currently selected entity
- `{type}List` -- all entities of this type
- `auditTrails`, `relations`, `uses` -- per-entity metadata
- `refresh{Type}List()` -- fetch all entities
- `get{Type}(id)` -- fetch single entity
- `save{Type}(item)` -- create or update
- `delete{Type}()` -- delete currently selected entity
- `setSearchTerm(term)` -- debounced search
