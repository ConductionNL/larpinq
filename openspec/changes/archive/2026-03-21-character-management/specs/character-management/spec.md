---
status: enriched
---

# Character Management

## Purpose

Provides full CRUD lifecycle management for LARP characters, including player characters, NPCs, and other character types. Characters serve as the central entity in the application, linking to skills, items, conditions, and events. The system includes a stat calculation engine (`CharacterService.calculateCharacter()`) that automatically computes ability scores based on associated effects, a currency system (gold/silver/copper), approval workflow, and background/notes management. Character data is fetched via `RegisterObjectFetcher` which resolves OpenRegister mappers from per-type configuration.

## Requirements

---

### Requirement: Character CRUD Operations

The system MUST support creating, reading, updating, and deleting characters with all required fields.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-001 | Create characters with name, description, background, faith, notice, and notes fields | MUST | Implemented |
| CHAR-002 | Update existing characters with all editable fields | MUST | Implemented |
| CHAR-003 | Delete characters with confirmation dialog | MUST | Implemented |
| CHAR-004 | List characters with search, pagination, and faceted results | MUST | Implemented |
| CHAR-005 | View single character detail page with tabbed interface | MUST | Implemented |
| CHAR-006 | Associate a character with a player profile via `ocName` field (referencing a Player object) | MUST | Implemented |
| CHAR-007 | Character name is required (validated by Zod on frontend) | MUST | Implemented |
| CHAR-008 | Characters MUST be retrievable via `RegisterObjectFetcher.getObject('character', id)` | MUST | Implemented |
| CHAR-009 | Character lists MUST be retrievable via `RegisterObjectFetcher.getObjects('character')` with pagination and filtering | MUST | Implemented |
| CHAR-010 | Created/updated characters MUST have stats recalculated via `CharacterService.calculateCharacter()` | MUST | Implemented |

#### Scenario: Create a new character

- GIVEN the user is on the Characters page
- WHEN they click "Karakter toevoegen" and fill in name "Sir Lancelot" and description "A noble knight"
- AND select player "John Doe" from the OC Name dropdown
- AND click "Aanmaken"
- THEN a new character MUST be created via the character store
- AND `CharacterService.calculateCharacter()` MUST compute the stats (empty stats if no skill/item/condition associations)
- AND the character list MUST refresh to include "Sir Lancelot"
- AND the modal MUST close after showing a success notification

#### Scenario: Update an existing character

- GIVEN character "Sir Lancelot" exists
- WHEN the user opens the character detail, clicks Edit, and changes the background to "Born in Camelot"
- AND saves the character
- THEN the character MUST be updated with the new background
- AND stats MUST be recalculated
- AND the detail view MUST refresh to show the new background

#### Scenario: Delete a character

- GIVEN character "Sir Lancelot" exists in the list
- WHEN the user clicks the delete action and confirms in the dialog
- THEN the character MUST be removed via DELETE /api/objects/character/{id}
- AND the character list MUST refresh
- AND the active character MUST be cleared

#### Scenario: Search characters by name

- GIVEN characters "Sir Lancelot", "Merlin", and "Dragonborn" exist
- WHEN the user types "dragon" into the search field
- THEN after a 500ms debounce the character list MUST refresh with only "Dragonborn"
- WHEN the user clears the search field
- THEN the full character list MUST be displayed again

#### Scenario: Create character with required name validation

- GIVEN the user opens the character creation modal
- WHEN they leave the name field empty and attempt to save
- THEN the Zod validation MUST prevent submission
- AND an error indicator MUST appear on the name field

---

### Requirement: Character Types and Approval Workflow

Characters MUST have a type classification and an approval workflow for game master review.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-020 | Characters MUST have a `type` field with values: `player`, `npc`, `other` | MUST | Implemented |
| CHAR-021 | Characters MUST have an `approved` field with values: `no`, `approved` | MUST | Implemented |
| CHAR-022 | Character list MUST display approval status as detail badge ("Approved" / "Not approved") | MUST | Implemented |
| CHAR-023 | Character detail page MUST include an "Accoderen" (approve) action button | MUST | Implemented |
| CHAR-024 | Default type for new characters MUST be "player" | MUST | Implemented |
| CHAR-025 | Default approval status for new characters MUST be "no" | MUST | Implemented |

