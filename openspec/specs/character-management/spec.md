# Character Management

## Purpose

Provides full CRUD lifecycle management for LARP characters, including player characters, NPCs, and other character types. Characters serve as the central entity in the application, linking to skills, items, conditions, and events. The system includes a stat calculation engine that automatically computes ability scores based on associated effects, a currency system (gold/silver/copper), approval workflow, and background/notes management.

## Requirements

### Character CRUD

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-001 | Create characters with name, description, background, faith, notice, and notes fields | MUST | Implemented |
| CHAR-002 | Update existing characters with all editable fields | MUST | Implemented |
| CHAR-003 | Delete characters with confirmation dialog | MUST | Implemented |
| CHAR-004 | List characters with search, pagination, and faceted results | MUST | Implemented |
| CHAR-005 | View single character detail page with tabbed interface | MUST | Implemented |
| CHAR-006 | Associate a character with a player profile via `ocName` field (referencing a Player object) | MUST | Implemented |
| CHAR-007 | Character name is required (validated by Zod on frontend) | MUST | Implemented |

### Character Types and Approval

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-010 | Characters have a `type` field with values: `player`, `npc`, `other` | MUST | Implemented |
| CHAR-011 | Characters have an `approved` field with values: `no`, `approved` | MUST | Implemented |
| CHAR-012 | Character list displays approval status as detail badge ("Approved" / "Not approved") | MUST | Implemented |
| CHAR-013 | Character detail page includes an "Accoderen" (approve) action button | MUST | Implemented |

### Currency System

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-020 | Characters track currency in three denominations: `gold`, `silver`, `copper` | MUST | Implemented |
| CHAR-021 | Currency values default to 0 when not set | MUST | Implemented |

### Character Associations

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-030 | Add/remove skills to/from a character via dedicated modals | MUST | Implemented |
| CHAR-031 | Add/remove items to/from a character via dedicated modals | MUST | Implemented |
| CHAR-032 | Add/remove conditions to/from a character via dedicated modals | MUST | Implemented |
| CHAR-033 | Add/remove events to/from a character via dedicated modals | MUST | Implemented |
| CHAR-034 | Character list items extend `ocName`, `skills`, `items`, `conditions`, and `events` when fetching | MUST | Implemented |
| CHAR-035 | Associations are stored as arrays of UUIDs and resolved to full objects on fetch via `_extend` parameter | MUST | Implemented |

### Stat Calculation Engine

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-040 | On character create/update, `CharacterService.calculateCharacter()` is invoked automatically | MUST | Implemented |
| CHAR-041 | The engine initializes all ability scores from their base values | MUST | Implemented |
| CHAR-042 | Effects from the character's skills are applied to ability scores | MUST | Implemented |
| CHAR-043 | Effects from the character's items are applied to ability scores | MUST | Implemented |
| CHAR-044 | Effects from the character's conditions are applied to ability scores | MUST | Implemented |
| CHAR-045 | Effects from the character's events are applied to ability scores | MUST | Implemented |
| CHAR-046 | Each effect can modify one or more abilities (via `abilities[]` array or legacy `stat_id` field) | MUST | Implemented |
| CHAR-047 | Positive modification adds the modifier value; negative modification subtracts it | MUST | Implemented |
| CHAR-048 | An audit trail is generated per ability showing each effect applied (old value, new value, effect data) | MUST | Implemented |
| CHAR-049 | The computed `stats` object is stored on the character with per-ability `name`, `base`, `value`, and `audit` entries | MUST | Implemented |

### Batch Recalculation

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-070 | `CharacterService.calculateAllCharacters()` MUST retrieve all characters via `characterMapper.findAll()` and recalculate stats for each | MUST | Implemented |
| CHAR-071 | `calculateAllCharacters()` MUST return an array of updated character arrays with recalculated stats | MUST | Implemented |
| CHAR-072 | `calculateAllCharacters()` is not exposed via any API route or controller -- it can only be called programmatically | SHOULD | Implemented |

### Entity Preloading and DI Bugs

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-080 | `CharacterService._loadAllEntities()` MUST preload all skills, items, conditions, events, effects, and abilities into indexed private arrays on construction | MUST | Bug |
| CHAR-081 | `_loadAllEntities()` calls `$this->objectService->getObjects()` but `objectService` is NOT injected via the constructor and does not exist as a class property -- this will cause a fatal error at construction time | MUST | Bug |
| CHAR-082 | The constructor injects `AbilityMapper`, `CharacterMapper`, `ConditionMapper`, `EffectMapper`, `EventMapper`, `ItemMapper` but does NOT inject `SkillMapper` or `ObjectService` -- both are required by internal methods | MUST | Bug |
| CHAR-083 | If `_loadAllEntities()` were to work, it would preload ALL entities into memory at service construction time, which could be a performance issue for large datasets | SHOULD | Bug |

### Internal vs OpenRegister Storage

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-090 | In **internal storage mode**, the Character entity is skeletal: only `id`, `name`, and `description` fields exist (as defined in `lib/Db/Character.php`) | MUST | Implemented |
| CHAR-091 | The full character data model (ocName, background, faith, notice, slNotesPublic, slNotesPrivate, card, stats, gold, silver, copper, events, skills, items, conditions, type, approved) only exists when using **OpenRegister storage mode** with a rich schema | MUST | Implemented |
| CHAR-092 | There is no `larpingapp_characters` table defined in the database migrations (`Version0Date20240826193657`), meaning the characters table referenced by `CharacterMapper` does not exist -- internal storage for characters cannot work | MUST | Bug |
| CHAR-093 | The stat calculation engine (`calculateCharacter()`) operates on arrays and is storage-agnostic -- it works in both modes as long as the character data array contains the expected fields | MUST | Implemented |

