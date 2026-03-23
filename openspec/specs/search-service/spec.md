---
status: implemented
---

# Search Service

## Purpose

The `SearchService` class (`lib/Service/SearchService.php`) contains methods for executing distributed searches, merging faceted aggregation results, constructing database query filters for MongoDB and MySQL backends, and parsing custom query strings with nested bracket syntax.

**Important: This service is largely dead/inherited code from OpenCatalogi.** It was carried over during the fork of LarpingApp from the OpenCatalogi project. The `search()` method references services that do not exist in LarpingApp (`$this->elasticService`, `$this->directoryService`), making it non-functional. The utility methods for MongoDB/MySQL filter construction and query string parsing are standalone and functional, but are not called from anywhere in the LarpingApp codebase. All actual search functionality in LarpingApp goes through the frontend object store, which communicates with OpenRegister's API using `_search`, `_limit`, `_offset`, and `_order` parameters.

**Key source file:** `lib/Service/SearchService.php`

## Requirements

---

### Requirement: Federated Search (Dead Code)

The federated search method is inherited from OpenCatalogi and is non-functional in LarpingApp.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-001 | The `search()` method MUST accept parameters, elasticConfig, dbConfig, and optional catalogi array | MUST | Dead Code |
| SRCH-002 | The `search()` method references `$this->elasticService` which does NOT exist as a class property or constructor parameter | MUST | Dead Code |
| SRCH-003 | The `search()` method references `$this->directoryService` which does NOT exist as a class property or constructor parameter | MUST | Dead Code |
| SRCH-004 | The `search()` method MUST execute async Guzzle GET requests to remote search endpoints | MUST | Dead Code |
| SRCH-005 | The `search()` method MUST merge remote results into local results sorted by `_score` | MUST | Dead Code |
| SRCH-006 | The `search()` method MUST merge facet aggregations from all sources | MUST | Dead Code |
| SRCH-007 | The `search()` method MUST return a response envelope with `results`, `facets`, `count`, `limit`, `page`, `pages`, `total` | MUST | Dead Code |
| SRCH-008 | Remote requests MUST use Guzzle's `Utils::settle()` for concurrent promise resolution | MUST | Dead Code |
| SRCH-009 | This entire method is specific to OpenCatalogi's distributed catalog architecture and has no relevance to LarpingApp | SHOULD | Dead Code |
| SRCH-010 | No controller, route, or other service calls `search()` anywhere in the LarpingApp codebase | MUST | Dead Code |

#### Scenario: Calling search() causes fatal error

- GIVEN the SearchService is instantiated
- WHEN `search()` is called with any parameters
- THEN a fatal error MUST occur because `$this->elasticService` does not exist
- AND the method MUST NOT complete execution

#### Scenario: Dead code identification

- GIVEN the LarpingApp codebase is searched for references to SearchService methods
- WHEN all controllers, services, and routes are examined
- THEN NO reference to `SearchService::search()` MUST be found
- AND the method MUST be identified as dead code

#### Scenario: Federated search concept inapplicable

- GIVEN LarpingApp manages LARP characters, skills, items, etc. in a single instance
- WHEN considering the federated search pattern (querying remote catalog instances)
- THEN the pattern MUST be identified as OpenCatalogi-specific
- AND MUST NOT be needed for LarpingApp's use case

---

### Requirement: Facet/Aggregation Merging

The facet merging utilities are standalone and functional, though not called from LarpingApp code.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-020 | `mergeFacets()` MUST combine two aggregation arrays by `_id` key, summing their `count` values | MUST | Implemented |
| SRCH-021 | `mergeFacets()` MUST handle non-overlapping facet entries (present in only one source) | MUST | Implemented |
| SRCH-022 | `mergeAggregations()` MUST merge named aggregation groups, delegating per-group merging to `mergeFacets()` | MUST | Implemented |
| SRCH-023 | `mergeAggregations()` MUST return an empty array if `$newAggregations` is null | MUST | Implemented |
| SRCH-024 | `mergeFacets()` uses `array_diff` which may produce unexpected results when counts are identical | SHOULD | Bug |
| SRCH-025 | Neither `mergeFacets()` nor `mergeAggregations()` is called from any LarpingApp code | SHOULD | Dead Code |

#### Scenario: Merge overlapping facets

