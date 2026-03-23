---
status: implemented
---

# Events and Players

## Purpose

Manages LARP events (game gatherings with date ranges, locations, and participant tracking) and player profiles (real-world people who play characters). Events can carry Effects that are applied to participating characters during stat calculation via `CharacterService.applyEntityEffects()`. Players serve as the link between real-world people and their in-game characters via the character's `ocName` field. Both entity types are managed through the generic object store pattern and support OpenRegister features (audit trails, relations, locking).

## Requirements

---

### Requirement: Event CRUD Operations

The system MUST support creating, reading, updating, and deleting LARP events with date ranges, location, player assignments, and effect associations.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EVT-001 | Create events with name, description, start date, end date, and location | MUST | Implemented |
| EVT-002 | Update existing events with all editable fields | MUST | Implemented |
| EVT-003 | Delete events with confirmation dialog | MUST | Implemented |
| EVT-004 | List events with search and pagination | MUST | Implemented |
| EVT-005 | View event details with Characters (relations) and Logging tabs | MUST | Implemented |
| EVT-006 | Assign players to events via `players[]` UUID array | MUST | Implemented |
| EVT-007 | Assign effects to events via `effects[]` UUID array for post-event stat modifications | MUST | Implemented |
| EVT-008 | Event effects MUST be applied to associated characters during stat calculation | MUST | Implemented |
| EVT-009 | Event name MUST be required | MUST | Implemented |
| EVT-010 | Events MUST be accessible from the main navigation sidebar | MUST | Implemented |

#### Scenario: Create an event with effects

- GIVEN an effect "Event Blessing" (modifier +2, positive, targeting "Mana") exists
- WHEN a game master creates event "Summer LARP 2025" with start date 2025-06-01, end date 2025-06-03, location "Forest Camp"
- AND assigns the effect "Event Blessing"
- THEN the event MUST be stored with effects = [event-blessing-uuid]
- AND any character associated with this event MUST receive +2 Mana during stat calculation

#### Scenario: Update an event

- GIVEN event "Summer LARP 2025" exists
- WHEN the game master updates the end date to 2025-06-05 and adds a second effect
- AND saves the event
- THEN the event MUST be updated with the new end date and both effects
- AND characters associated with this event MUST have stats recalculated with both effects

#### Scenario: Delete an event

- GIVEN event "Summer LARP 2025" exists
- WHEN the game master deletes the event
- THEN the event MUST be removed
- AND characters that had this event assigned MUST retain it in their events[] array (stale reference)
- AND stat recalculation for those characters MUST gracefully skip the missing event

#### Scenario: List events with search

- GIVEN events "Summer LARP 2025", "Winter Gathering", and "Spring Festival" exist
- WHEN the user types "summer" in the search field
- THEN after debounce the list MUST show only "Summer LARP 2025"

#### Scenario: View event participants via relations tab

- GIVEN event "Summer LARP 2025" has characters associated with it
- WHEN a user views the event detail page
- AND opens the "Characters" tab
- THEN the related characters MUST be listed via the relations endpoint

---

### Requirement: Event Effect Application to Characters

When a character has events in their `events[]` array, the stat calculation engine MUST apply those events' effects to the character's ability scores.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EVT-020 | `CharacterService.calculateCharacter()` MUST call `applyEntityEffects()` with property 'events' and the events lookup table | MUST | Implemented |
| EVT-021 | Event effects MUST be applied AFTER skills, items, and conditions effects (in that order) | MUST | Implemented |
| EVT-022 | Events with no effects MUST be skipped without error | MUST | Implemented |
| EVT-023 | Events referenced by a character but not found in the preloaded events map MUST be skipped gracefully | MUST | Implemented |

#### Scenario: Event effects applied during character stat calculation

- GIVEN ability "XP" with base 0
- AND event "Tournament" has effect "+50 XP" (modifier 50, positive)
- AND character "Fighter" has event "Tournament" assigned
- WHEN `calculateCharacter()` runs for Fighter
- THEN XP MUST equal 50 (base 0 + 50 from event effect)
- AND the XP audit trail MUST include the event effect entry

#### Scenario: Multiple events stacking effects

- GIVEN ability "Reputation" with base 0
- AND event "Battle of Helm's Deep" has effect "+10 Reputation"
- AND event "Council of Elrond" has effect "+5 Reputation"
- AND character "Aragorn" attended both events
- WHEN stats are calculated
- THEN Reputation MUST equal 15 (0 + 10 + 5)
- AND the audit trail MUST show both event effects

#### Scenario: Event with no effects

- GIVEN event "Social Gathering" has no effects assigned
- AND character "Bard" has this event assigned
- WHEN stats are calculated
- THEN no ability scores MUST be modified by this event
- AND no errors MUST occur

---

### Requirement: Player Profile Management

