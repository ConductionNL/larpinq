# Design: rules-unique-holder-enforcement

## Context

Read at development `2af18d8`.

- `lib/Settings/larpinq_register.json:589-594` declares `item.unique`
  (boolean, default true) and `:661-666` declares `condition.unique`
  (boolean, default false). Both show on the item and condition data widgets.
- A holding is stored twice and the two sides are not synced: the character
  carries `items[]` and `conditions[]` (UUID arrays, `$ref` `larping_item` and
  `condition`), and the item and condition carry `characters[]` (UUID array,
  `$ref` `character`). No `x-openregister-relations` inverse ties them.
- `lib/Listener/CharacterRequirementListener.php` is the pattern to copy. It
  is registered for `OCA\OpenRegister\Event\ObjectCreatingEvent` and
  `ObjectUpdatingEvent` in `lib/AppInfo/Application.php:117-130`, guarded by
  `class_exists()`. It resolves the character schema id from app config
  (`character_schema`, `isCharacterSchema()` at `:239-252`), diff-scopes on the
  changed association fields (`associationsChanged()`), and vetoes with
  `$event->stopPropagation()` plus `$event->setErrors([...])`. Its catch block
  logs and lets the write through, which is right for a rule validator.
- `lib/Service/RegisterObjectFetcher.php:322-341` `getObjects()` accepts
  `limit` and `filters` and passes them to the mapper's `findAll()`. Every call
  site today passes neither (see the open change
  `larpinq-runsheet-scoped-cast-query`).
- `lib/Service/EventRosterService.php:309-321` `collectItems()` already treats
  `character.items[]` as the holding for the run sheet's unique-items rollup.

## Goals / Non-Goals

**Goals**

- One holder per unique item and per unique condition, enforced on every
  write path OpenRegister serves (pages, REST, GraphQL, MCP).
- A refusal that says who holds the thing now.

**Non-Goals**

- Syncing `character.items[]` with `item.characters[]`.
- A transfer action.

## Decisions

### D1. A second listener, not more branches in CharacterRequirementListener

`CharacterRequirementListener` is about skill requirements and the XP budget
on character writes. Uniqueness spans three schemas and has its own failure
message. A separate `UniqueHolderListener` keeps each veto readable and
testable. Alternative considered: extend `collectVeto()`; rejected because it
would make the requirement listener handle item and condition writes too.

### D2. Count holders on both sides

A holder is any character whose `items[]` (or `conditions[]`) contains the id,
plus any id in `item.characters[]` (or `condition.characters[]`), minus the
character being written. `UniqueHolderService::otherHolders(string $type, string
$id, ?string $exceptCharacter): array` returns at most two holder names. Reading
both sides costs one extra query and closes the gap the unsynced model leaves.

### D3. Which writes are checked

| Write | Checked when | Refused when |
|---|---|---|
| character create or update | `items[]` or `conditions[]` gains an id | the added item or condition is unique and has another holder |
| item or condition create or update | `characters[]` changes, or `unique` goes from false to true | `unique` is true and the holders, both sides, number more than one |

An edit that does not add a holder or switch the flag on is never blocked,
even when old data already breaks the rule (same diff-scoping as
`associationsChanged()`).

### D4. Bounded lookup

`otherHolders()` calls `RegisterObjectFetcher::getObjects('character', limit: 2,
filters: ['items' => $id])` (or `conditions`). Two results are enough to refuse
and to name the holder. If the OpenRegister filter does not match inside an
array field, the fallback pages through characters in batches of 100 and stops
at the first other holder, never an unbounded `findAll()` (hydra ADR-058).

### D5. Error shape

The veto uses the field the user edited as the key, so the page shows it on
the right input: `{"items": [{"code": "unique_item_held", "item": "<uuid>",
"heldBy": "<character name>"}]}`, `conditions` likewise, and `characters` or
`unique` on an item or condition write. The message text is translated in
`l10n/`.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| One holder per unique item or condition | Imperative, pre-write listener | hydra ADR-031 exception 2: the rule spans the character schema and the item or condition schema, and depends on a flag on the other object. OpenRegister's declarative extensions validate one object; none expresses "at most one object across schema A references this object of schema B when its flag is set". |
| The unique flag itself | Declarative, already in the register | No change. |

## Seed data

No schema change. The existing demo data gets one unique item held by exactly
one character (for example "Crown of Aldmoor" on "Queen Isolde") so the
Playwright test has a holder to collide with.

## Risks / Trade-offs

- [Existing conflicts] The listener lets an old conflict stand until someone
  adds a holder. Mitigation: `occ larpinq:unique-holders:check` lists them.
- [Validator error] Like the requirement listener, a thrown exception is
  logged and the write goes through, so a bug cannot block every character
  write. Mitigation: PHPUnit covers each refusal path.

## Migration

None. Existing data is untouched.

## Open Questions

None.
