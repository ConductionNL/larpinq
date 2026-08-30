---
status: approved
---

# Character Management — Design

## Architecture

Character management uses a layered architecture:

1. **Frontend**: Vue SPA with generic `ObjectList.vue` and `ObjectDetail.vue` views, powered by `@conduction/nextcloud-vue`'s `createObjectStore`.
2. **Backend Data Access**: `RegisterObjectFetcher` resolves OpenRegister mappers per object type from `IAppConfig`.
3. **Stat Engine**: `CharacterService.calculateCharacter()` computes ability scores by applying effects from skills, items, conditions, and events in deterministic order.
4. **Storage**: All character data lives in OpenRegister (no internal DB mappers for characters).

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| View | `ObjectList.vue` | Character list with search, pagination, facets |
| View | `ObjectDetail.vue` | Character detail with tabs (properties, relations, audit) |
| Store | `object.js` | Generic CRUD via `createObjectStore('object')` |
| Router | `router/index.js` | `/characters` and `/characters/:id` routes |
| Navigation | `MainMenu.vue` | Characters nav item with BriefcaseAccountOutline icon |
| PHP Service | `CharacterService` | Stat calculation engine |
| PHP Service | `RegisterObjectFetcher` | Data access layer |

## Data Flow

1. User creates/updates character via frontend form dialog
2. Object store calls OpenRegister API (`/api/objects/{register}/{schema}`)
3. OpenRegister persists the object
4. On read, `CharacterService.calculateCharacter()` applies effects:
   - `initializeAbilityScores()` — base values from all abilities
   - `applyEntityEffects()` for skills, items, conditions, events (in order)
   - Each effect modifies ability scores via `applyModifierToAbility()`
   - Full audit trail recorded per modification

## Decisions

- **No internal DB storage**: Characters are stored exclusively in OpenRegister.
- **Deterministic effect order**: Skills → Items → Conditions → Events.
- **Currency fields**: gold, silver, copper stored as character properties.
- **Approval workflow**: Character `approved` boolean field.
