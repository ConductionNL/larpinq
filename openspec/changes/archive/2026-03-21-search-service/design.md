---
status: approved
---

# Search Service — Design

## Architecture

The `SearchService` is inherited dead code from OpenCatalogi. It was carried over during the fork but is non-functional in LarpingApp.

## Status: Dead Code

- `search()` method references `$this->elasticService` and `$this->directoryService` which do not exist
- No controller, route, or service calls any SearchService method
- The file itself (`lib/Service/SearchService.php`) has been deleted from the codebase
- All actual search functionality goes through the frontend object store, which uses OpenRegister's API parameters (`_search`, `_limit`, `_offset`, `_order`)

## Decision

The SearchService file has been removed from the codebase as dead code. This spec documents its existence for historical reference. No implementation work is needed beyond documenting and archiving.
