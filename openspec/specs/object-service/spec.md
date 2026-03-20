---
status: enriched
---

# Object Service

## Purpose

The data access layer for LarpingApp has been refactored from a single monolithic `ObjectService` to a thin, focused `RegisterObjectFetcher` (`lib/Service/RegisterObjectFetcher.php`). This service provides object retrieval from OpenRegister by resolving register and schema IDs from IAppConfig per object type. It replaces the previous generic CRUD dispatch pattern (internal mappers vs OpenRegister) with direct cross-app calls to OpenRegister's ObjectService. The `CharacterService` uses `RegisterObjectFetcher` to load entities for stat calculation, and the `CharactersController` uses it to fetch character data for PDF export.

**Key source files:**
- `lib/Service/RegisterObjectFetcher.php` -- Main data access service
- `lib/Service/CharacterService.php` -- Consumes RegisterObjectFetcher for entity preloading
- `lib/Controller/CharactersController.php` -- Uses RegisterObjectFetcher for character retrieval
- `src/store/modules/object.js` -- Frontend generic object store

## Requirements

---

### Requirement: RegisterObjectFetcher Mapper Resolution

The `RegisterObjectFetcher` MUST resolve OpenRegister mappers for each object type by reading register and schema IDs from IAppConfig.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-001 | `getMapper()` MUST read `{objectType}_register` from IAppConfig using `getValueString()` | MUST | Implemented |
| OBJ-002 | `getMapper()` MUST read `{objectType}_schema` from IAppConfig using `getValueString()` | MUST | Implemented |
| OBJ-003 | `getMapper()` MUST convert the objectType to lowercase before config key lookup | MUST | Implemented |
| OBJ-004 | `getMapper()` MUST obtain the mapper from OpenRegister's ObjectService via `$openRegister->getMapper($register, $schema)` | MUST | Implemented |
| OBJ-005 | `getMapper()` MUST throw an Exception if register is not configured (empty string) | MUST | Implemented |
| OBJ-006 | `getMapper()` MUST throw an Exception if schema is not configured (empty string) | MUST | Implemented |
| OBJ-007 | The appName MUST be hardcoded to 'larpingapp' for IAppConfig lookups | MUST | Implemented |

#### Scenario: Resolve mapper for configured type

- GIVEN `character_register` is "reg-123" and `character_schema` is "sch-456" in IAppConfig
- WHEN `getMapper('character')` is called
- THEN it MUST call `$openRegister->getMapper('reg-123', 'sch-456')`
- AND return the resulting mapper object

#### Scenario: Resolve mapper with case-insensitive type

- GIVEN `skill_register` and `skill_schema` are configured
- WHEN `getMapper('Skill')` is called (uppercase first letter)
- THEN it MUST convert to lowercase 'skill' before config lookup
- AND MUST successfully resolve the mapper

#### Scenario: Mapper resolution fails for unconfigured register

- GIVEN `ability_register` is empty in IAppConfig
- WHEN `getMapper('ability')` is called
- THEN it MUST throw an Exception with message "Register not configured for ability"

#### Scenario: Mapper resolution fails for unconfigured schema

- GIVEN `ability_register` is "reg-123" but `ability_schema` is empty
- WHEN `getMapper('ability')` is called
- THEN it MUST throw an Exception with message "Schema not configured for ability"

---

### Requirement: OpenRegister Service Resolution

The `RegisterObjectFetcher` MUST resolve and cache the OpenRegister ObjectService from the DI container.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-010 | `getOpenRegisterService()` MUST check if OpenRegister app is installed via `IAppManager::getInstalledApps()` | MUST | Implemented |
| OBJ-011 | `getOpenRegisterService()` MUST resolve `OCA\OpenRegister\Service\ObjectService` from the DI container | MUST | Implemented |
| OBJ-012 | `getOpenRegisterService()` MUST cache the resolved service in `$openRegisterService` private property | MUST | Implemented |
| OBJ-013 | `getOpenRegisterService()` MUST return cached instance on subsequent calls | MUST | Implemented |
| OBJ-014 | `getOpenRegisterService()` MUST throw an Exception if OpenRegister is not installed | MUST | Implemented |