The system MUST support creating, reading, updating, and deleting player profiles that represent real-world people.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PLR-001 | Create player profiles with name and description | MUST | Implemented |
| PLR-002 | Update existing player profiles | MUST | Implemented |
| PLR-003 | Delete player profiles with confirmation dialog | MUST | Implemented |
| PLR-004 | List players with search and pagination | MUST | Implemented |
| PLR-005 | View player details with relations and logging tabs | MUST | Implemented |
| PLR-006 | Players MUST be referenced by characters via `ocName` field (linking real-world player to in-game character) | MUST | Implemented |
| PLR-007 | Player selector dropdown MUST be available in the character edit modal | MUST | Implemented |
| PLR-008 | Player name MUST be required | MUST | Implemented |
| PLR-009 | Players MUST be accessible from the main navigation sidebar | MUST | Implemented |

#### Scenario: Create a player and link to character

- GIVEN no players exist
- WHEN a game master creates player "John Doe" with description "Experienced LARP player"
- AND then creates character "Sir Lancelot"
- AND selects "John Doe" in the OC Name dropdown
- THEN the character's ocName field MUST reference the player's UUID
- AND the character list MUST show "John Doe" as the subname for "Sir Lancelot"

#### Scenario: Update a player profile

- GIVEN player "John Doe" exists
- WHEN the game master updates the description to "Veteran LARP player, 5 years experience"
- THEN the player MUST be updated with the new description
- AND characters referencing this player MUST still display the correct player name

#### Scenario: Delete a player with character references

- GIVEN player "John Doe" exists and is referenced by character "Sir Lancelot" via ocName
- WHEN the user deletes the player
- THEN the player MUST be removed
- AND the character's ocName reference MUST become stale (the UUID no longer resolves)
- AND the character list SHOULD still display the character but without a player subname

#### Scenario: Player selector in character modal

- GIVEN players "Alice", "Bob", and "Charlie" exist
- WHEN the user opens the character edit modal
- THEN the OC Name dropdown MUST list all 3 players
- AND selecting "Alice" MUST set ocName to Alice's UUID

#### Scenario: Search players

- GIVEN players "Alice Smith", "Bob Jones", and "Charlie Brown" exist
- WHEN the user types "jones" in the player search field
- THEN after debounce the list MUST show only "Bob Jones"

---

### Requirement: Shared OpenRegister Features

Events and Players MUST support OpenRegister-specific features when backed by OpenRegister storage.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EVP-001 | Events and Players MUST support audit trail viewing | MUST | Implemented |
| EVP-002 | Events and Players MUST support relation discovery | MUST | Implemented |
| EVP-003 | Events and Players MUST support lock/unlock | MUST | Implemented |
| EVP-004 | Events and Players MUST support revert to previous state | MUST | Implemented |

#### Scenario: View event audit trail

- GIVEN event "Summer LARP 2025" has been edited 3 times
- WHEN the user views the event detail and opens the Logging tab
- THEN 3 audit entries MUST be displayed with timestamps and changed values

#### Scenario: Lock a player profile

- GIVEN player "Alice" is being edited by game master A
- WHEN game master A locks the player
- THEN game master B MUST be prevented from editing Alice simultaneously

#### Scenario: Revert an event to previous state

- GIVEN event "Tournament" was incorrectly updated
- WHEN the game master views the audit trail and reverts to the previous version
- THEN the event data MUST be restored
- AND characters associated with the event MUST use the reverted effects on next stat calculation

---

### Requirement: Internal vs OpenRegister Storage

Events and Players MUST support both internal and OpenRegister storage modes, with significant data model differences between the two.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EVP-010 | In **internal storage mode**, the Event entity MUST use `title` (NOT `name`) as its primary label field | MUST | Implemented |
| EVP-011 | The internal Event entity MUST have a `userId` property that associates events with the creating Nextcloud user | MUST | Implemented |
| EVP-012 | The internal Event entity MUST have only: `id`, `title`, `description`, `startDate`, `endDate`, `userId` | MUST | Implemented |
| EVP-013 | The full Event data model (name, location, players array, effects array) MUST only exist in **OpenRegister storage mode** | MUST | Implemented |
| EVP-014 | The `title` vs `name` discrepancy MUST cause display issues when using internal storage -- the frontend expects `name` but the internal entity serializes `title` | MUST | Bug |
| EVP-015 | In **internal storage mode**, the Player entity MUST be skeletal: only `id`, `name`, and `description` fields | MUST | Implemented |
| EVP-016 | PlayerMapper.findAll() takes NO parameters, returning all players regardless of user | MUST | Implemented |
| EVP-017 | EventMapper.findAll() takes `(?int $limit, ?int $offset, ?array $filters, ?array $searchConditions, ?array $searchParams)` | MUST | Implemented |

#### Scenario: Internal storage event name mismatch

- GIVEN events are configured for internal storage
- WHEN a game master creates an event via the API with `{"name": "Summer LARP"}`
- THEN the internal Event entity MUST store it as `title` (not `name`)
- AND when serialized to JSON it MUST return `{"title": "Summer LARP"}` instead of `{"name": "Summer LARP"}`
- AND the frontend (which expects `name`) MUST NOT display the event label correctly

#### Scenario: Event with userId scoping in internal mode

- GIVEN events are configured for internal storage
- AND user "admin" creates event "Admin Event"
- AND user "player1" creates event "Player Event"
- WHEN user "admin" lists events
- THEN only "Admin Event" MUST be returned (events are user-scoped in internal mode)