- GIVEN existing facets `[{_id: "skill", count: 5}, {_id: "item", count: 3}]`
- AND new facets `[{_id: "skill", count: 2}, {_id: "event", count: 1}]`
- WHEN `mergeFacets()` is called
- THEN the result MUST include `{_id: "skill", count: 7}` (5+2)
- AND `{_id: "item", count: 3}` (only in existing)
- AND `{_id: "event", count: 1}` (only in new)

#### Scenario: Merge with null new aggregations

- GIVEN existing aggregations with 2 groups
- AND new aggregations is null
- WHEN `mergeAggregations()` is called
- THEN the result MUST be an empty array

#### Scenario: array_diff bug in mergeFacets

- GIVEN two facet arrays with identical count values for different _id keys
- WHEN `mergeFacets()` uses `array_diff` to find non-overlapping entries
- THEN entries with identical counts MAY be incorrectly excluded
- AND the merged result MAY be incomplete

---

### Requirement: Result Sorting

The result sorting utility provides a comparison function for sorting by relevance score.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-030 | `sortResultArray()` MUST compare two result arrays by their `_score` field using the spaceship operator | MUST | Implemented |
| SRCH-031 | `sortResultArray()` MUST be usable as a callback for `usort()` | MUST | Implemented |
| SRCH-032 | `sortResultArray()` is not called from any LarpingApp code | SHOULD | Dead Code |

#### Scenario: Sort results by score

- GIVEN results `[{_score: 0.8, name: "A"}, {_score: 0.95, name: "B"}, {_score: 0.5, name: "C"}]`
- WHEN `usort($results, [$searchService, 'sortResultArray'])` is called
- THEN the order MUST be C (0.5), A (0.8), B (0.95) -- ascending by _score

#### Scenario: Equal scores

- GIVEN two results with `_score: 0.7`
- WHEN `sortResultArray()` compares them
- THEN it MUST return 0 (equal)

---

### Requirement: MongoDB Filter Construction

The MongoDB filter construction methods build query filters from search parameters.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-040 | `createMongoDBSearchFilter()` MUST convert `_search` filter to a `$regex` query with case-insensitive `$options: 'i'` | MUST | Implemented |
| SRCH-041 | `createMongoDBSearchFilter()` MUST generate `$or` conditions across all provided `$fieldsToSearch` | MUST | Implemented |
| SRCH-042 | `createMongoDBSearchFilter()` MUST unset the `_search` key from filters after conversion | MUST | Implemented |
| SRCH-043 | `createMongoDBSearchFilter()` MUST convert `'IS NOT NULL'` filter values to `['$ne' => null]` | MUST | Implemented |
| SRCH-044 | `createMongoDBSearchFilter()` MUST convert `'IS NULL'` filter values to `['$eq' => null]` | MUST | Implemented |
| SRCH-045 | These methods are not called from any LarpingApp code | SHOULD | Dead Code |

#### Scenario: Convert _search to MongoDB regex

- GIVEN filters `['_search' => 'healing']` and fieldsToSearch `['name', 'description']`
- WHEN `createMongoDBSearchFilter()` is called
- THEN the result MUST include `['$or' => [['name' => ['$regex' => 'healing', '$options' => 'i']], ['description' => ['$regex' => 'healing', '$options' => 'i']]]]`
- AND `_search` MUST be removed from the filter array

#### Scenario: IS NOT NULL conversion

- GIVEN filters `['status' => 'IS NOT NULL']`
- WHEN processed
- THEN status MUST become `['$ne' => null]`

#### Scenario: IS NULL conversion

- GIVEN filters `['deletedAt' => 'IS NULL']`
- WHEN processed
- THEN deletedAt MUST become `['$eq' => null]`

---

### Requirement: MySQL Search Conditions

The MySQL search construction methods build SQL WHERE clauses and parameters for text search.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-050 | `createMySQLSearchConditions()` MUST generate `LOWER(field) LIKE :search` SQL conditions for each field | MUST | Implemented |
| SRCH-051 | `createMySQLSearchConditions()` MUST only generate conditions when `_search` is present in filters | MUST | Implemented |
| SRCH-052 | `createMySQLSearchParams()` MUST create a `search` parameter with `%lowercased_value%` wrapping | MUST | Implemented |
| SRCH-053 | `createMySQLSearchParams()` MUST only create parameters when `_search` is present | MUST | Implemented |
| SRCH-054 | These methods are not called from any LarpingApp code | SHOULD | Dead Code |