#### Scenario: First-time service resolution

- GIVEN OpenRegister is installed
- AND the DI container can resolve `OCA\OpenRegister\Service\ObjectService`
- WHEN `getOpenRegisterService()` is called for the first time
- THEN it MUST resolve the service from the container
- AND cache it in `$openRegisterService`
- AND return the service instance

#### Scenario: Cached service returned on second call

- GIVEN `getOpenRegisterService()` was already called successfully
- WHEN it is called again
- THEN the cached instance MUST be returned immediately
- AND the DI container MUST NOT be queried again

#### Scenario: OpenRegister not installed

- GIVEN OpenRegister is not in the installed apps list
- WHEN `getOpenRegisterService()` is called
- THEN it MUST throw an Exception with message "OpenRegister app is not installed"

---

### Requirement: Multiple Object Retrieval -- `getObjects()`

The `RegisterObjectFetcher` MUST support retrieving multiple objects with pagination, filtering, sorting, and search.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-020 | `getObjects()` MUST accept: objectType (string), limit (?int), offset (?int), filters (?array), sort (?array), search (?string) | MUST | Implemented |
| OBJ-021 | `getObjects()` MUST pass parameters through to the mapper's `findAll()` method | MUST | Implemented |
| OBJ-022 | `getObjects()` MUST convert all returned objects to arrays via `toArray()` | MUST | Implemented |
| OBJ-023 | `toArray()` MUST handle objects with `jsonSerialize()`, plain arrays, and other types | MUST | Implemented |
| OBJ-024 | Default parameter values MUST be: limit=null, offset=null, filters=[], sort=[], search=null | MUST | Implemented |

#### Scenario: Retrieve all objects of a type

- GIVEN 10 skills exist in OpenRegister
- WHEN `getObjects('skill')` is called with no parameters
- THEN all 10 skills MUST be returned as arrays
- AND each array MUST contain the skill's full data

#### Scenario: Retrieve objects with pagination

- GIVEN 50 characters exist
- WHEN `getObjects('character', limit: 10, offset: 20)` is called
- THEN the mapper's `findAll(10, 20, [], [], null)` MUST be called
- AND up to 10 objects starting from offset 20 MUST be returned

#### Scenario: Retrieve objects with search

- GIVEN abilities "Strength", "Dexterity", and "Mana" exist
- WHEN `getObjects('ability', search: 'str')` is called
- THEN the search term MUST be passed to the mapper's `findAll()`
- AND results MUST be filtered based on the search

#### Scenario: Convert jsonSerialize objects to arrays

- GIVEN the mapper returns Entity objects with `jsonSerialize()` method
- WHEN `toArray()` processes each object
- THEN `jsonSerialize()` MUST be called to convert to an array

#### Scenario: Pass through plain arrays

- GIVEN the mapper returns plain arrays (OpenRegister typically returns arrays)
- WHEN `toArray()` processes each result
- THEN the array MUST be returned unchanged

---

### Requirement: Single Object Retrieval -- `getObject()`

The `RegisterObjectFetcher` MUST support retrieving a single object by type and ID, with URI-format ID cleaning.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-030 | `getObject()` MUST accept objectType (string) and id (string) | MUST | Implemented |
| OBJ-031 | `getObject()` MUST clean URI-format IDs by extracting the last path segment | MUST | Implemented |
| OBJ-032 | URI cleaning MUST only activate when `filter_var($id, FILTER_VALIDATE_URL)` returns a valid URL | MUST | Implemented |
| OBJ-033 | `getObject()` MUST call the mapper's `find()` method with the cleaned ID | MUST | Implemented |
| OBJ-034 | `getObject()` MUST convert the result to an array via `toArray()` | MUST | Implemented |

#### Scenario: Retrieve object by simple ID

- GIVEN a character with ID "abc-123" exists
- WHEN `getObject('character', 'abc-123')` is called
- THEN the mapper's `find('abc-123')` MUST be called
- AND the character data MUST be returned as an array

