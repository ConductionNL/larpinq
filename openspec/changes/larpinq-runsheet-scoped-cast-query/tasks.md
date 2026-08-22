# Tasks — larpinq-runsheet-scoped-cast-query

## 1. Preconditions

- [ ] 1.1 Confirm at HEAD that `EventsController::buildRunsheetContext()` (`lib/Controller/EventsController.php:163-235`) still calls `getObjects('character')` and `getObjects('player')` with no filters, and that `RegisterObjectFetcher::getObjects()` (`lib/Service/RegisterObjectFetcher.php:329-357`) forwards a `filters` array to the OpenRegister mapper's `findAll()`
- [ ] 1.2 Read the OpenRegister `ObjectService`/mapper `findAll()` filter contract (openregister repo) to confirm whether it supports array-field "contains" matching (e.g. `events` containing a given UUID) or only scalar-equality filters; record the finding here before writing task 2

## 2. Scope the character query

- [ ] 2.1 If array-contains filtering is supported: change `buildRunsheetContext()` line 167 to `$this->objectFetcher->getObjects('character', filters: ['events' => $eventId])`
- [ ] 2.2 If NOT supported: implement a paginated scan (`limit`/`offset` loop) through the `character` register that stops once no more pages remain, and note in a code comment why a full unfiltered `findAll()` was replaced with pagination (avoids loading the whole register into one PHP array at once, still bounded per page)
- [ ] 2.3 Preserve the existing `is_array($events) === false` guard and `in_array($eventId, array_map('strval', $events), true)` membership check as the final correctness check even when the OR-side filter pre-narrows the set (defense in depth against a filter that under- or over-matches)

## 3. Scope the player lookup

- [ ] 3.1 Change `indexPlayers()` (`lib/Controller/EventsController.php:242-258`) to accept the already-filtered cast's player-id set and fetch only those players (by id, or via an `id`-in-list filter) instead of `getObjects('player')` unfiltered
- [ ] 3.2 Update `buildRunsheetContext()`'s call order so player ids are known before `indexPlayers()` runs (resolve cast first, collect distinct `player`/`ocName` ids, then look up only those)

## 4. Tests

- [ ] 4.1 Extend `tests/e2e/spec-coverage/event-runsheet-export.spec.ts` (or add a PHPUnit test on `EventsController`) asserting the runsheet still returns the correct cast for an event when the register contains characters NOT linked to that event (regression guard against the new filter over- or under-matching)
- [ ] 4.2 Add a PHPUnit test (or extend an existing `EventsController` test) that asserts `RegisterObjectFetcher::getObjects()` is called with a scoping filter/limit rather than no arguments, via a mock/spy on `objectFetcher`
- [ ] 4.3 `@spec` annotations on the changed methods pointing at this change (gate-16)

## 5. Quality

- [ ] 5.1 `composer check:strict` green
- [ ] 5.2 Hydra gates green on the diff
