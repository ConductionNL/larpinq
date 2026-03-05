---
status: reviewed
---

# RPG System

## Purpose

Defines the core RPG mechanics of LarpingApp: Skills, Items, Conditions, Effects, and Abilities (stats). These entities form an interconnected system where Effects serve as the bridge between game elements and character stats. Skills, Items, Conditions, and Events each carry Effects that modify Abilities. Skills additionally support a prerequisite system requiring other skills, stats, conditions, effects, or a minimum score.

## Requirements

### Ability (Stat) Management

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| ABIL-001 | Create abilities with name, description, and base value | MUST | Implemented |
| ABIL-002 | Update existing abilities | MUST | Implemented |
| ABIL-003 | Delete abilities with confirmation dialog | MUST | Implemented |
| ABIL-004 | List abilities with search and pagination | MUST | Implemented |
| ABIL-005 | View ability details with relations and audit trail tabs | MUST | Implemented |
| ABIL-006 | Abilities serve as targets for Effect modifiers during stat calculation | MUST | Implemented |
| ABIL-007 | Base value defaults to 0 when not specified | MUST | Implemented |
| ABIL-008 | Abilities have an `allowed_negative` boolean field (added in migration `Version0Date20241015141612`) that controls whether the ability value can go below zero during stat calculation | MUST | Implemented |

### Skill Management

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SKILL-001 | Create skills with name, description, and effect text | MUST | Implemented |
| SKILL-002 | Update existing skills | MUST | Implemented |
| SKILL-003 | Delete skills with confirmation dialog | MUST | Implemented |
| SKILL-004 | List skills with search and pagination | MUST | Implemented |
| SKILL-005 | View skill details with Effects, Characters (relations), and Logging tabs | MUST | Implemented |
| SKILL-006 | Assign one or more Effects to a skill | MUST | Implemented |
| SKILL-007 | Skill detail shows associated effects with name, modification type, and modifier value | MUST | Implemented |
| SKILL-008 | View which characters use this skill via relations tab | MUST | Implemented |

### Skill Prerequisites

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SKILL-010 | Skills can require other skills as prerequisites (`requiredSkills[]`) | MUST | Implemented |
| SKILL-011 | Skills can require minimum stat values (`requiredStats[]`) | MUST | Implemented |
| SKILL-012 | Skills can require specific conditions (`requiredConditions[]`) | MUST | Implemented |
| SKILL-013 | Skills can require specific effects (`requiredEffects[]`) | MUST | Implemented |
| SKILL-014 | Skills can require a minimum score threshold (`requiredScore`) | MUST | Implemented |

### Item Management

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| ITEM-001 | Create items with name, description, effect text, and uniqueness flag | MUST | Implemented |
| ITEM-002 | Update existing items | MUST | Implemented |
| ITEM-003 | Delete items with confirmation dialog | MUST | Implemented |
| ITEM-004 | List items with search and pagination | MUST | Implemented |
| ITEM-005 | View item details with relations and audit trail | MUST | Implemented |
| ITEM-006 | Assign one or more Effects to an item | MUST | Implemented |
| ITEM-007 | Items track which characters own them (`characters[]`) | MUST | Implemented |
| ITEM-008 | Items have a `unique` boolean flag (for one-of-a-kind items) | MUST | Implemented |

### Condition Management

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| COND-001 | Create conditions with name, description, effect text, and uniqueness flag | MUST | Implemented |
| COND-002 | Update existing conditions | MUST | Implemented |
| COND-003 | Delete conditions with confirmation dialog | MUST | Implemented |
| COND-004 | List conditions with search and pagination | MUST | Implemented |
| COND-005 | View condition details with relations and audit trail | MUST | Implemented |
| COND-006 | Assign one or more Effects to a condition | MUST | Implemented |
| COND-007 | Conditions track which characters are affected (`characters[]`) | MUST | Implemented |
| COND-008 | Conditions have a `unique` boolean flag | MUST | Implemented |