#### Scenario: OpenRegister event with full fields

- GIVEN events are configured for OpenRegister storage
- WHEN a game master creates event "Summer LARP 2025" with name, location "Forest", players ["player-uuid"], effects ["effect-uuid"]
- THEN all fields MUST be stored in the JSON document
- AND the event MUST be visible to all users (not user-scoped)

---

### Requirement: Event-Character Linking

Characters MUST be able to link to events, and events MUST be able to track which characters participated.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EVP-020 | Characters MUST have an `events[]` UUID array linking to Event objects | MUST | Implemented |
| EVP-021 | Events MAY have a `players[]` UUID array linking to Player objects | MUST | Implemented |
| EVP-022 | Adding an event to a character MUST trigger stat recalculation | MUST | Implemented |
| EVP-023 | Removing an event from a character MUST trigger stat recalculation | MUST | Implemented |
| EVP-024 | The AddEventToCharacter modal MUST list available events for selection | MUST | Implemented |

#### Scenario: Assign an event to a character

- GIVEN event "Summer LARP 2025" exists with effect "+2 XP"
- AND character "Aragorn" exists
- WHEN the user opens the character detail and clicks "Events bewerken"
- AND adds "Summer LARP 2025" to the character
- AND saves
- THEN the character's events[] array MUST include the event UUID
- AND stat calculation MUST apply the event's effects (+2 XP)

#### Scenario: Remove an event from a character

- GIVEN character "Aragorn" has event "Summer LARP 2025" assigned
- WHEN the user removes "Summer LARP 2025" from the character's events
- AND saves
- THEN the events[] array MUST no longer contain the event UUID
- AND stats MUST be recalculated without the event's effects

#### Scenario: Event deletion does not cascade to characters

- GIVEN event "Summer LARP 2025" is assigned to 3 characters
- WHEN the event is deleted
- THEN the 3 characters MUST still have the event UUID in their events[] array
- BUT stat calculation MUST gracefully skip the missing event (no error, effect not applied)

---

## Data Model

### Event Entity (OpenRegister / Full Data Model)

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Event name |
| description | string | No | "" | Event description |
| startDate | string (datetime) | No | null | Event start date/time |
| endDate | string (datetime) | No | null | Event end date/time |
| location | string | No | "" | Event location |
| players | string[] (UUIDs) | No | [] | Array of Player IDs participating in this event |
| effects | string[] (UUIDs) | No | [] | Array of Effect IDs applied to characters who attend |

### Event Internal Entity (lib/Db/Event.php)

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Auto-incrementing primary key |
| title | string | Event label (**NOTE: uses `title` not `name`**) |
| description | string | Event description |
| startDate | datetime | Event start date/time |
| endDate | datetime | Event end date/time |
| userId | string | Nextcloud user ID of the event creator (user-scoped) |

### Player Entity (Full / OpenRegister)

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Player's real-world name |
| description | string | No | "" | Player profile description |

### Player Internal Entity (lib/Db/Player.php)

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Auto-incrementing primary key |
| name | string | Player's real-world name |
| description | string | Player profile description |

## User Interface

### Events Views

- **EventsList.vue**: List view with `NcListItem` rows, search, refresh, and add-new actions
- **EventDetails.vue**: Detail view with header showing event name, summary, and description. Action menu with Edit and Delete. Tabbed interface: Characters (relations) and Logging (audit trail)
- **EditEvent.vue / DeleteEvent.vue**: Create/edit and delete modals

### Players Views

- **PlayersList.vue**: List view with `NcListItem` rows, search, refresh, and add-new actions
- **PlayerDetails.vue**: Detail view showing player name, summary, and description. Action menu with Edit and Delete. Tabbed interface with relations and logging tabs
- **EditPlayer.vue / DeletePlayer.vue**: Create/edit and delete modals

### Character Association Modals

- **AddEventToCharacter.vue / DeleteEventFromCharacter.vue**: Add/remove events from characters

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/objects/event` | List events |
| POST | `/api/objects/event` | Create event |
| GET | `/api/objects/event/{id}` | Get single event |
| PUT | `/api/objects/event/{id}` | Update event |
| DELETE | `/api/objects/event/{id}` | Delete event |
| GET | `/api/objects/player` | List players |
| POST | `/api/objects/player` | Create player |
| GET | `/api/objects/player/{id}` | Get single player |
| PUT | `/api/objects/player/{id}` | Update player |
| DELETE | `/api/objects/player/{id}` | Delete player |

Both entity types additionally support: `{id}/lock`, `{id}/unlock`, `{id}/revert`, `{id}/audit`, `{id}/relations`, `{id}/uses`, `{id}/files`.

## Dependencies

- **RegisterObjectFetcher**: Data retrieval for both `event` and `player` object types
- **CharacterService**: Applies event effects during character stat calculation via `applyEntityEffects()`
- **Pinia stores**: `event.js`, `player.js` -- frontend state management
- **Character entity**: References players via `ocName` and events via `events[]`
- **Effect entity**: Events carry effects that modify character abilities
- **OpenRegister** (optional): Audit trails, relations, locking for both entity types
