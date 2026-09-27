# Tasks: characters-custom-fields

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Data

- [ ] 1.1 `lib/Settings/register.d/characters-custom-fields.json`: schema `characterField` with authorization, and `character.customFields` / `customFieldsPrivate` with property authorization (REQ-CCF-001, REQ-CCF-003). Verify: `npm run check:register`; `npm run check:schema-l10n`; Newman as a player gets no `customFieldsPrivate`.
- [ ] 1.2 Seed the four Aldmoor definitions and Mirela's values of design.md. Verify: the seed imports without a validation error.

## 2. Rules

- [ ] 2.1 `CustomFieldValidator` and its call from `CharacterRequirementListener` when either value property changed (REQ-CCF-004). Verify: PHPUnit per type, unknown key, wrong world, and private value in the public property.

## 3. Pages

- [ ] 3.1 `src/manifest.d/characters-custom-fields.json`: Character fields index and detail pages and the menu entry (REQ-CCF-001). Verify: `npm run check:manifest`.
- [ ] 3.2 `src/views/CharacterCustomFields.vue` as a `kind: 'section'` registry entry, placed on CharacterDetail in `src/manifest.json` (REQ-CCF-002, REQ-CCF-003). Verify: vitest renders each input type and hides private definitions for a player.
- [ ] 3.3 Playwright `tests/e2e/character-custom-fields.spec.ts`: a game master adds "Guild rank" (choice), the player sets it on her own sheet, another player cannot see "True allegiance" (REQ-CCF-001 to REQ-CCF-004). Verify: passes locally.

## 4. Strings and docs

- [ ] 4.1 Dutch and English strings (REQ-CCF-002). Verify: `npm run test:l10n`.
- [ ] 4.2 `docs/features/character-custom-fields.md` with a screenshot (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
