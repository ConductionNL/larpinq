# Design: characters-status-and-bulk-edit

## Context

Read at development `2af18d8`.

- `character` has `type` (`player`, `npc`, `other`, facetable) and `approved`
  (`no`, `approved`, the `x-openregister-lifecycle` field with transitions
  `approved` and `unapprove`). A schema has one lifecycle block, and it is
  taken by `approved`.
- `event.players` is a `$ref` `character` array with
  `"x-relation-filter": {"setting": "@object.setting"}`: the picker already
  filters on a field of the candidate object.
- `lib/Listener/CharacterRequirementListener.php` handles character pre-write
  events and diff-scopes on `skills`, `items`, `conditions` and
  `requirementOverrides` (`associationsChanged()`).
- The Characters index (`src/manifest.json`, page `Characters`) is a plain
  index page. `CnIndexPage` in `@conduction/nextcloud-vue` supports
  `selectable` and `config.bulkActions[]`, whose `handler: "open-modal"` opens
  a registered modal with the selected ids.
- `src/registry.js` is the ADR-036 registry; modals live in `src/modals/`
  (hydra modal-isolation rule).
- `src/main.js:276-310` `mergeManifestFragments()` concatenates `pages` and
  `menu` from `src/manifest.d/`; it cannot change an existing page. Edits to
  the Characters index and CharacterDetail therefore go into `src/manifest.json`
  itself.

## Goals / Non-Goals

**Goals**: a status that means something (retired and dead characters stay
out of new events), and one action to change many characters.

**Non-Goals**: a death record, bulk changes to the build.

## Decisions

### D1. A plain enum, not a second lifecycle

`status` is an enum with default `active`. The lifecycle slot belongs to
`approved`, and moving between the three states is free in both directions (a
resurrection is a game fact, not an error). Alternative: a lifecycle with
transitions; rejected because a schema carries one lifecycle and these states
need no guard on their order.

### D2. Keep retired and dead characters out of events

Two layers. The picker: `event.players` gains `"status": "active"` in its
`x-relation-filter`, so the event form does not offer them. The rule:
`CharacterRequirementListener` treats a change of `events[]` as an association
change and refuses a write that adds an event to a character whose status is
not `active`, with a field error on `events`. The registration change
(`registration-intake-and-capacity`) filters its character choice on the same
field.

### D3. Who writes the status

Game masters. `characters-player-visibility` puts `status` in its game-master
write list; until it lands, anyone with the Characters page can set it, as
with every character field today.

### D4. The bulk action

`config.bulkActions` on the Characters index: `{"id": "bulk-edit", "label":
"Edit selected", "handler": "open-modal", "target": "CharacterBulkEditModal"}`
and `selectable: true`. `CharacterBulkEditModal.vue` shows the fields that may
be set in bulk (status, type, world; faction and writer when their changes
land), a count of the selection, and on confirm writes each character with a
PATCH through the object store. It lists successes and refusals by name.
Alternative: a larpinq bulk endpoint; rejected because a per-object write keeps
every rule (RBAC, the requirement listener, the unique-holder check) on the
one OpenRegister write path.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| The status field and its facet | Declarative, register fragment | A property. |
| Pickers skip retired and dead characters | Declarative, `x-relation-filter` | Existing mechanism. |
| Refuse adding an event to a retired or dead character | Imperative, the existing pre-write listener | hydra ADR-031 exception 2: the rule reads the character's status while the write changes its event list; the listener that guards character writes already exists. |
| Bulk edit | Frontend modal over the object API | Per-object writes; no aggregation. |

## Seed data

Set `status` on the demo characters: "Mirela the Wanderer" active, "Old
Captain Harrow" retired, "Brother Aldric" dead (fell at "Summer Siege 2025").

## Risks / Trade-offs

- [Selection across pages] A bulk edit acts on the selected rows only; the
  modal states the count so a game master sees what will change.
- [Performance] Fifty writes from the browser take a few seconds; the modal
  shows progress. A server batch is not worth a second write path.

## Migration

None. Objects without `status` read as `active`.

## Open Questions

None.

## Changes at build (2026-09-30)

1. The bulk action uses a registry handler (`larpinqBulkEditCharacters`),
   not `handler: "open-modal"`: CnIndexPage 2.57 only emits a bulk
   `open-modal` event to its host, and no host listens. The handler opens
   `src/dialogs/CharacterBulkEditDialog.vue` with `spawnDialog` and reloads
   the list when a character changed. The dialog is an NcDialog, so it lives
   in `src/dialogs/`.
2. The events check is `CharacterStatusGuard`, called by
   `CharacterRequirementListener` before its requirement check, so an event
   change does not start a full requirement validation.
3. Migration after all: the picker filter `status = active` leaves out every
   character without a status (SQL `NULL` matches no value), so the repair
   step `BackfillCharacterStatus` sets `active` on stored characters without
   a status after the register import. Idempotent; it logs and continues.
4. `status` carries `x-enum-labels`, and its property rule gives `update` to
   game masters only (read stays open).
5. The Characters index gets explicit columns (name, type, status, approved,
   world); the status shows in Game state & notes on the character page.

6. Faction and writer are not in the bulk form. A faction is a membership
   object (characters-factions-and-relationships), not a character field, so
   one character PATCH cannot set it; the writer can join the form later.
