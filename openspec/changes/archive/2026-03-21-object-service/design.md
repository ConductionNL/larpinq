---
status: approved
---

# Object Service — Design

## Architecture

The data access layer uses `RegisterObjectFetcher` as a thin wrapper around OpenRegister's `ObjectService`. It resolves register/schema IDs from `IAppConfig` per object type and delegates all CRUD operations to OpenRegister.

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| PHP Service | `RegisterObjectFetcher` | Mapper resolution and object retrieval |
| PHP Service | `CharacterService` | Consumes RegisterObjectFetcher for entity preloading |
| PHP Controller | `CharactersController` | Uses RegisterObjectFetcher for PDF export |
| Frontend Store | `object.js` | Generic CRUD via createObjectStore (calls OpenRegister API directly) |
| Frontend Store | `store.js` | Initializes object type registry from settings config |

## Key Methods

- `getMapper(objectType)` — Resolves OpenRegister mapper from `{type}_register` and `{type}_schema` config
- `getObjects(objectType, ...)` — Fetches all objects via mapper.findAll()
- `getObject(objectType, id)` — Fetches single object via mapper.find()
- `toArray(object)` — Converts object to array (supports jsonSerialize, array, cast)

## Config Key Pattern

For each object type (e.g., `character`):
- `character_register` — Register ID in IAppConfig
- `character_schema` — Schema ID in IAppConfig
- `character_source` — Source type (always `openregister`)
