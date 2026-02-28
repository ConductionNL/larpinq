# Object Service

## Purpose

The `ObjectService` (`lib/Service/ObjectService.php`) is the central data access layer for LarpingApp. It provides a generic CRUD interface for all 10 entity types (ability, character, condition, effect, event, item, player, setting, skill, template) by dispatching operations to the appropriate mapper -- either an internal Nextcloud QBMapper or an OpenRegister mapper, based on per-type configuration stored in IAppConfig. It also provides entity extension (resolving UUID references to full objects), request parameter parsing, faceted search result envelopes, audit trail retrieval, locking, unlocking, reverting, and file access.

**Key source file:** `lib/Service/ObjectService.php`

## Requirements

### Mapper Dispatch

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-001 | `getMapper()` MUST read `{objectType}_source` from IAppConfig to determine data source ("internal" or "openregister") | MUST | Implemented |
| OBJ-002 | When source is "openregister", `getMapper()` MUST obtain a mapper from OpenRegister service using configured register and schema | MUST | Implemented |
| OBJ-003 | When source is "internal", `getMapper()` MUST return the appropriate QBMapper via a `match` statement covering all 10 types | MUST | Implemented |
| OBJ-004 | `getMapper()` MUST throw an Exception if OpenRegister source is configured but the service is unavailable | MUST | Implemented |
| OBJ-005 | `getMapper()` MUST throw an Exception if register or schema is not configured when using OpenRegister source | MUST | Implemented |
| OBJ-006 | `getMapper()` MUST throw an InvalidArgumentException for unknown object types | MUST | Implemented |

### Object Retrieval -- `getObjects()`

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-010 | `getObjects()` MUST accept parameters: objectType (string), limit (?int), offset (?int), filters (?array), sort (?array), search (?string), extend (?array) | MUST | Implemented |
| OBJ-011 | `getObjects()` MUST pass all parameters through to the mapper's `findAll()` method using named arguments | MUST | Implemented |
| OBJ-012 | `getObjects()` MUST convert entity objects to arrays using `jsonSerialize()` | MUST | Implemented |
| OBJ-013 | `getObjects()` MUST pass through arrays unchanged (when mapper already returns arrays, as OpenRegister does) | MUST | Implemented |
| OBJ-014 | `getObjects()` MUST throw InvalidArgumentException if extend is requested for non-OpenRegister mappers | MUST | Implemented |

### Single Object Retrieval -- `getObject()`

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-020 | `getObject()` MUST accept objectType, id, and optional extend array | MUST | Implemented |
| OBJ-021 | `getObject()` MUST clean up URI-format IDs by extracting the last path segment | MUST | Implemented |
| OBJ-022 | `getObject()` MUST convert objects with `jsonSerialize()` to arrays before returning | MUST | Implemented |
| OBJ-023 | `getObject()` MUST throw InvalidArgumentException if extend is requested for non-OpenRegister objects | MUST | Implemented |

### Faceted Results -- `getFacets()`

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-030 | `getFacets()` MUST return aggregations from OpenRegister mapper via `getAggregations()` | MUST | Implemented |
| OBJ-031 | `getFacets()` MUST return an empty array for internal (non-OpenRegister) mappers | MUST | Implemented |

### Result Array Envelope -- `getResultArrayForRequest()`

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-040 | `getResultArrayForRequest()` MUST parse request parameters supporting both prefixed and unprefixed aliases: `limit`/`_limit`, `offset`/`_offset`, `order`/`_order`, `extend`/`_extend`, `page`/`_page`, `search`/`_search` | MUST | Implemented |
| OBJ-041 | When `page` is set and `limit` is present, `getResultArrayForRequest()` MUST calculate offset as `limit * (page - 1)` | MUST | Implemented |
| OBJ-042 | `getResultArrayForRequest()` MUST convert string order/extend values to arrays by splitting on commas | MUST | Implemented |
| OBJ-043 | `getResultArrayForRequest()` MUST strip internal parameters (`_route`, `_extend`, `_limit`, `_offset`, `_order`, `_page`, and their unprefixed equivalents) from filters before querying | MUST | Implemented |
| OBJ-044 | `getResultArrayForRequest()` MUST return a response envelope with `results`, `facets`, and `total` keys | MUST | Implemented |
| OBJ-045 | `getResultArrayForRequest()` MUST call `getCount()` for the total, which delegates to the mapper's `count()` for OpenRegister or returns 0 for internal mappers | MUST | Implemented |