#### Scenario: Approve a character

- GIVEN character "Merlin" exists with approved="no"
- WHEN the game master opens the character detail and clicks "Accoderen"
- THEN the character's approved field MUST change to "approved"
- AND the list view MUST show "Approved" badge for Merlin

#### Scenario: Filter characters by approval status

- GIVEN 5 approved and 3 unapproved characters exist
- WHEN the user views the character list
- THEN each character MUST display its approval badge
- AND the user MUST be able to distinguish approved from unapproved characters

#### Scenario: Create NPC character

- GIVEN a game master opens the character creation modal
- WHEN they set type to "npc" and fill in name "Goblin Guard"
- AND save the character
- THEN the character MUST be created with type="npc"
- AND MUST appear in the character list

#### Scenario: View character type in list

- GIVEN characters of types "player", "npc", and "other" exist
- WHEN the user views the character list
- THEN each character MUST display its type classification

---

### Requirement: Currency System

Characters MUST track in-game wealth using a three-denomination currency system.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-030 | Characters MUST track currency in three denominations: `gold`, `silver`, `copper` | MUST | Implemented |
| CHAR-031 | Currency values MUST default to 0 when not set | MUST | Implemented |
| CHAR-032 | Currency values MUST be non-negative integers | SHOULD | Implemented |
| CHAR-033 | Currency MUST be editable via the character edit modal | MUST | Implemented |

#### Scenario: Set initial currency

- GIVEN a new character is created
- WHEN no currency values are specified
- THEN gold, silver, and copper MUST all default to 0

#### Scenario: Update character currency

- GIVEN character "Sir Lancelot" has gold=0, silver=0, copper=0
- WHEN the game master edits the character and sets gold=5, silver=12, copper=30
- AND saves the character
- THEN the character MUST be updated with the new currency values

#### Scenario: View currency on character sheet

- GIVEN character "Sir Lancelot" has gold=5, silver=12, copper=30
- WHEN the user views the character detail page
- THEN the currency values MUST be visible

---

### Requirement: Character Associations

Characters MUST support many-to-many relationships with skills, items, conditions, and events through UUID reference arrays.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-040 | Add/remove skills to/from a character via dedicated modals | MUST | Implemented |
| CHAR-041 | Add/remove items to/from a character via dedicated modals | MUST | Implemented |
| CHAR-042 | Add/remove conditions to/from a character via dedicated modals | MUST | Implemented |
| CHAR-043 | Add/remove events to/from a character via dedicated modals | MUST | Implemented |
| CHAR-044 | Associations MUST be stored as arrays of UUIDs and resolved to full objects on fetch via `_extend` parameter | MUST | Implemented |
| CHAR-045 | Adding/removing an association MUST trigger stat recalculation | MUST | Implemented |

#### Scenario: Assign a skill to a character

- GIVEN character "Merlin" exists with no skills
- AND skill "Fireball" exists with an effect "+5 Arcane Mana"
- WHEN the user opens the character detail and clicks "Skills bewerken"
- AND selects "Fireball" from the skill list
- AND saves the character
- THEN the character's skills array MUST include the Fireball UUID
- AND `CharacterService.calculateCharacter()` MUST recalculate stats
- AND the Arcane Mana ability value MUST increase by 5 from its base

#### Scenario: Remove an item from a character

- GIVEN character "Sir Lancelot" has item "Magic Sword" assigned
- WHEN the user opens "Items bewerken" and removes "Magic Sword"
- AND saves the character
- THEN the character's items array MUST no longer contain the Magic Sword UUID
- AND stats MUST be recalculated without the Magic Sword's effects

#### Scenario: Assign multiple conditions

- GIVEN character "Aragorn" exists
- AND conditions "Blessed" (+3 HP) and "Poisoned" (-2 HP) exist
- WHEN both conditions are assigned to Aragorn
- AND the character is saved
- THEN the conditions array MUST contain both UUIDs
- AND HP MUST be calculated as base + 3 - 2

#### Scenario: Assign an event to a character

