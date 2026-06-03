# Object Service (RegisterObjectFetcher)

## Overview

The data access layer for LarpingApp uses `RegisterObjectFetcher` as a thin wrapper around OpenRegister's `ObjectService`. It resolves register and schema IDs from `IAppConfig` per object type.

## Features

- **Mapper resolution**: Reads `{type}_register` and `{type}_schema` from IAppConfig
- **Case-insensitive lookup**: Converts object type to lowercase before config key lookup
- **Object retrieval**: `getObjects()` for lists with pagination/filtering, `getObject()` for single items
- **URI cleaning**: Strips URL prefixes from IDs (e.g., `https://example.com/api/objects/abc-123` becomes `abc-123`)
- **Serialization**: `toArray()` supports JsonSerializable objects, arrays, and object casting
- **Service caching**: OpenRegister ObjectService resolved once and cached

## Error Handling

- Throws `Exception` if OpenRegister is not installed
- Throws `Exception` if register or schema is not configured for the requested type

## Technical Details

- Service: `lib/Service/RegisterObjectFetcher.php`
- Dependencies: `ContainerInterface`, `IAppManager`, `IAppConfig`
- App name hardcoded to `larpingapp` for config lookups
- Consumed by: `CharacterService`, `CharactersController`
