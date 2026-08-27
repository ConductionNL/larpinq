---
kind: code
---

# Event runsheet export does a full-register scan to find one event's cast

## Why

`lib/Controller/EventsController.php::buildRunsheetContext()` (`lib/Controller/EventsController.php:163-235`)
builds the cast list for **one** event's PDF runsheet like this:

```php
$characters = $this->objectFetcher->getObjects('character');   // line 167 — ALL characters, no limit/filter
...
$players = $this->indexPlayers();                                // line 172 -> line 246: getObjects('player'), ALL players
foreach ($characters as $character) {
    // line 182: in_array($eventId, $character['events'], true) filtering done in PHP
```

`RegisterObjectFetcher::getObjects()` (`lib/Service/RegisterObjectFetcher.php:329-357`) accepts
`limit`, `offset`, `filters`, `sort`, and `search` and forwards them to the OpenRegister mapper's
`findAll()` — but every call site in this codebase (`grep -n "getObjects(" lib -r` — 7 call
sites across `CharacterService.php`, `EventsController.php`, `SkillRequirementService.php`) calls
it with **zero** arguments beyond the object type. `buildRunsheetContext()` therefore always loads
the **entire** `character` register and the **entire** `player` register into PHP memory, for
every single runsheet download, no matter how small the target event's cast is. On a LARP
campaign with hundreds or thousands of characters across many events/seasons (the exact scale a
successful multi-season LARP reaches), this is an O(all-characters-ever-created) database read
and PHP array walk to render one event's ~20-person cast list — and it repeats on every
`downloadRunsheet()` call (`EventsController.php:141-144` calls it directly, no caching).

This is not a hypothetical: `CharacterService::loadAllEntities()` (a similar full-register-load
pattern) was deliberately guarded behind a load-once flag and documented as closing #217
(`lib/Service/CharacterService.php:150-152`, "Guarded by $entitiesLoaded so the 6 OR queries are
only issued once per service instance"). `buildRunsheetContext()` has no equivalent guard and no
server-side filter — it is a regression of the same class of problem #217 fixed elsewhere in this
app, in a request path (PDF export) that is more likely to be run against a large, established
campaign than a fresh install.

## What Changes

- Change `EventsController::buildRunsheetContext()` to pass a server-side filter to
  `RegisterObjectFetcher::getObjects('character', filters: ['events' => $eventId])` (or the
  equivalent array-contains filter shape the OpenRegister mapper supports for this field) so the
  database does the membership match instead of PHP iterating every character in the register.
- Similarly scope `indexPlayers()` to only the players referenced by the filtered cast (resolve
  player ids from the already-scoped character list, then fetch just those players by id) instead
  of loading every player in the register.
- If OpenRegister's generic filter contract does not support array-field-contains matching today,
  document that limitation explicitly in the task list and fall back to a `limit`-bounded,
  paginated scan with a hard ceiling (e.g. batch through the register in pages, stopping once the
  cast for the target event is fully collected) rather than one unbounded `findAll()`.
- No change to the PDF output, the runsheet template, or the render data shape — `cast`,
  `castCount`, and `uniqueItemsInPlay` keep their exact current keys and semantics.

Not BREAKING: same PDF output for any existing runsheet; only the OpenRegister query shape
changes.