#### Scenario: Generate MySQL search conditions

- GIVEN filters `['_search' => 'healing']` and fieldsToSearch `['name', 'description']`
- WHEN `createMySQLSearchConditions()` is called
- THEN the result MUST be `['LOWER(name) LIKE :search', 'LOWER(description) LIKE :search']`

#### Scenario: Generate MySQL search parameters

- GIVEN filters `['_search' => 'Healing']`
- WHEN `createMySQLSearchParams()` is called
- THEN the result MUST be `['search' => '%healing%']` (lowercased and wrapped)

#### Scenario: No _search parameter

- GIVEN filters `['status' => 'active']` (no _search key)
- WHEN `createMySQLSearchConditions()` is called
- THEN the result MUST be an empty array

---

### Requirement: Special Query Parameter Handling

The special parameter stripping utility removes framework/system parameters from filter arrays.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-060 | `unsetSpecialQueryParams()` MUST remove all filter keys starting with `_` (underscore) | MUST | Implemented |
| SRCH-061 | `unsetSpecialQueryParams()` MUST return the remaining filters without underscore-prefixed keys | MUST | Implemented |
| SRCH-062 | This method is not called from any LarpingApp code | SHOULD | Dead Code |

#### Scenario: Strip special parameters

- GIVEN filters `['_search' => 'test', '_limit' => 10, 'name' => 'healing', '_order' => 'asc']`
- WHEN `unsetSpecialQueryParams()` is called
- THEN the result MUST be `['name' => 'healing']`
- AND `_search`, `_limit`, and `_order` MUST be removed

#### Scenario: No special parameters

- GIVEN filters `['name' => 'healing', 'type' => 'skill']`
- WHEN `unsetSpecialQueryParams()` is called
- THEN the result MUST be unchanged: `['name' => 'healing', 'type' => 'skill']`

---

### Requirement: Sort Construction

The sort construction utilities parse `_order` parameters into database-specific sort configurations.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-070 | `createSortForMySQL()` MUST parse `_order` filter array into `[field => 'ASC'|'DESC']` format | MUST | Implemented |
| SRCH-071 | `createSortForMySQL()` MUST default direction to `ASC` for non-DESC values | MUST | Implemented |
| SRCH-072 | `createSortForMongoDB()` MUST parse `_order` filter array into `[field => 1|-1]` format | MUST | Implemented |
| SRCH-073 | `createSortForMongoDB()` is marked `@todo Not functional yet` in code docblock | SHOULD | Bug |
| SRCH-074 | These methods are not called from any LarpingApp code | SHOULD | Dead Code |

#### Scenario: Parse MySQL sort ascending

- GIVEN filters `['_order' => ['name' => 'ASC']]`
- WHEN `createSortForMySQL()` is called
- THEN the result MUST be `['name' => 'ASC']`

#### Scenario: Parse MySQL sort descending

- GIVEN filters `['_order' => ['createdAt' => 'DESC']]`
- WHEN `createSortForMySQL()` is called
- THEN the result MUST be `['createdAt' => 'DESC']`

#### Scenario: Parse MySQL sort default direction

- GIVEN filters `['_order' => ['name' => 'invalid']]`
- WHEN `createSortForMySQL()` is called
- THEN the result MUST be `['name' => 'ASC']` (defaults to ASC)

#### Scenario: Parse MongoDB sort

- GIVEN filters `['_order' => ['name' => 'DESC']]`
- WHEN `createSortForMongoDB()` is called
- THEN the result MUST be `['name' => -1]`

---

### Requirement: Query String Parsing

The custom query string parser supports nested bracket notation for complex filter parameters.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-080 | `parseQueryString()` MUST parse a URL query string into an associative array | MUST | Implemented |
| SRCH-081 | `parseQueryString()` MUST support nested bracket syntax (e.g., `param[key1][key2]=value`) via recursive parsing | MUST | Implemented |
| SRCH-082 | `parseQueryString()` MUST support array-append syntax (e.g., `param[key][]`) by pushing values | MUST | Implemented |
| SRCH-083 | `parseQueryString()` MUST URL-decode both keys and values | MUST | Implemented |
| SRCH-084 | `parseQueryString()` delegates nested parsing to `recursiveRequestQueryKey()` which uses regex matching | MUST | Implemented |
| SRCH-085 | `parseQueryString()` has a bug: `$vars` is used uninitialized (relies on PHP implicit array creation) | SHOULD | Bug |
| SRCH-086 | Neither `parseQueryString()` nor `recursiveRequestQueryKey()` is called from any LarpingApp code | SHOULD | Dead Code |