### Bulk Retrieval -- `getMultipleObjects()`

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-050 | `getMultipleObjects()` MUST accept an array of IDs in multiple formats: raw IDs, objects with `getId()`, or arrays with `id` key | MUST | Implemented |
| OBJ-051 | `getMultipleObjects()` MUST clean URI-format IDs by extracting the last path segment | MUST | Implemented |
| OBJ-052 | `getMultipleObjects()` MUST delegate to the mapper's `findMultiple()` method with cleaned IDs | MUST | Implemented |

### Simple Retrieval -- `getAllObjects()`

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-060 | `getAllObjects()` MUST accept objectType, optional limit, and optional offset | MUST | Implemented |
| OBJ-061 | `getAllObjects()` MUST call the mapper's `findAll()` with only limit and offset (no filters, sort, search, or extend) | MUST | Implemented |

### Entity Extension -- `extendEntity()`

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-070 | `extendEntity()` MUST resolve UUID references in entity properties to full objects | MUST | Implemented |
| OBJ-071 | When extend contains `'all'`, `extendEntity()` MUST attempt to extend all keys of the entity, suppressing mapper errors for keys without corresponding object types | MUST | Implemented |
| OBJ-072 | `extendEntity()` MUST resolve mappers using both plural and singular property names (e.g., try `skills` then `skill`) | MUST | Implemented |
| OBJ-073 | For array values, `extendEntity()` MUST call `getMultipleObjects()` for bulk resolution | MUST | Implemented |
| OBJ-074 | For scalar values, `extendEntity()` MUST call `getObject()` for single resolution | MUST | Implemented |
| OBJ-075 | `extendEntity()` MUST throw Exception if property is not found in entity and suppress mode is not active | MUST | Implemented |

### Object Persistence -- `saveObject()` and `deleteObject()`

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-080 | `saveObject()` MUST create a new object (via `createFromArray`) when no `id` is present in the data | MUST | Implemented |
| OBJ-081 | `saveObject()` MUST update an existing object (via `updateFromArray`) when `id` is present | MUST | Implemented |
| OBJ-082 | `saveObject()` MUST pass extend array and updateVersion flag through to mapper methods | MUST | Implemented |
| OBJ-083 | `deleteObject()` MUST find the object by ID, then delete it via the mapper | MUST | Implemented |
| OBJ-084 | `deleteObject()` MUST return false (not throw) if the object cannot be found or deleted | MUST | Implemented |

### File Access -- `getFiles()`

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-090 | `getFiles()` MUST delegate to the mapper's `getFiles()` and `formatFiles()` methods | MUST | Implemented |
| OBJ-091 | `getFiles()` has incorrect annotations (`@NoAdminRequired`, `@NoCSRFRequired`, `@return JSONResponse`) that belong on a controller, not a service method | SHOULD | Bug |
| OBJ-092 | A route exists for `getFiles` (`api/objects/{objectType}/{id}/files`) but the ObjectsController does NOT have a `getFiles()` method -- the route maps to the service directly which is incorrect | MUST | Bug |

### Lock/Unlock/IsLocked

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-100 | `lockObject()` MUST delegate to mapper's `lockObject()` with id, optional process, and duration (default 3600s) | MUST | Implemented |
| OBJ-101 | `unlockObject()` MUST delegate to mapper's `unlockObject()` | MUST | Implemented |
| OBJ-102 | `isLocked()` MUST delegate to mapper's `isLocked()` and return a boolean | MUST | Implemented |
| OBJ-103 | `isLocked()` exists on both ObjectService and ObjectsController but there is NO route defined for it in `appinfo/routes.php` | MUST | Dead Code |