- GIVEN event "Summer LARP 2025" exists with effect "+2 XP"
- AND character "Gandalf" exists
- WHEN the user assigns the event to Gandalf
- THEN the character's events array MUST include the event UUID
- AND XP MUST increase by 2 during stat calculation

#### Scenario: Extend associations on fetch

- GIVEN character "Merlin" has skills ["skill-uuid-1", "skill-uuid-2"]
- WHEN the character is fetched with `_extend=skills`
- THEN the skills array MUST contain the full skill objects (not just UUIDs)

---

### Requirement: Stat Calculation Engine

The `CharacterService.calculateCharacter()` MUST aggregate effects from all associated entities to compute final ability scores with a full audit trail.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-050 | On character create/update, `CharacterService.calculateCharacter()` MUST be invoked | MUST | Implemented |
| CHAR-051 | The engine MUST initialize all ability scores from their `base` values via `initializeAbilityScores()` | MUST | Implemented |
| CHAR-052 | Effects from the character's skills MUST be applied to ability scores via `applyEntityEffects()` | MUST | Implemented |
| CHAR-053 | Effects from the character's items MUST be applied to ability scores | MUST | Implemented |
| CHAR-054 | Effects from the character's conditions MUST be applied to ability scores | MUST | Implemented |
| CHAR-055 | Effects from the character's events MUST be applied to ability scores | MUST | Implemented |
| CHAR-056 | Each effect MUST modify one or more abilities via `abilities[]` array or legacy `stat_id` field | MUST | Implemented |
| CHAR-057 | Positive modification MUST add the modifier value; negative modification MUST subtract it | MUST | Implemented |
| CHAR-058 | An audit trail MUST be generated per ability showing each effect applied (old value, new value, effect data) | MUST | Implemented |
| CHAR-059 | The computed `stats` object MUST be stored on the character with per-ability `name`, `base`, `value`, and `audit` entries | MUST | Implemented |
| CHAR-060 | Effects MUST be applied in order: skills first, then items, then conditions, then events | MUST | Implemented |
| CHAR-061 | Null or missing entity IDs in association arrays MUST be gracefully skipped | MUST | Implemented |
| CHAR-062 | Null or missing effect IDs within entity effect arrays MUST be gracefully skipped | MUST | Implemented |

#### Scenario: Calculate stats for character with single skill

- GIVEN ability "Strength" with base 10
- AND effect "Strong Arm" with modifier 5, modification "positive", targeting Strength
- AND skill "Warrior Training" containing effect "Strong Arm"
- AND character "Conan" has skill "Warrior Training"
- WHEN `calculateCharacter()` is called for Conan
- THEN Strength value MUST be 15 (base 10 + 5)
- AND the Strength audit trail MUST contain one entry: `{type: "effect", old: 10, new: 15, effect: ...}`

#### Scenario: Calculate stats with multiple effect sources

- GIVEN ability "HP" with base 20
- AND skill effect "+5 HP", item effect "+3 HP", condition effect "-2 HP", event effect "+1 HP"
- AND character "Tank" has all four entity types assigned
- WHEN stats are calculated
- THEN HP MUST equal 27 (20 + 5 + 3 - 2 + 1)
- AND the HP audit trail MUST contain 4 entries in order: skill, item, condition, event

#### Scenario: Effect targeting multiple abilities

- GIVEN abilities "Arcane Mana" (base 0) and "Spiritual Mana" (base 0)
- AND effect "Universal Boost" with modifier 3, modification "positive", targeting both abilities
- AND skill "Meditation" containing "Universal Boost"
- AND character "Sage" has skill "Meditation"
- WHEN stats are calculated
- THEN Arcane Mana MUST be 3 and Spiritual Mana MUST be 3
- AND both abilities MUST have audit entries for "Universal Boost"

#### Scenario: Effect with legacy stat_id field

- GIVEN ability "Dexterity" with base 10
- AND effect "Quick Fingers" with modifier 2, modification "positive", stat_id pointing to Dexterity (no abilities[] array)
- WHEN the effect is applied
- THEN Dexterity MUST increase by 2
- AND the audit trail MUST record the modification

#### Scenario: Graceful handling of missing references