### Character Detail Tabs

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-050 | "Eigenschappen" (Properties/Stats) tab showing computed ability scores with name and value | MUST | Implemented |
| CHAR-051 | "Skills" tab listing associated skills with count badge | MUST | Implemented |
| CHAR-052 | "Items" tab listing associated items with count badge | MUST | Implemented |
| CHAR-053 | "Conditions" tab listing associated conditions with count badge | MUST | Implemented |
| CHAR-054 | "Events" tab listing associated events with count badge | MUST | Implemented |
| CHAR-055 | "Background" tab displaying character background text | MUST | Implemented |
| CHAR-056 | "Logging" tab showing audit trail entries from OpenRegister | MUST | Implemented |

### Shared OpenRegister Features

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| CHAR-060 | View audit trail for a character (when using OpenRegister data source) | MUST | Implemented |
| CHAR-061 | View relations for a character | MUST | Implemented |
| CHAR-062 | View uses for a character | MUST | Implemented |
| CHAR-063 | Lock/unlock a character to prevent concurrent modification | MUST | Implemented |
| CHAR-064 | Revert a character to a previous state via audit trail | MUST | Implemented |

## Data Model

### Character Entity

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

The internal Nextcloud entity is skeletal compared to the full data model:

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Auto-incrementing primary key |
| name | string | Character name |
| description | string | Character description |

All other fields (ocName, background, skills, items, conditions, events, stats, gold, silver, copper, type, approved, etc.) are only available when using OpenRegister storage mode with a properly configured schema.

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

### Character List (`CharactersList.vue`)

- Sticky header with search input (debounced 500ms) and action buttons (refresh, add new)
- `NcListItem` rows showing character name, player name (ocName subname), approval status badge, and skill count
- Click to select and view details; inline edit/delete action buttons
- Loading spinner during data fetch; empty state message when no characters

### Character Details (`CharacterDetails.vue`)

- Header with character name and "Acties" dropdown menu containing: Edit, Skills bewerken, Items bewerken, Condities bewerken, Events bewerken, Als pdf downloaden, Accoderen, Verwijderen
- Notice card displayed when character has a `notice` value
- Summary and description text
- Tabbed interface (Bootstrap Vue `BTabs`) with seven tabs: Eigenschappen, Skills, Items, Conditions, Events, Background, Logging

### Character Edit Modal (`EditCharacter.vue`)

- `NcDialog` with fields: Name (required), Description, Background, Notice
- Player selector (`NcSelect`) populated from the Player store
- Creates new character (POST) or updates existing (PUT) via the character store
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

## Scenarios

### Create a New Character

```
GIVEN the user is on the Characters page
WHEN they click "Karakter toevoegen" and fill in the name and description
AND select a player from the OC Name dropdown
AND click "Aanmaken"
THEN a new character is created via POST /api/objects/character
AND CharacterService.calculateCharacter() computes the stats (empty if no associations)
AND the character list is refreshed
AND the modal closes after showing a success message
```

### Assign a Skill to a Character

```
GIVEN a character exists with no skills
WHEN the user opens the character detail and clicks "Skills bewerken"
AND selects a skill that has an Effect with modifier +3 on Strength ability
AND saves the character
THEN the character's skills array includes the new skill UUID
AND CharacterService recalculates stats
AND the Strength ability value increases by 3 from its base
AND the audit trail for Strength shows the applied effect
```

### View Computed Stats

```
GIVEN a character has skills with effects targeting Strength (+5) and Dexterity (-2)
WHEN the user views the character detail page
AND opens the "Eigenschappen" tab
THEN each ability is listed with its computed value
AND Strength shows base + 5
AND Dexterity shows base - 2
```

### Delete a Character

```
GIVEN a character exists in the list
WHEN the user clicks the delete action and confirms in the dialog
THEN the character is removed via DELETE /api/objects/character/{id}
AND the character list is refreshed
AND the active character is cleared
```

### Search Characters

```
GIVEN multiple characters exist
WHEN the user types "dragon" into the search field
THEN after a 500ms debounce the character list refreshes with filtered results
WHEN the user clears the search field
THEN the full character list is displayed again
```

### Batch Recalculation

```
GIVEN multiple characters exist with various skill/item/condition/event associations
WHEN calculateAllCharacters() is called programmatically
THEN all characters are retrieved via characterMapper.findAll()
AND each character has its stats recalculated via calculateCharacter()
AND the method returns an array of all updated character data arrays
```

## Dependencies

- **ObjectService**: Generic CRUD dispatch to internal mappers or OpenRegister
- **CharacterService**: Stat calculation engine (`calculateCharacter()`, `calculateAllCharacters()`)
- **ObjectsController**: Generic API controller that invokes CharacterService for character create/update
- **Pinia character store**: Frontend state management with `Character` entity class hydration
- **OpenRegister** (optional): Audit trails, relations, uses, locking, reverting
- **Player entity**: Referenced via `ocName` for player-character association
- **Ability entity**: Provides base values for stat computation
- **Effect entity**: Provides modifiers applied during stat calculation