### Revert

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-110 | `revertObject()` MUST delegate to mapper's `revertObject()` with id, until (DateTime or audit trail ID), and overwriteVersion flag | MUST | Implemented |

### Audit Trails, Relations, Uses

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-120 | `getAuditTrail()` MUST delegate to mapper's `getAuditTrail()` | MUST | Implemented |
| OBJ-121 | `getRelations()` MUST delegate to mapper's `getRelations()` | MUST | Implemented |
| OBJ-122 | `getUses()` MUST delegate to mapper's `getUses()` | MUST | Implemented |

### OpenRegister Detection

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-130 | `getOpenRegisters()` MUST check if OpenRegister app is installed via `IAppManager::getInstalledApps()` | MUST | Implemented |
| OBJ-131 | `getOpenRegisters()` MUST return the OpenRegister ObjectService from the DI container if available | MUST | Implemented |
| OBJ-132 | `getOpenRegisters()` MUST return null if OpenRegister is not installed or the service cannot be obtained | MUST | Implemented |

## Constructor and Dependency Injection

### Constructor Type-Hint Bugs

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| OBJ-140 | Constructor type-hints `IContainer` for the `$container` parameter, but the actual Nextcloud DI container implements `Psr\Container\ContainerInterface` -- `IContainer` is the deprecated Nextcloud interface | MUST | Bug |
| OBJ-141 | Constructor type-hints `IConfig` for the `$config` parameter, but the code calls `getValueString()` which is an `IAppConfig` method, not `IConfig` | MUST | Bug |
| OBJ-142 | The PHPDoc says `@param IContainer $container` and `@param IConfig $config` but the actual type-hints in the constructor signature reference non-imported or deprecated classes | MUST | Bug |
| OBJ-143 | Despite the incorrect type-hints, DI auto-wiring may still inject the correct implementations at runtime, so this bug may not cause runtime failures in all environments | SHOULD | Bug |

## Method Signatures

### `getObjects(string $objectType, ?int $limit, ?int $offset, ?array $filters, ?array $sort, ?string $search, ?array $extend): array`
Retrieves multiple objects with full filtering, sorting, search, and extension support.

### `getObject(string $objectType, string $id, array $extend = []): mixed`
Retrieves a single object by ID with optional extension. Cleans URI-format IDs.

### `getFacets(string $objectType, array $filters = []): array`
Returns facet aggregations for a query. Only works with OpenRegister mappers.

### `getMultipleObjects(string $objectType, array $ids): array`
Bulk retrieval by an array of IDs. Supports object/array/scalar ID formats and URI cleaning.

### `getAllObjects(string $objectType, ?int $limit, ?int $offset): array`
Simplified retrieval without filters/sort/search/extend. Passes limit and offset directly.

### `getResultArrayForRequest(string $objectType, array $requestParams): array`
Parses HTTP request parameters and returns a structured response envelope `{results, facets, total}`.

### `extendEntity(mixed $entity, array $extend): array`
Resolves UUID references on an entity into full sub-objects. Supports `'all'` keyword.

### `saveObject(string $objectType, array $object, array $extend = [], bool $updateVersion = true): mixed`
Creates or updates an object based on presence of `id` key.

### `deleteObject(string $objectType, string|int $id): bool`
Deletes an object by type and ID. Returns false on failure.

### `getFiles(string $objectType, string $id): array`
Returns formatted files for an object. Has misplaced controller annotations.

### `isLocked(string $objectType, string|int $id): bool`
Checks lock status. Method exists but has no corresponding route (dead code path).

### `lockObject(string $objectType, string|int $id, ?string $process, ?int $duration): mixed`
Acquires a lock on an object.

### `unlockObject(string $objectType, string|int $id): mixed`
Releases a lock on an object.

### `revertObject(string $objectType, string|int $id, $until, bool $overwriteVersion): mixed`
Reverts an object to a previous state.

### `getAuditTrail(string $objectType, string $id): array`
Returns the audit trail for an object.

### `getRelations(string $objectType, string $id): array`
Returns relations for an object.