- GIVEN character "Broken" has skills ["valid-uuid", "nonexistent-uuid"]
- WHEN `calculateCharacter()` is called
- THEN the engine MUST process "valid-uuid" normally
- AND MUST skip "nonexistent-uuid" without throwing an error
- AND stats MUST reflect only the valid skill's effects

---

### Requirement: Batch Recalculation

The system MUST support batch recalculation of stats for all characters.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-070 | `CharacterService.calculateAllCharacters()` MUST retrieve all characters and recalculate each one's stats | MUST | Implemented |
| CHAR-071 | `calculateAllCharacters()` MUST return an array of updated character arrays with recalculated stats | MUST | Implemented |
| CHAR-072 | `calculateAllCharacters()` is not exposed via any API route or controller -- it can only be called programmatically | SHOULD | Implemented |
| CHAR-073 | `calculateAllCharacters()` uses `RegisterObjectFetcher.getObjects('character')` to fetch all characters | MUST | Implemented |

#### Scenario: Batch recalculate all characters

- GIVEN 5 characters exist with various skill/item/condition/event associations
- WHEN `calculateAllCharacters()` is called programmatically
- THEN all 5 characters MUST be retrieved
- AND each MUST have stats recalculated via `calculateCharacter()`
- AND the method MUST return 5 updated character arrays

#### Scenario: Batch recalculate with no characters

- GIVEN no characters exist in the system
- WHEN `calculateAllCharacters()` is called
- THEN the method MUST return an empty array

#### Scenario: Entity preloading for batch calculation

- GIVEN the CharacterService is constructed
- WHEN `loadAllEntities()` runs in the constructor
- THEN all skills, items, conditions, events, effects, and abilities MUST be loaded into indexed maps
- AND these preloaded entities MUST be used during all subsequent stat calculations

---

### Requirement: Entity Preloading

The `CharacterService` MUST preload all entity data on construction for efficient stat calculation.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-080 | Constructor MUST call `loadAllEntities()` to preload all skills, items, conditions, events, effects, and abilities | MUST | Implemented |
| CHAR-081 | `loadAllEntities()` MUST use `RegisterObjectFetcher.getObjects()` for each entity type | MUST | Implemented |
| CHAR-082 | Entities MUST be indexed by ID via `indexById()` for O(1) lookup during stat calculation | MUST | Implemented |
| CHAR-083 | Preloading ALL entities into memory at service construction time could be a performance concern for large datasets | SHOULD | Planned |

#### Scenario: Preload entities on construction

- GIVEN the system has 50 skills, 30 items, 20 conditions, 10 events, 80 effects, and 15 abilities
- WHEN `CharacterService` is constructed
- THEN all 205 entities MUST be loaded into memory
- AND each MUST be indexed by its ID for instant lookup

#### Scenario: Preload with missing OpenRegister configuration

- GIVEN the skill type has no register/schema configured in IAppConfig
- WHEN `CharacterService` is constructed and `loadAllEntities()` calls `getObjects('skill')`
- THEN `RegisterObjectFetcher` MUST throw an exception
- AND the service construction MUST fail

#### Scenario: Preload performance with large dataset

- GIVEN 500 skills, 200 items, and 1000 effects exist
- WHEN `CharacterService` is constructed
- THEN all 1700+ entities MUST be loaded into memory
- AND this MAY cause memory or latency issues for large LARP deployments

---

### Requirement: Internal vs OpenRegister Storage

The character entity MUST support dual storage modes, with the full data model only available in OpenRegister mode.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-090 | In **internal storage mode**, the Character entity has only `id`, `name`, and `description` fields (as defined in `lib/Db/Character.php`) | MUST | Implemented |
| CHAR-091 | The full character data model (ocName, background, faith, notice, slNotesPublic, slNotesPrivate, card, stats, gold, silver, copper, events, skills, items, conditions, type, approved) MUST only exist in **OpenRegister storage mode** | MUST | Implemented |
| CHAR-092 | There is no `larpingapp_characters` table in database migrations -- internal storage for characters cannot work | MUST | Bug |
| CHAR-093 | The stat calculation engine operates on arrays and is storage-agnostic | MUST | Implemented |

#### Scenario: Character in OpenRegister mode

