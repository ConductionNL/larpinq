---
status: reviewed
---

# Search Service

## Purpose

Provides federated search capabilities inherited from the OpenCatalogi codebase. The `SearchService` class (`lib/Service/SearchService.php`) contains methods for executing distributed searches across multiple catalog instances using async HTTP requests, merging faceted aggregation results, and constructing database query filters for both MongoDB and MySQL backends. It also includes a custom query string parser that supports nested bracket syntax.

**Important: This service is largely dead/inherited code from OpenCatalogi.** It was carried over during the fork of LarpingApp from the OpenCatalogi project. The `search()` method references services that do not exist in LarpingApp (`$this->elasticService`, `$this->directoryService`), making it non-functional. The utility methods for MongoDB/MySQL filter construction and query string parsing are standalone and functional, but are not called from anywhere in the LarpingApp codebase -- the app uses `ObjectService.getResultArrayForRequest()` for all search operations instead.

**Key source file:** `lib/Service/SearchService.php`

## Requirements

### Federated Search (Dead Code)

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-001 | The `search()` method MUST accept parameters, elasticConfig, dbConfig, and optional catalogi array | MUST | Dead Code |
| SRCH-002 | The `search()` method MUST query the local elastic search index first (if configured) | MUST | Dead Code |
| SRCH-003 | The `search()` method MUST discover remote catalog instances via `$this->directoryService->listDirectory()` | MUST | Dead Code |
| SRCH-004 | The `search()` method MUST execute async Guzzle GET requests to all remote search endpoints | MUST | Dead Code |
| SRCH-005 | The `search()` method MUST merge remote results into local results, sorted by `_score` | MUST | Dead Code |
| SRCH-006 | The `search()` method MUST merge facet aggregations from all sources | MUST | Dead Code |
| SRCH-007 | The `search()` method MUST return a response envelope with `results`, `facets`, `count`, `limit`, `page`, `pages`, `total` | MUST | Dead Code |
| SRCH-008 | Remote requests MUST use Guzzle's `Utils::settle()` for concurrent promise resolution | MUST | Dead Code |

### Facet/Aggregation Merging

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-010 | `mergeFacets()` MUST combine two aggregation arrays by `_id` key, summing their `count` values | MUST | Implemented |
| SRCH-011 | `mergeFacets()` MUST handle non-overlapping facet entries (entries present in only one source) | MUST | Implemented |
| SRCH-012 | `mergeAggregations()` MUST merge named aggregation groups, delegating per-group merging to `mergeFacets()` | MUST | Implemented |
| SRCH-013 | `mergeAggregations()` MUST return an empty array if `$newAggregations` is null | MUST | Implemented |

### Result Sorting

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-020 | `sortResultArray()` MUST compare two result arrays by their `_score` field using the spaceship operator | MUST | Implemented |
| SRCH-021 | `sortResultArray()` MUST be usable as a callback for `usort()` | MUST | Implemented |

### MongoDB Filter Construction

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-030 | `createMongoDBSearchFilter()` MUST convert `_search` filter to a `$regex` query with case-insensitive `$options: 'i'` | MUST | Implemented |
| SRCH-031 | `createMongoDBSearchFilter()` MUST generate `$or` conditions across all provided `$fieldsToSearch` | MUST | Implemented |
| SRCH-032 | `createMongoDBSearchFilter()` MUST unset the `_search` key from filters after conversion | MUST | Implemented |
| SRCH-033 | `createMongoDBSearchFilter()` MUST convert `'IS NOT NULL'` filter values to `['$ne' => null]` | MUST | Implemented |
| SRCH-034 | `createMongoDBSearchFilter()` MUST convert `'IS NULL'` filter values to `['$eq' => null]` | MUST | Implemented |

### MySQL Search Conditions

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-040 | `createMySQLSearchConditions()` MUST generate `LOWER(field) LIKE :search` SQL conditions for each field in `$fieldsToSearch` | MUST | Implemented |
| SRCH-041 | `createMySQLSearchConditions()` MUST only generate conditions when `_search` is present in filters | MUST | Implemented |
| SRCH-042 | `createMySQLSearchParams()` MUST create a `search` parameter with `%lowercased_value%` wrapping for SQL LIKE queries | MUST | Implemented |
| SRCH-043 | `createMySQLSearchParams()` MUST only create parameters when `_search` is present in filters | MUST | Implemented |

### Special Query Parameter Handling

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-050 | `unsetSpecialQueryParams()` MUST remove all filter keys starting with `_` (underscore) | MUST | Implemented |
| SRCH-051 | `unsetSpecialQueryParams()` MUST return the remaining filters without underscore-prefixed keys | MUST | Implemented |

### Sort Construction

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-060 | `createSortForMySQL()` MUST parse `_order` filter array into `[field => 'ASC'|'DESC']` format | MUST | Implemented |
| SRCH-061 | `createSortForMySQL()` MUST default direction to `ASC` for non-DESC values | MUST | Implemented |
| SRCH-062 | `createSortForMongoDB()` MUST parse `_order` filter array into `[field => 1|-1]` format (1=ASC, -1=DESC) | MUST | Implemented |
| SRCH-063 | `createSortForMongoDB()` is marked `@todo Not functional yet` in code docblock | SHOULD | Bug |

