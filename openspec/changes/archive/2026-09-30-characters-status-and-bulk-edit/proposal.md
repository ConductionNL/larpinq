---
kind: code
depends_on: []
---

# Proposal: characters-status-and-bulk-edit

## Summary

A campaign that runs for years collects characters who retired or died, and
larpinq cannot say so: a character is only approved or not. This change adds
a character status (active, retired, dead) that keeps retired and dead
characters out of new events, and lets a game master select many characters
on the Characters index and set a status, type or world on all of them at
once.

## Motivation

Two characters rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Characters is a
core area; the OpenSpec pass of 2026-09-27 decided `build` for both.

**`chr-status-lifecycle`**, "Mark a character as active, retired or dead."
Larpinq rates it no. Matrix evidence: "lib/Settings/larpinq_register.json:101-111
(character.approved is only no/approved) and :90-100 (character.type is only
player/npc/other), no lifecycle field for active/retired/dead exists". One
competitor rates it yes and two partial:

- LARP Portal (yes): "features.php explicitly lists 'Character death management' as a Character Module feature. https://larportal.com/features.php"
- LarpManager (partial): "larpmanager/forms/character.py:873-886 and 1300-1320 Active flag (inactive characters cannot be assigned) under the campaign feature; no retired versus dead distinction" (source read at main 36f23d3).
- Kanka (partial): "lang/en/entities/statuses.php:4-8 character statuses alive, dead, missing (category_statuses, database/migrations/2026_03_26_000001); no retired status" (source read at tag 3.15).

**`chr-bulk-edit`**, "Change many characters at once, such as setting a
faction, status or writer on a selection." Larpinq rates it no. Matrix
evidence: "grep -n 'mass\|bulk\|selectable' src/manifest.json: no hits; index
pages edit one object at a time". Two competitors rate it yes:

- LarpManager: "larpmanager/utils/services/bulk.py:637 handle_bulk_characters, wired for Character in larpmanager/utils/services/writing.py:379" (source read at main 36f23d3). Its changelog: https://github.com/LoSkana/larpmanager/commit/f4717e56ae.
- Kanka: "routes/campaigns/bulks.php:12-13 batch edit per module; app/Services/BulkService.php:278-299,357-369 sets type, status and tags (245-259 privacy) on every selected entry, plus bulk permissions (bulks.php:21-22)" (source read at tag 3.15).

The status is the first thing a game master sets in bulk after a season, so
the two rows ship together.

## Affected Projects

- [ ] Project: `larpinq`: a `status` property on `character`, picker filters on the event and registration side, a pre-write refusal, and a bulk action on the Characters index.

## Scope

### In Scope

- `character.status`: `active`, `retired`, `dead`; default `active`; facetable; written by game masters.
- Retired and dead characters left out of the character pickers of `event.players`, and a write that adds such a character to an event refused.
- A status column and facet on the Characters index and the status on the character detail page.
- A bulk action on the Characters index: select characters and set status, type or world (`setting`) on all of them, in one confirmation.
- The same bulk action sets the faction and the writer once `characters-factions-and-relationships` and `characters-plot-threads-and-writing` have landed; each of those adds its field to the bulk form.

### Out of Scope

- An in-game death record (cause, date, event). A note field can hold it today.
- Bulk editing of skills, items or conditions: those go through the requirement check per character.

## Approach

The property and the picker filter are register declarations in a new
fragment. The event refusal extends the existing
`CharacterRequirementListener` diff check. The bulk action is a manifest
`bulkActions` entry on the Characters index that opens a registered modal,
which writes each selected character through the OpenRegister objects API
so every write passes the same rules. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/characters-status-and-bulk-edit.json` (new).
- `lib/Listener/CharacterRequirementListener.php`: refuse adding an event to a retired or dead character.
- `src/manifest.json`: the Characters index gains `selectable`, the bulk action and a status column, and CharacterDetail shows the status. These are edits of existing pages, made in place, because `mergeManifestFragments()` (`src/main.js:276-310`) only appends pages and menu entries from `src/manifest.d/`.
- `src/modals/CharacterBulkEditModal.vue` (new), registered in `src/registry.js`.

## Cross-Project Dependencies

`@conduction/nextcloud-vue` `CnIndexPage` `selectable` and `bulkActions`
with `handler: "open-modal"` (documented in `docs/components/cn-index-page.md`),
already in the installed 2.57.1.

## Risks

### Risk 1: A bulk edit half succeeds
**Severity:** Medium. **Mitigation:** the modal writes one character at a time and reports each result; a refused write (for example a player-owned character a non-GM cannot change) is listed by name and the rest still apply. Nothing is rolled back silently.

### Risk 2: Old data has no status
**Severity:** Low. **Mitigation:** the default `active` applies on read for objects without the field, and the seed data sets it explicitly.

## Rollback Strategy

Remove the two fragments, the modal and the listener branch. The `status`
values stay on the objects, unused.

## Open Questions

None.