#### Scenario: Parse simple query string

- GIVEN query string `"name=healing&type=skill"`
- WHEN `parseQueryString()` is called
- THEN the result MUST be `['name' => 'healing', 'type' => 'skill']`

#### Scenario: Parse nested bracket syntax

- GIVEN query string `"filter[name][contains]=heal"`
- WHEN `parseQueryString()` is called
- THEN the result MUST be `['filter' => ['name' => ['contains' => 'heal']]]`

#### Scenario: Parse array-append syntax

- GIVEN query string `"tags[]=combat&tags[]=magic"`
- WHEN `parseQueryString()` is called
- THEN the result MUST be `['tags' => ['combat', 'magic']]`

#### Scenario: URL decoding

- GIVEN query string `"name=Healing%20Mana&type=skill%20tree"`
- WHEN `parseQueryString()` is called
- THEN the result MUST be `['name' => 'Healing Mana', 'type' => 'skill tree']`

#### Scenario: Uninitialized $vars bug

- GIVEN any query string
- WHEN `parseQueryString()` runs
- THEN PHP's implicit array creation MUST allow `$vars[$key] = ...` to work
- BUT strict mode or certain PHP configurations MAY emit a notice/warning

---

### Requirement: Actual Search in LarpingApp

The actual search functionality used by LarpingApp is handled by the frontend object store communicating with OpenRegister's API.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SRCH-090 | Frontend search MUST use `_search` parameter when calling the OpenRegister API | MUST | Implemented |
| SRCH-091 | Frontend search MUST be debounced (500ms) to avoid excessive API calls | MUST | Implemented |
| SRCH-092 | Search results MUST update the entity list in real-time as the user types | MUST | Implemented |
| SRCH-093 | Clearing the search field MUST restore the full unfiltered list | MUST | Implemented |
| SRCH-094 | All entity list views (characters, skills, items, conditions, effects, events, players, abilities) MUST support text search | MUST | Implemented |

#### Scenario: Search skills in the UI

- GIVEN 20 skills exist
- WHEN the user types "heal" in the skills list search field
- THEN after 500ms debounce the store MUST call the OpenRegister API with `_search=heal`
- AND only matching skills MUST be displayed

#### Scenario: Clear search restores full list

- GIVEN a search for "heal" is active showing 3 results
- WHEN the user clears the search field
- THEN the store MUST call the API without `_search`
- AND all 20 skills MUST be displayed

#### Scenario: Search across entity types

- GIVEN the user navigates to Characters and searches "dragon"
- AND then navigates to Items and searches "sword"
- THEN each view MUST maintain its own search state
- AND each search MUST query the correct object type

---

## Known Issues

1. **Dead code**: The `search()` method references `$this->elasticService` and `$this->directoryService` which do not exist. Calling this method would throw a fatal error.

2. **OpenCatalogi heritage**: The entire service was inherited from OpenCatalogi. The federated search pattern has no relevance to LarpingApp.

3. **Incorrect method end comments**: Several methods have `//end createMongoDBSearchFilter()` as their closing comment regardless of which method they close.

4. **Uninitialized `$vars`**: `parseQueryString()` uses `$vars` without initializing it to `[]`.

5. **`mergeFacets()` logic issue**: Uses `array_diff` on associative arrays of counts, which compares values not keys. Same-count entries may be incorrectly excluded.

6. **`createSortForMongoDB()` marked as non-functional**: The docblock says `@todo Not functional yet`.

7. **No integration**: None of the SearchService methods are called from any LarpingApp code. All search goes through the frontend object store communicating with OpenRegister.

## Dependencies

- **GuzzleHttp\Client**: HTTP client for async federated search requests (dead code)
- **GuzzleHttp\Promise\Utils**: Promise settlement for concurrent request handling (dead code)
- **OCP\IURLGenerator**: URL generation (imported, used in dead search method)
- **Symfony\Component\Uid\Uuid**: UUID generation (imported but not used in any method)