### Query String Parsing

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-070 | `parseQueryString()` MUST parse a URL query string into an associative array | MUST | Implemented |
| SRCH-071 | `parseQueryString()` MUST support nested bracket syntax (e.g., `param[key1][key2]=value`) via recursive parsing | MUST | Implemented |
| SRCH-072 | `parseQueryString()` MUST support array-append syntax (e.g., `param[key][]`) by pushing values onto arrays | MUST | Implemented |
| SRCH-073 | `parseQueryString()` MUST URL-decode both keys and values | MUST | Implemented |
| SRCH-074 | `parseQueryString()` delegates nested parsing to `recursiveRequestQueryKey()` which uses regex matching on bracket patterns | MUST | Implemented |
| SRCH-075 | `parseQueryString()` has a bug: `$vars` is used uninitialized (no `$vars = []` before the loop), relying on PHP implicit array creation | SHOULD | Bug |

## Method Signatures

### `mergeFacets(array $existingAggregation, array $newAggregation): array`
- **Visibility**: public
- **Purpose**: Merges two arrays of facet results (each element has `_id` and `count`) by summing counts for matching `_id` values
- **Note**: Uses `array_diff` to find non-overlapping entries, which may produce unexpected results when counts are identical between sources (since `array_diff` compares values, not keys)

### `mergeAggregations(?array $existingAggregations, ?array $newAggregations): array`
- **Visibility**: private
- **Purpose**: Iterates over named aggregation groups and merges each group via `mergeFacets()`

### `sortResultArray(array $a, array $b): int`
- **Visibility**: public
- **Purpose**: Comparison function for `usort()` that sorts result arrays by `_score` ascending

### `search(array $parameters, array $elasticConfig, array $dbConfig, array $catalogi = []): array`
- **Visibility**: public
- **Purpose**: Federated search across local and remote catalogs
- **Status**: Dead code -- references non-existent `$this->elasticService` and `$this->directoryService`
- **Returns**: `{results, facets, count, limit, page, pages, total}`

### `recursiveRequestQueryKey(array &$vars, string $name, string $nameKey, string $value): void`
- **Visibility**: private
- **Purpose**: Recursively parses bracket notation in query parameter names into nested array structures

### `createMongoDBSearchFilter(array $filters, array $fieldsToSearch): array`
- **Visibility**: public
- **Purpose**: Converts a `_search` text filter into MongoDB regex `$or` conditions; handles IS NULL/IS NOT NULL

### `createMySQLSearchConditions(array $filters, array $fieldsToSearch): array`
- **Visibility**: public
- **Purpose**: Generates SQL `LOWER(field) LIKE :search` conditions for text search

### `unsetSpecialQueryParams(array $filters): array`
- **Visibility**: public
- **Purpose**: Strips all underscore-prefixed special query parameters from filter array

### `createMySQLSearchParams(array $filters): array`
- **Visibility**: public
- **Purpose**: Creates the `search` named parameter with `%value%` wildcard wrapping

### `createSortForMySQL(array $filters): array`
- **Visibility**: public
- **Purpose**: Parses `_order` into `[field => 'ASC'|'DESC']` for MySQL ORDER BY

### `createSortForMongoDB(array $filters): array`
- **Visibility**: public
- **Purpose**: Parses `_order` into `[field => 1|-1]` for MongoDB sort

### `parseQueryString(string $queryString = ''): array`
- **Visibility**: public
- **Purpose**: Manual query string parser supporting nested bracket parameters

## Known Issues

1. **Dead code**: The `search()` method references `$this->elasticService` and `$this->directoryService` which are not injected via constructor and do not exist as class properties. This method would throw a fatal error if called.
2. **OpenCatalogi heritage**: This entire service was inherited from the OpenCatalogi project. The federated search pattern (querying remote catalog instances) is specific to OpenCatalogi's distributed catalog architecture and has no relevance to LarpingApp's LARP management use case.
3. **Incorrect method end comments**: Several methods have `//end createMongoDBSearchFilter()` as their closing comment regardless of which method they actually close (e.g., `createMySQLSearchConditions`, `unsetSpecialQueryParams`, `createMySQLSearchParams`).
4. **Uninitialized variable**: `parseQueryString()` uses `$vars` without initializing it to `[]` first.
5. **mergeFacets() logic issue**: Uses `array_diff` on associative arrays of counts, which compares values. When two sources have the same count for different `_id` values, entries may be incorrectly excluded from the result.
6. **No integration**: None of the SearchService methods are called from any controller, service, or route in LarpingApp. All search functionality goes through `ObjectService.getResultArrayForRequest()`.

## Dependencies

- **GuzzleHttp\Client**: HTTP client for async federated search requests
- **GuzzleHttp\Promise\Utils**: Promise settlement for concurrent request handling
- **OCP\IURLGenerator**: URL generation for identifying local search endpoints
- **Symfony\Component\Uid\Uuid**: UUID generation (imported but not used in any method)