### `getUses(string $objectType, string $id): array`
Returns uses for an object.

### `getOpenRegisters(): ?ObjectService`
Checks for OpenRegister availability and returns its service.

## Known Issues

1. **Constructor type-hint bugs (Gap 23)**: The constructor declares `private IContainer $container` and `private IConfig $config`, but `IContainer` is deprecated and `IConfig` does not have the `getValueString()` method used in `getMapper()`. The correct types should be `ContainerInterface` (from PSR-11) and `IAppConfig`. The code still works at runtime due to Nextcloud's DI auto-wiring, but it is technically incorrect and could break in future Nextcloud versions.

2. **`getFiles()` misplaced annotations (Gap 8)**: The `getFiles()` method has `@NoAdminRequired`, `@NoCSRFRequired`, and `@return JSONResponse` annotations which are controller annotations, not service annotations. The route `api/objects/{objectType}/{id}/files` exists in `routes.php` but the `ObjectsController` does NOT implement a `getFiles()` method. The route name `objects#getFiles` would map to `ObjectsController::getFiles()` which does not exist, meaning this route currently results in a method not found error. The actual implementation is only on `ObjectService`.

3. **`isLocked()` dead code (Gaps 9-10)**: Both `ObjectService::isLocked()` and `ObjectsController::isLocked()` exist as methods, but there is no route defined in `appinfo/routes.php` for the `isLocked` action. The method can never be reached via HTTP. Lock and unlock have routes, but checking lock status does not.

4. **Internal mapper `findAll()` signature mismatch**: `ObjectService.getObjects()` calls `findAll()` with named parameters `limit`, `offset`, `filters`, `sort`, `search`, `extend`. However, internal mappers have inconsistent signatures: `AbilityMapper.findAll(string $userId)`, `CharacterMapper.findAll(string $userId)`, `EffectMapper.findAll(string $userId)` take only a userId; `EventMapper.findAll(?int $limit, ?int $offset, ?array $filters, ?array $searchConditions, ?array $searchParams)` and `SkillMapper.findAll(...)` take search-related params but not `sort`, `search`, or `extend`; `ItemMapper.findAll()` takes no parameters. This means calling `getObjects()` in internal mode would fail for most entity types.

5. **`getCount()` returns 0 for internal mappers**: The `getCount()` private method only returns a count for OpenRegister mappers. For internal mappers, it always returns 0, meaning the `total` field in the response envelope is always 0 in internal mode.

## Scenarios

### Retrieve objects with pagination

```
GIVEN object type "skill" is configured for internal storage
WHEN a client calls GET /api/objects/skill?_limit=10&_offset=20
THEN getResultArrayForRequest() parses limit=10, offset=20
AND getObjects() calls SkillMapper.findAll(limit: 10, offset: 20, ...)
AND the response contains {results: [...], facets: [], total: 0}
```

### Extend entity references

```
GIVEN a character object has skills: ["uuid-1", "uuid-2"]
WHEN extendEntity() is called with extend: ["skills"]
THEN it resolves "skills" to singular "skill" for mapper lookup
AND calls getMultipleObjects("skill", ["uuid-1", "uuid-2"])
AND replaces the skills array with full skill objects
```

### URI-format ID cleaning

```
GIVEN an ID value of "https://example.com/api/objects/skill/abc-123"
WHEN getObject() or getMultipleObjects() processes this ID
THEN it extracts "abc-123" as the actual ID
AND passes "abc-123" to the mapper's find() method
```

## Dependencies

- **All 10 entity mappers**: AbilityMapper, CharacterMapper, ConditionMapper, EffectMapper, EventMapper, ItemMapper, PlayerMapper, SettingMapper, SkillMapper, TemplateMapper
- **IContainer / ContainerInterface**: DI container for obtaining OpenRegister service
- **IAppManager**: Checking if OpenRegister app is installed
- **IAppConfig / IConfig**: Reading per-type data source configuration
- **OpenRegister ObjectService**: Obtaining register/schema-specific mappers when configured
