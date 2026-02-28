# Events and Players

## Purpose

Manages LARP events (game gatherings with date ranges, locations, and participant tracking) and player profiles (real-world people who play characters). Events can carry Effects that are applied to participating characters during stat calculation. Players serve as the link between real-world people and their in-game characters via the character's `ocName` field.

## Requirements

### Event Management

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EVT-001 | Create events with name, description, start date, end date, and location | MUST | Implemented |
| EVT-002 | Update existing events | MUST | Implemented |
| EVT-003 | Delete events with confirmation dialog | MUST | Implemented |
| EVT-004 | List events with search and pagination | MUST | Implemented |
| EVT-005 | View event details with Characters (relations) and Logging tabs | MUST | Implemented |
| EVT-006 | Assign players to events (`players[]` array) | MUST | Implemented |
| EVT-007 | Assign effects to events (`effects[]` array) for post-event stat modifications | MUST | Implemented |
| EVT-008 | Event effects are applied to associated characters during stat calculation | MUST | Implemented |

### Player Management

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PLR-001 | Create player profiles with name and description | MUST | Implemented |
| PLR-002 | Update existing player profiles | MUST | Implemented |
| PLR-003 | Delete player profiles with confirmation dialog | MUST | Implemented |
| PLR-004 | List players with search and pagination | MUST | Implemented |
| PLR-005 | View player details with relations and logging tabs | MUST | Implemented |
| PLR-006 | Players are referenced by characters via `ocName` field (linking real-world player to in-game character) | MUST | Implemented |
| PLR-007 | Player selector dropdown is available in the character edit modal | MUST | Implemented |

### Shared OpenRegister Features

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EVP-001 | Events and Players support audit trail viewing | MUST | Implemented |
| EVP-002 | Events and Players support relation discovery | MUST | Implemented |
| EVP-003 | Events and Players support lock/unlock | MUST | Implemented |
| EVP-004 | Events and Players support revert to previous state | MUST | Implemented |

### Internal vs OpenRegister Storage

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EVP-010 | In **internal storage mode**, the Event entity uses `title` (NOT `name`) as its primary label field -- this differs from all other entities which use `name` | MUST | Implemented |
| EVP-011 | The internal Event entity (`lib/Db/Event.php`) has a `userId` property that associates events with the creating Nextcloud user -- events are user-scoped in internal mode | MUST | Implemented |
| EVP-012 | The internal Event entity has only: `id`, `title`, `description`, `startDate`, `endDate`, `userId` -- no `name`, `location`, `players[]`, or `effects[]` fields | MUST | Implemented |
| EVP-013 | The full Event data model (name, location, players array, effects array) only exists when using **OpenRegister storage mode** with a rich schema | MUST | Implemented |
| EVP-014 | The discrepancy between `title` (internal) and `name` (OpenRegister/frontend) means the frontend may display events incorrectly when using internal storage -- the frontend expects `name` but the internal entity serializes `title` | MUST | Bug |
| EVP-015 | In **internal storage mode**, the Player entity is skeletal: only `id`, `name`, and `description` fields exist (as defined in `lib/Db/Player.php`) | MUST | Implemented |

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

The internal Nextcloud entity differs from the full data model:

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Auto-incrementing primary key |
| title | string | Event label (**NOTE: uses `title` not `name`**) |
| description | string | Event description |
| startDate | datetime | Event start date/time |
| endDate | datetime | Event end date/time |
| userId | string | Nextcloud user ID of the event creator (events are user-scoped) |

The internal entity does NOT have: `name` (uses `title` instead), `location`, `players[]`, `effects[]`. These fields are only available in OpenRegister mode.

### Player Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Player's real-world name |
| description | string | No | "" | Player profile description |

### Player Internal Entity (lib/Db/Player.php)

The internal entity is skeletal:

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Auto-incrementing primary key |
| name | string | Player's real-world name |
| description | string | Player profile description |

## User Interface

### Events Views (`EventsList.vue`, `EventDetails.vue`, `EventsIndex.vue`)

- List view with `NcListItem` rows, search, refresh, and add-new actions
- Detail view with header showing event name, summary, and description
- Action menu with Edit and Delete options
- Tabbed interface with:
  - **Characters**: Shows related characters (via `eventStore.relations`)
  - **Logging**: Audit trail entries

### Players Views (`PlayersList.vue`, `PlayerDetails.vue`, `PlayersIndex.vue`)

- List view with `NcListItem` rows, search, refresh, and add-new actions
- Detail view showing player name, summary, and description
- Action menu with Edit and Delete options
- Tabbed interface with relations and logging tabs

### Edit/Delete Modals

- `EditEvent.vue` / `DeleteEvent.vue`
- `EditPlayer.vue` / `DeletePlayer.vue`
- `AddEventToCharacter.vue` / `DeleteEventFromCharacter.vue` (character association modals)

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

## Scenarios

### Create an Event with Effects

```
GIVEN an effect "Event Blessing" (modifier +2, positive, targeting "Mana") exists
WHEN a game master creates event "Summer LARP 2025" with start/end dates and assigns the effect
THEN the event is stored with effects = [event-blessing-uuid]
AND any character associated with this event receives +2 Mana during stat calculation
```

### Assign an Event to a Character

```
GIVEN event "Summer LARP 2025" exists with effects
AND character "Aragorn" exists
WHEN the user opens the character detail and clicks "Events bewerken"
AND adds "Summer LARP 2025" to the character
AND saves
THEN the character's events[] array includes the event UUID
AND stat calculation applies the event's effects to the character's abilities
```

### Create a Player and Link to Character

```
GIVEN no players exist
WHEN a game master creates player "John Doe" with a description
AND then creates character "Sir Lancelot"
AND selects "John Doe" in the OC Name dropdown
THEN the character's ocName field references the player's UUID
AND the character list shows "John Doe" as the subname for "Sir Lancelot"
```

### Delete a Player

```
GIVEN player "John Doe" exists and is referenced by character "Sir Lancelot"
WHEN the user deletes the player
THEN the player is removed via DELETE /api/objects/player/{id}
AND the character's ocName reference becomes stale (the character still exists but the player reference is broken)
```

### View Event Participants

```
GIVEN event "Summer LARP 2025" has characters associated with it
WHEN a user views the event detail page
AND opens the "Characters" tab
THEN the related characters are listed via the relations endpoint
```

### Internal Storage Event Name Mismatch

```
GIVEN events are configured for internal storage
WHEN a game master creates an event via the API with {"name": "Summer LARP"}
THEN the internal Event entity stores it as "title" (not "name")
AND when the event is serialized to JSON, it returns {"title": "Summer LARP"} instead of {"name": "Summer LARP"}
AND the frontend (which expects "name") may not display the event label correctly
```

## Dependencies

- **ObjectService**: Generic CRUD dispatch for both `event` and `player` object types
- **ObjectsController**: RESTful API endpoints
- **CharacterService**: Applies event effects during character stat calculation
- **Pinia stores**: `event.js`, `player.js` -- frontend state management
- **TypeScript entities**: `Event`, `Player` -- frontend models with type definitions
- **Character entity**: References players via `ocName` and events via `events[]`
- **Effect entity**: Events carry effects that modify character abilities
- **OpenRegister** (optional): Audit trails, relations, locking for both entity types
