# Search Service (Removed)

## Overview

The `SearchService` was inherited dead code from OpenCatalogi that was carried over during the fork. It has been removed from the codebase.

## Status

**Removed** — The `SearchService.php` file no longer exists in the codebase.

## Reason for Removal

- `search()` method referenced `$this->elasticService` and `$this->directoryService` which did not exist in LarpingApp
- No controller, route, or service called any SearchService method
- All actual search functionality uses the frontend object store with OpenRegister's API parameters (`_search`, `_limit`, `_offset`, `_order`)

## Current Search Implementation

Search in LarpingApp is handled entirely through:
1. The frontend object store (`createObjectStore` from `@conduction/nextcloud-vue`)
2. OpenRegister's API endpoints with query parameters
3. The `CnIndexSidebar` component for search UI and faceted filtering
