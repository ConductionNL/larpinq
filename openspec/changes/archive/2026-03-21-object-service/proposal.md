# Object Service

## Problem
The data access layer for LarpingApp has been refactored from a single monolithic `ObjectService` to a thin, focused `RegisterObjectFetcher` (`lib/Service/RegisterObjectFetcher.php`). This service provides object retrieval from OpenRegister by resolving register and schema IDs from IAppConfig per object type. It replaces the previous generic CRUD dispatch pattern (internal mappers vs OpenRegister) with direct cross-app calls to OpenRegister's ObjectService. The `CharacterService` uses `RegisterObjectFetcher` to load entities for stat calculation, and the `CharactersController` uses it to fetch character data for PDF export.
**Key source files:**
- `lib/Service/RegisterObjectFetcher.php` -- Main data access service
- `lib/Service/CharacterService.php` -- Consumes RegisterObjectFetcher for entity preloading
- `lib/Controller/CharactersController.php` -- Uses RegisterObjectFetcher for character retrieval
- `src/store/modules/object.js` -- Frontend generic object store

## Proposed Solution
Implement Object Service following the detailed specification. Key requirements include:
- Requirement: RegisterObjectFetcher Mapper Resolution
- Requirement: OpenRegister Service Resolution
- Requirement: Multiple Object Retrieval -- `getObjects()`
- Requirement: Single Object Retrieval -- `getObject()`
- Requirement: Object Array Conversion -- `toArray()`

## Scope
This change covers all requirements defined in the object-service specification.

## Success Criteria
- Resolve mapper for configured type
- Resolve mapper with case-insensitive type
- Mapper resolution fails for unconfigured register
- Mapper resolution fails for unconfigured schema
- First-time service resolution