### Effect System

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EFF-001 | Create effects with name, description, modifier, modification type, and cumulative flag | MUST | Implemented |
| EFF-002 | Update existing effects | MUST | Implemented |
| EFF-003 | Delete effects with confirmation dialog | MUST | Implemented |
| EFF-004 | List effects with search and pagination | MUST | Implemented |
| EFF-005 | View effect details with "Used by" relations tab and audit trail | MUST | Implemented |
| EFF-006 | Effects target one or more abilities via `abilities[]` array | MUST | Implemented |
| EFF-007 | Effects have `modification` type: `positive` (adds modifier) or `negative` (subtracts modifier) | MUST | Implemented |
| EFF-008 | Effects have `cumulative` flag: `cumulative` or `non-cumulative` | MUST | Implemented |
| EFF-009 | The modifier is an integer value representing the magnitude of the effect | MUST | Implemented |
| EFF-010 | Effects are the bridge between Skills/Items/Conditions/Events and Abilities | MUST | Implemented |

### Shared OpenRegister Features

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| RPG-001 | All RPG entities support audit trail viewing (when using OpenRegister) | MUST | Implemented |
| RPG-002 | All RPG entities support relation discovery | MUST | Implemented |
| RPG-003 | All RPG entities support lock/unlock | MUST | Implemented |
| RPG-004 | All RPG entities support revert to previous state | MUST | Implemented |

### Internal vs OpenRegister Storage

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| RPG-010 | In **internal storage mode**, all entity types (Ability, Skill, Item, Condition, Effect) have skeletal PHP entities with only `id`, `name`, and `description` fields | MUST | Implemented |
| RPG-011 | The full data model for each entity type (effects arrays, prerequisites, unique flags, base values, etc.) only exists when using **OpenRegister storage mode** with rich schemas | MUST | Implemented |
| RPG-012 | Internal entity PHP classes (`lib/Db/*.php`) do NOT define fields like `effects`, `requiredSkills`, `requiredStats`, `modifier`, `modification`, `cumulative`, `abilities`, `unique`, `characters` -- these are OpenRegister schema fields | MUST | Implemented |
| RPG-013 | The Ability entity has `base` and `allowed_negative` columns added via migration `Version0Date20241015141612`, but these are NOT represented as properties in the `Ability` PHP entity class (`lib/Db/Ability.php` only has `name` and `description`) | MUST | Bug |
| RPG-014 | Mapper `findAll()` signatures are inconsistent across entity types: `AbilityMapper.findAll(string $userId)`, `EffectMapper.findAll(string $userId)`, `ConditionMapper.findAll(string $userId)` require a userId; `SkillMapper.findAll(?int $limit, ?int $offset, ?array $filters, ?array $searchConditions, ?array $searchParams)` takes search params; `ItemMapper.findAll()` takes no params. This causes `ObjectService.getObjects()` to fail when calling internal mappers with standardized parameters | MUST | Bug |

## Data Model

### Ability Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Ability name (e.g., "Strength", "Dexterity", "Mana") |
| description | string | No | "" | Description of what the ability represents |
| base | number | No | 0 | Base starting value for this ability on all characters |
| allowed_negative | boolean | No | false | Whether this ability value can go below zero during stat calculation (added in migration `Version0Date20241015141612`) |

### Ability Internal Entity (lib/Db/Ability.php)

The PHP entity class is skeletal and does not expose `base` or `allowed_negative`:

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Auto-incrementing primary key |
| name | string | Ability name |
| description | string | Ability description |

Note: The database table `larpingapp_abilities` has `base` (INTEGER, default 0) and `allowed_negative` (BOOLEAN, default false) columns from migration, but the Ability entity class does not define these as properties. They would only be accessible via `__call` magic or direct array access after hydration, not via `getJsonFields()` or `jsonSerialize()`.