#### Scenario: Retrieve object by URI-format ID

- GIVEN a skill with ID "skill-uuid" exists
- WHEN `getObject('skill', 'https://example.com/api/objects/skill/skill-uuid')` is called
- THEN the URI MUST be parsed and "skill-uuid" extracted
- AND the mapper's `find('skill-uuid')` MUST be called
- AND the skill data MUST be returned as an array

#### Scenario: Retrieve non-existent object

- GIVEN no character with ID "nonexistent" exists
- WHEN `getObject('character', 'nonexistent')` is called
- THEN the mapper's `find()` MUST throw a DoesNotExistException
- AND the exception MUST propagate to the caller

---

### Requirement: Object Array Conversion -- `toArray()`

The `toArray()` private method MUST handle multiple input types and always return an associative array.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-040 | `toArray()` MUST call `jsonSerialize()` on objects that have this method | MUST | Implemented |
| OBJ-041 | `toArray()` MUST return arrays unchanged | MUST | Implemented |
| OBJ-042 | `toArray()` MUST cast other types to array via `(array)` | MUST | Implemented |
| OBJ-043 | `toArray()` MUST accept `mixed` type parameter | MUST | Implemented |

#### Scenario: Convert Entity object

- GIVEN a Nextcloud Entity object with `jsonSerialize()` returning `['id' => 1, 'name' => 'Strength']`
- WHEN `toArray()` is called with this object
- THEN it MUST return `['id' => 1, 'name' => 'Strength']`

#### Scenario: Pass through array

- GIVEN a plain array `['id' => 'uuid', 'name' => 'Healing']`
- WHEN `toArray()` is called with this array
- THEN it MUST return the same array unchanged

#### Scenario: Cast scalar to array

- GIVEN an unexpected string value "test"
- WHEN `toArray()` is called with this value
- THEN it MUST return `['test']` via PHP's `(array)` cast

---

### Requirement: Frontend Object Store

The frontend MUST use a generic Pinia object store pattern for all entity types, communicating with the backend via HTTP.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-050 | The `object.js` store MUST provide CRUD operations for any object type | MUST | Implemented |
| OBJ-051 | List operations MUST support `_search`, `_limit`, `_offset`, `_extend`, `_order` parameters | MUST | Implemented |
| OBJ-052 | The store MUST manage per-type state: item (selected), list, auditTrails, relations, uses | MUST | Implemented |
| OBJ-053 | Search MUST be debounced to avoid excessive API calls | MUST | Implemented |
| OBJ-054 | The store MUST support OpenRegister features: audit trail, relations, uses, lock/unlock, revert | MUST | Implemented |

#### Scenario: Frontend lists objects with search

- GIVEN the user navigates to the Skills view
- WHEN they type "heal" in the search field
- THEN after debounce the store MUST call GET /api/objects/skill?_search=heal
- AND the list MUST update with filtered results

#### Scenario: Frontend creates an object

- GIVEN the user fills in a new skill form
- WHEN they click save
- THEN the store MUST POST to /api/objects/skill with the skill data
- AND the list MUST refresh after successful creation

#### Scenario: Frontend fetches object with extensions

- GIVEN a character has skills and items as UUID arrays
- WHEN the store fetches with `_extend=skills,items`
- THEN the response MUST contain full skill and item objects instead of UUIDs

#### Scenario: Frontend fetches audit trail

- GIVEN a character is selected
- WHEN the user views the Logging tab
- THEN the store MUST call GET /api/objects/character/{id}/audit
- AND display the audit trail entries

---

### Requirement: Settings Store Integration

The frontend MUST have a settings store that loads configuration from the backend and supports admin operations.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-060 | The `settings.js` store MUST load settings via GET /api/settings | MUST | Implemented |
| OBJ-061 | The settings store MUST save settings via POST /api/settings | MUST | Implemented |
| OBJ-062 | The settings store MUST expose OpenRegister availability status | MUST | Implemented |
| OBJ-063 | The settings store MUST expose admin status | MUST | Implemented |
| OBJ-064 | The settings store MUST support reimport via POST /api/settings/reimport | MUST | Implemented |

