# Tasks: characters-status-and-bulk-edit

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Status

- [x] 1.1 `lib/Settings/register.d/characters-status-and-bulk-edit.json`: `character.status` with enum, labels, default and facet, update for game masters, and `status: active` in the `x-relation-filter` of `event.players`, edited in place in `larpinq_register.json` (design change 7) (REQ-CSB-001, REQ-CSB-002). Verified: `tests/unit/Settings/CharacterStatusFragmentTest.php` over the real merge, seeds validated with Opis; `npm run check:register`; `npm run check:schema-l10n`.
- [x] 1.2 Status on CharacterDetail (Game state & notes) and as a column on the Characters index, edited in place in `src/manifest.json`; the facet comes from `facetable` (REQ-CSB-001). Verified: `tests/vitest/characterBulkEdit.spec.js`; `npm run check:manifest`.
- [x] 1.3 A change of `events[]` that adds an event to a retired or dead character is refused on `events`, through `CharacterStatusGuard` in `CharacterStatusListener` (design change 2) (REQ-CSB-002). Verified: `tests/unit/Listener/CharacterStatusListenerTest.php` with the real OpenRegister event classes, for active, no status, retired and dead. `BackfillCharacterStatus` (design change 3): `tests/unit/Repair/BackfillCharacterStatusTest.php`.

## 2. Bulk edit

- [x] 2.1 `bulkActions` and `selectable` on the Characters index in `src/manifest.json`, and `src/dialogs/CharacterBulkEditDialog.vue` opened by the registry handler `larpinqBulkEditCharacters` (design change 1) (REQ-CSB-003, REQ-CSB-004). Verified: `tests/vitest/characterBulkEdit.spec.js`: one PATCH per selected id, a refusal named with its reason, the others still written.
- [x] 2.2 `tests/e2e/workflows/character-status.workflow.spec.ts` at API level: three characters set retired one by one, a dead character refused a new event on `events` (REQ-CSB-002, REQ-CSB-003). Written, not run: no isolated instance.

## 3. Strings and docs

- [x] 3.1 Strings for the status values, the action and the dialog in all 37 shipped locales (REQ-CSB-001, REQ-CSB-003). Verified: `npm run test:l10n`, `npm run check:l10n-js`.
- [x] 3.2 `docs/features/character-status-and-bulk-edit.md`, linked from `docs/features/README.md`. Screenshot not taken: no isolated instance.

Seeds: the three demo characters carry active, retired and dead.