### Skill Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Skill name |
| description | string | No | "" | Detailed skill description |
| effect | string | No | "" | Free-text effect description (for display) |
| effects | string[] (UUIDs) | No | [] | Array of Effect IDs that this skill grants |
| requiredSkills | string[] (UUIDs) | No | [] | Prerequisite Skill IDs |
| requiredStats | string[] (UUIDs) | No | [] | Prerequisite Ability IDs with minimum values |
| requiredConditions | string[] (UUIDs) | No | [] | Prerequisite Condition IDs |
| requiredEffects | string[] (UUIDs) | No | [] | Prerequisite Effect IDs |
| requiredScore | number | No | null | Minimum score threshold required |

### Item Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Item name |
| description | string | No | "" | Item description |
| effect | string | No | "" | Free-text effect description (for display) |
| effects | string[] (UUIDs) | No | [] | Array of Effect IDs that this item grants |
| unique | boolean | No | false | Whether this item is one-of-a-kind |
| characters | string[] (UUIDs) | No | [] | Characters that currently own this item |

### Condition Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Condition name (e.g., "Poisoned", "Blessed") |
| description | string | No | "" | Condition description |
| effect | string | No | "" | Free-text effect description (for display) |
| effects | string[] (UUIDs) | No | [] | Array of Effect IDs that this condition applies |
| unique | boolean | No | false | Whether this condition is unique |
| characters | string[] (UUIDs) | No | [] | Characters currently affected by this condition |

### Effect Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Effect name (e.g., "Strong Arm", "Weak Knees") |
| description | string | No | "" | Effect description |
| modifier | number | No | 0 | Integer modifier value (magnitude of the effect) |
| modification | enum | Yes | "positive" | Modification type: `positive` or `negative` |
| cumulative | enum | No | "non-cumulative" | Whether effect stacks: `cumulative` or `non-cumulative` |
| abilities | string[] (UUIDs) | Yes | [] | Target ability IDs this effect modifies |

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
                            |-- allowed_negative: false
                            +-- computed value: 15 (base + modifier)
```

## User Interface

### Abilities Views (`AbilitiesList.vue`, `AbilityDetails.vue`)

- List view with search and add/refresh actions
- Detail view showing name, description, and tabs for relations/logging
- Located in the Settings navigation area (bottom of left sidebar)

### Skills Views (`SkillsList.vue`, `SkillDetails.vue`)

- List view with search and add/refresh actions
- Detail view with three tabs:
  - **Effects**: Lists associated effects with name, modification type, and modifier counter
  - **Characters**: Shows which characters have this skill (via relations)
  - **Logging**: Audit trail entries
- Located in the Settings navigation area

### Items Views (`ItemsList.vue`, `ItemDetails.vue`)

- List view with search and add/refresh actions
- Detail view with tabs for relations and logging

### Conditions Views (`ConditionsList.vue`, `ConditionDetails.vue`)

- List view with search and add/refresh actions
- Detail view with tabs for relations and logging

### Effects Views (`EffectsList.vue`, `EffectDetails.vue`)

- List view with search and add/refresh actions
- Detail view with two tabs:
  - **Used by**: Shows which skills/items/conditions/events reference this effect (via relations)
  - **Logging**: Audit trail
- Located in the Settings navigation area

### Edit Modals

- `EditAbility.vue` / `DeleteAbility.vue`
- `EditSkill.vue` / `DeleteSkill.vue`
- `EditItem.vue` / `DeleteItem.vue`
- `EditCondition.vue` / `DeleteCondition.vue`
- `EditEffect.vue` / `AddEffect.vue` / `DeleteEffect.vue`

## API Endpoints

All RPG entity types use the generic `/api/objects/{objectType}` pattern:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/objects/ability` | List abilities |
| POST | `/api/objects/ability` | Create ability |
| GET | `/api/objects/ability/{id}` | Get single ability |
| PUT | `/api/objects/ability/{id}` | Update ability |
| DELETE | `/api/objects/ability/{id}` | Delete ability |
| GET | `/api/objects/skill` | List skills |
| POST | `/api/objects/skill` | Create skill |
| GET | `/api/objects/skill/{id}` | Get single skill |
| PUT | `/api/objects/skill/{id}` | Update skill |
| DELETE | `/api/objects/skill/{id}` | Delete skill |
| GET | `/api/objects/item` | List items |
| POST | `/api/objects/item` | Create item |
| GET | `/api/objects/item/{id}` | Get single item |
| PUT | `/api/objects/item/{id}` | Update item |
| DELETE | `/api/objects/item/{id}` | Delete item |
| GET | `/api/objects/condition` | List conditions |
| POST | `/api/objects/condition` | Create condition |
| GET | `/api/objects/condition/{id}` | Get single condition |
| PUT | `/api/objects/condition/{id}` | Update condition |
| DELETE | `/api/objects/condition/{id}` | Delete condition |
| GET | `/api/objects/effect` | List effects |
| POST | `/api/objects/effect` | Create effect |
| GET | `/api/objects/effect/{id}` | Get single effect |
| PUT | `/api/objects/effect/{id}` | Update effect |
| DELETE | `/api/objects/effect/{id}` | Delete effect |