#### Scenario: Load settings on app mount

- GIVEN the app is loading
- WHEN the settings store initializes
- THEN it MUST call GET /api/settings
- AND populate objectTypes, openRegisters, isAdmin, and configuration

#### Scenario: Save settings

- GIVEN the admin changes configuration values
- WHEN they click Save All
- THEN the store MUST POST the configuration to /api/settings
- AND display success/error feedback

#### Scenario: Reimport configuration

- GIVEN the admin clicks the reimport button
- WHEN the store calls POST /api/settings/reimport
- THEN the backend MUST re-import from JSON
- AND the store MUST reload settings to reflect changes

---

### Requirement: Routes Configuration

All API routes MUST be defined in `appinfo/routes.php` with appropriate HTTP verbs and URL patterns.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-070 | Dashboard page route MUST be defined as `dashboard#page` with URL `/` and verb GET | MUST | Implemented |
| OBJ-071 | PDF download route MUST be defined as `characters#downloadPdf` with URL `/characters/{id}/download/{template}` | MUST | Implemented |
| OBJ-072 | Settings routes MUST include GET and POST for `/api/settings` | MUST | Implemented |
| OBJ-073 | Settings reimport route MUST be defined as POST `/api/settings/reimport` | MUST | Implemented |
| OBJ-074 | No generic object CRUD routes exist in routes.php -- entity CRUD is handled by the frontend object store communicating via OpenRegister's API | MUST | Implemented |

#### Scenario: Route resolution for dashboard

- GIVEN a GET request to `/apps/larpingapp/`
- WHEN Nextcloud routes the request
- THEN `DashboardController::page()` MUST handle it

#### Scenario: Route resolution for PDF download

- GIVEN a GET request to `/apps/larpingapp/characters/uuid-123/download/template-456`
- WHEN Nextcloud routes the request
- THEN `CharactersController::downloadPdf('uuid-123', 'template-456')` MUST handle it

#### Scenario: No generic object routes

- GIVEN a GET request to `/apps/larpingapp/api/objects/skill`
- WHEN Nextcloud routes the request
- THEN no LarpingApp route MUST match
- AND the request MUST be handled by OpenRegister's routing (or return 404)

---

## Method Signatures

### RegisterObjectFetcher

```php
public function getObjects(
    string $objectType,
    ?int $limit = null,
    ?int $offset = null,
    ?array $filters = [],
    ?array $sort = [],
    ?string $search = null
): array

public function getObject(string $objectType, string $id): array

private function getMapper(string $objectType): object
private function getOpenRegisterService(): object
private function toArray(mixed $object): array
```

## Known Issues

1. **No internal mapper fallback**: Unlike the previous ObjectService, RegisterObjectFetcher only works with OpenRegister. If OpenRegister is not installed or a type is not configured, all data operations will fail with exceptions.

2. **No entity extension**: RegisterObjectFetcher does not support the `_extend` parameter for resolving UUID references to full objects. Extension must be handled by OpenRegister's mapper directly or by the frontend.

3. **Constructor preload in CharacterService**: CharacterService calls `loadAllEntities()` in its constructor, which fetches ALL skills, items, conditions, events, effects, and abilities from OpenRegister. This could be a performance issue for large datasets.

4. **Legacy internal mappers still exist**: The `lib/Db/` directory still contains all 10 entity/mapper pairs (Ability, Character, Condition, Effect, Event, Item, Player, Setting, Skill, Template) but they are no longer used by RegisterObjectFetcher. They remain as dead code.

## Dependencies

- **OpenRegister ObjectService**: Required dependency for all data operations (resolved via DI container)
- **IAppConfig**: Per-type register/schema configuration storage
- **IAppManager**: Checking if OpenRegister app is installed
- **ContainerInterface**: DI container for resolving OpenRegister service
- **CharacterService**: Primary consumer of RegisterObjectFetcher for stat calculation entity preloading
- **CharactersController**: Uses RegisterObjectFetcher for character retrieval in PDF export
