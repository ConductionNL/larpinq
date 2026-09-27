# Tasks: characters-status-and-bulk-edit

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Status

- [ ] 1.1 `lib/Settings/register.d/characters-status-and-bulk-edit.json`: `character.status` with enum, default and facet, and `status: active` in the `x-relation-filter` of `event.players` (REQ-CSB-001, REQ-CSB-002). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [ ] 1.2 Show status on CharacterDetail and as a column and facet on the Characters index in the manifest fragment (REQ-CSB-001). Verify: `npm run check:manifest`.
- [ ] 1.3 `CharacterRequirementListener`: a change of `events[]` that adds an event to a retired or dead character is refused on `events` (REQ-CSB-002). Verify: PHPUnit with the real OpenRegister event classes, for active, retired and dead.

## 2. Bulk edit

- [ ] 2.1 `bulkActions` and `selectable` on the Characters index, and `src/modals/CharacterBulkEditModal.vue` registered in `src/registry.js` (REQ-CSB-003, REQ-CSB-004). Verify: vitest for the modal: it writes each selected id and lists refusals by name.
- [ ] 2.2 Playwright `tests/e2e/character-bulk-edit.spec.ts`: select three characters, set status retired, see all three retired and absent from the event player picker (REQ-CSB-002, REQ-CSB-003). Verify: passes locally.

## 3. Strings and docs

- [ ] 3.1 Dutch and English strings for the status values, the action and the modal (REQ-CSB-001, REQ-CSB-003). Verify: `npm run test:l10n`.
- [ ] 3.2 `docs/features/character-status-and-bulk-edit.md` with a screenshot of the modal (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push; seed the three statuses of design.md.