All entity types additionally support: `{id}/lock`, `{id}/unlock`, `{id}/revert`, `{id}/audit`, `{id}/relations`, `{id}/uses`, `{id}/files`.

## Scenarios

### Create an Effect Targeting Multiple Abilities

```
GIVEN abilities "Strength" and "Constitution" exist
WHEN a user creates an effect "Hardy" with modifier 2, modification "positive", targeting both abilities
THEN the effect is stored with abilities = [strength-uuid, constitution-uuid]
AND when applied to a character, both Strength and Constitution increase by 2
```

### Create a Skill with Effects

```
GIVEN an effect "Precise Strike" (modifier +3, positive, targeting Dexterity) exists
WHEN a user creates a skill "Sword Mastery" and assigns the effect
THEN the skill is stored with effects = [precise-strike-uuid]
AND any character with "Sword Mastery" receives +3 Dexterity during stat calculation
```

### Skill Prerequisites

```
GIVEN skill "Advanced Swordplay" requires skill "Basic Swordplay" as prerequisite
AND requires a minimum score of 5 on the "Combat" ability
WHEN viewing the skill details
THEN requiredSkills contains the "Basic Swordplay" UUID
AND requiredScore is 5
```

### Effect Modification Types

```
GIVEN an ability "Health" with base value 20
AND a "positive" effect with modifier 5
AND a "negative" effect with modifier 3
WHEN both effects are applied to a character
THEN Health = 20 + 5 - 3 = 22
AND the audit trail shows two entries with old/new values
```

### Item with Unique Flag

```
GIVEN an item "Excalibur" is created with unique = true
WHEN a character receives this item
THEN the item's characters[] array includes that character's UUID
AND the unique flag indicates this is a one-of-a-kind item
```

### Condition Affecting Characters

```
GIVEN a condition "Poisoned" has an effect with modifier 5, modification "negative", targeting "Health"
WHEN the condition is applied to a character
THEN the character's Health ability decreases by 5
AND the condition's characters[] array includes the character's UUID
AND removing the condition from the character restores the Health value on next recalculation
```

### Allowed Negative Ability

```
GIVEN an ability "Mana" with base value 3 and allowed_negative = false
AND a "negative" effect with modifier 5 targeting "Mana"
WHEN the effect is applied to a character
THEN the allowed_negative flag indicates whether Mana should clamp at 0 or go to -2
NOTE: The allowed_negative field exists in the database but is not currently used by the stat calculation engine
```

## Dependencies

- **ObjectService**: Generic CRUD for all entity types via `getMapper()` dispatch
- **ObjectsController**: RESTful API endpoints for all entity types
- **CharacterService**: Consumes effects from skills/items/conditions/events during stat calculation
- **Pinia stores**: `ability.js`, `skill.js`, `item.js`, `condition.js`, `effect.js` -- frontend state management
- **TypeScript entities**: `Ability`, `Skill`, `Item`, `Condition`, `Effect` -- Zod-validated frontend models
- **OpenRegister** (optional): Provides audit trails, relations, locking, and schema validation for all entity types
