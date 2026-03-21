# Search Service

## Problem
The `SearchService` class (`lib/Service/SearchService.php`) contains methods for executing distributed searches, merging faceted aggregation results, constructing database query filters for MongoDB and MySQL backends, and parsing custom query strings with nested bracket syntax.
**Important: This service is largely dead/inherited code from OpenCatalogi.** It was carried over during the fork of LarpingApp from the OpenCatalogi project. The `search()` method references services that do not exist in LarpingApp (`$this->elasticService`, `$this->directoryService`), making it non-functional. The utility methods for MongoDB/MySQL filter construction and query string parsing are standalone and functional, but are not called from anywhere in the LarpingApp codebase. All actual search functionality in LarpingApp goes through the frontend object store, which communicates with OpenRegister's API using `_search`, `_limit`, `_offset`, and `_order` parameters.
**Key source file:** `lib/Service/SearchService.php`

## Proposed Solution
Implement Search Service following the detailed specification. Key requirements include:
- Requirement: Federated Search (Dead Code)
- Requirement: Facet/Aggregation Merging
- Requirement: Result Sorting
- Requirement: MongoDB Filter Construction
- Requirement: MySQL Search Conditions

## Scope
This change covers all requirements defined in the search-service specification.

## Success Criteria
- Calling search() causes fatal error
- Dead code identification
- Federated search concept inapplicable
- Merge overlapping facets
- Merge with null new aggregations
