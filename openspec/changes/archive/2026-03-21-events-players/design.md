---
status: approved
---

# Events and Players — Design

## Architecture

Events and Players use the same generic object store pattern as other entity types. Both are managed via OpenRegister with CRUD through the frontend object store.

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| View | `ObjectList.vue` | Event/Player lists with search and pagination |
| View | `ObjectDetail.vue` | Event/Player detail with relations and audit tabs |
| Store | `object.js` | Generic CRUD via createObjectStore |
| Router | `router/index.js` | `/events`, `/events/:id`, `/players`, `/players/:id` |
| Navigation | `MainMenu.vue` | Events (CalendarMonthOutline), Players (AccountGroupOutline) |
| PHP Service | `CharacterService` | Applies event effects to characters via `applyEntityEffects()` |

## Event Effect Integration

Events carry an `effects[]` array of effect UUIDs. During character stat calculation, `CharacterService.applyEntityEffects()` iterates the character's `events` property, looks up each event, and applies its effects to ability scores.

## Player-Character Link

Players are linked to characters via the character's `ocName` field, which references a Player object UUID. This association is managed through OpenRegister's relations system.