- GIVEN character type is configured for OpenRegister with a rich schema
- WHEN a character is created with all fields (name, background, skills, items, etc.)
- THEN all fields MUST be stored as JSON in OpenRegister
- AND the stat calculation engine MUST have access to all association arrays

#### Scenario: Character in internal mode (broken)

- GIVEN character type is configured for internal storage
- WHEN any character operation is attempted
- THEN it MUST fail because `larpingapp_characters` table does not exist in the database
- AND a database exception MUST be thrown

#### Scenario: Stat engine works with any storage backend

- GIVEN a character data array with keys: skills, items, conditions, events
- WHEN `calculateCharacter()` is called with this array
- THEN the engine MUST process the array regardless of where it was loaded from
- AND the `stats` key MUST be populated on the returned array

---

### Requirement: Character Detail Tabs

The character detail page MUST display information in a tabbed interface.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-100 | "Eigenschappen" (Properties/Stats) tab MUST show computed ability scores with name and value | MUST | Implemented |
| CHAR-101 | "Skills" tab MUST list associated skills with count badge | MUST | Implemented |
| CHAR-102 | "Items" tab MUST list associated items with count badge | MUST | Implemented |
| CHAR-103 | "Conditions" tab MUST list associated conditions with count badge | MUST | Implemented |
| CHAR-104 | "Events" tab MUST list associated events with count badge | MUST | Implemented |
| CHAR-105 | "Background" tab MUST display character background text | MUST | Implemented |
| CHAR-106 | "Logging" tab MUST show audit trail entries from OpenRegister | MUST | Implemented |

#### Scenario: View computed stats in Eigenschappen tab

- GIVEN character "Merlin" has Strength=15, Dexterity=12, Mana=25
- WHEN the user opens the character detail and selects the "Eigenschappen" tab
- THEN each ability MUST be listed with its computed value
- AND the ability name MUST be displayed alongside the value

#### Scenario: View skills tab with count badge

- GIVEN character "Merlin" has 5 skills assigned
- WHEN the user views the character detail
- THEN the "Skills" tab MUST show a badge with "5"
- AND clicking the tab MUST list all 5 skills

#### Scenario: View background tab

- GIVEN character "Sir Lancelot" has background "Born in Camelot, raised as a knight"
- WHEN the user clicks the "Background" tab
- THEN the background text MUST be displayed

#### Scenario: View audit trail in Logging tab

- GIVEN character "Merlin" has been edited 3 times (OpenRegister mode)
- WHEN the user clicks the "Logging" tab
- THEN all 3 audit trail entries MUST be displayed with timestamps and changed values

---

### Requirement: Shared OpenRegister Features

Characters MUST support OpenRegister-specific features when backed by OpenRegister storage.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-110 | View audit trail for a character | MUST | Implemented |
| CHAR-111 | View relations for a character | MUST | Implemented |
| CHAR-112 | View uses for a character | MUST | Implemented |
| CHAR-113 | Lock/unlock a character to prevent concurrent modification | MUST | Implemented |
| CHAR-114 | Revert a character to a previous state via audit trail | MUST | Implemented |

#### Scenario: Lock a character for editing

- GIVEN character "Merlin" is unlocked
- WHEN a game master locks the character
- THEN other users MUST be prevented from editing Merlin
- AND the lock MUST be visible in the character detail

#### Scenario: Revert a character to previous state

- GIVEN character "Merlin" was edited incorrectly
- WHEN the game master views the audit trail and reverts to a previous version
- THEN the character data MUST be restored to that version
- AND stats MUST be recalculated based on the reverted data

#### Scenario: View character relations

- GIVEN character "Sir Lancelot" is referenced by event "Summer LARP 2025" and item "Excalibur"
- WHEN the user views the relations for Sir Lancelot
- THEN the event and item MUST be listed as related objects

---

## Data Model

### Character Entity (Full / OpenRegister)

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Character name |
| ocName | string (UUID) | No | "" | Reference to Player object (the real-world player) |
| description | string | No | "" | Character description |
| background | string | No | "" | Character backstory |
| itemsAndMoney | string | No | "" | Free-text items and money description |
| notice | string | No | "" | Notice/alert displayed as info card |
| faith | string | No | "" | Character's religious faith |
| slNotesPublic | string | No | "" | Public game master notes |
| slNotesPrivate | string | No | "" | Private game master notes |
| card | string | No | "" | Character card reference |
| stats | object | No | {} | Computed ability scores (populated by stat engine) |
| gold | number | No | 0 | Gold currency amount |
| silver | number | No | 0 | Silver currency amount |
| copper | number | No | 0 | Copper currency amount |
| events | string[] (UUIDs) | No | [] | Array of associated Event IDs |
| skills | string[] (UUIDs) | No | [] | Array of associated Skill IDs |
| items | string[] (UUIDs) | No | [] | Array of associated Item IDs |
| conditions | string[] (UUIDs) | No | [] | Array of associated Condition IDs |
| type | enum | No | "player" | Character type: `player`, `npc`, `other` |
| approved | enum | No | "no" | Approval status: `no`, `approved` |

### Internal Entity (lib/Db/Character.php)

The internal Nextcloud entity is skeletal:

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Auto-incrementing primary key |
| name | string | Character name |
| description | string | Character description |

### Computed Stats Structure

```json
{
  "<ability-uuid>": {
    "name": "Strength",
    "base": 10,
    "value": 15,
    "audit": [
      {
        "type": "effect",
        "effect": { "name": "Strong Arm", "modifier": 5, "modification": "positive" },
        "old": 10,
        "new": 15
      }
    ]
  }
}
```

## User Interface

### Character List

- Sticky header with search input (debounced 500ms) and action buttons (refresh, add new)
- `NcListItem` rows showing character name, player name (ocName subname), approval status badge, and skill count
- Click to select and view details; inline edit/delete action buttons
- Loading spinner during data fetch; empty state message when no characters

### Character Details

- Header with character name and "Acties" dropdown menu containing: Edit, Skills bewerken, Items bewerken, Condities bewerken, Events bewerken, Als pdf downloaden (visible only when DocuDesk is installed), Accoderen, Verwijderen
- DocuDesk availability check on mount
- Notice card displayed when character has a `notice` value
- Summary and description text
- Tabbed interface with seven tabs

### Character Edit Modal

- `NcDialog` with fields: Name (required), Description, Background, Notice
- Player selector (`NcSelect`) populated from the Player store
- Creates new character (POST) or updates existing (PUT)
- Success/error notification cards; auto-closes after 2 seconds on success

### Association Modals

- `AddSkillToCharacter.vue` / `DeleteSkillFromCharacter.vue`
- `AddItemToCharacter.vue` / `DeleteItemFromCharacter.vue`
- `AddConditionToCharacter.vue` / `DeleteConditionFromCharacter.vue`
- `AddEventToCharacter.vue` / `DeleteEventFromCharacter.vue`

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/objects/character` | List characters (supports `_search`, `_limit`, `_offset`, `_extend`, `_order`) |
| POST | `/api/objects/character` | Create character (triggers stat calculation) |
| GET | `/api/objects/character/{id}` | Get single character (supports `_extend`) |
| PUT | `/api/objects/character/{id}` | Update character (triggers stat calculation) |
| DELETE | `/api/objects/character/{id}` | Delete character |
| POST | `/api/objects/character/{id}/lock` | Lock character |
| POST | `/api/objects/character/{id}/unlock` | Unlock character |
| POST | `/api/objects/character/{id}/revert` | Revert to previous state |
| GET | `/api/objects/character/{id}/audit` | Get audit trail |
| GET | `/api/objects/character/{id}/relations` | Get relations |
| GET | `/api/objects/character/{id}/uses` | Get uses |
| GET | `/api/objects/character/{id}/files` | Get associated files |

## Dependencies

- **RegisterObjectFetcher**: Data retrieval from OpenRegister via per-type register/schema config
- **CharacterService**: Stat calculation engine (`calculateCharacter()`, `calculateAllCharacters()`)
- **Pinia character store**: Frontend state management with entity class hydration
- **OpenRegister** (optional): Audit trails, relations, uses, locking, reverting
- **Player entity**: Referenced via `ocName` for player-character association
- **Ability entity**: Provides base values for stat computation
- **Effect entity**: Provides modifiers applied during stat calculation
