# Tasks: worlds-lore-pages

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Decision and data

- [ ] 1.1 `openspec/architecture/adr-003-lore-pages-are-register-objects.md`: the ADR-022 exception of design D1. Verify: linked from ADR-001's consequences.
- [ ] 1.2 `lib/Settings/register.d/worlds-lore-pages.json`: `lorePage` with the D2 rules (REQ-WLP-001, REQ-WLP-002, REQ-WLP-003). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [ ] 1.3 Newman: before its moment a player gets "The fall of the north gate" from neither the index, the object, nor search; after it, from all three; a game-master page never (REQ-WLP-002, REQ-WLP-003). Verify: the collection passes with a fixed `revealFrom` in the past and the future.
- [ ] 1.4 Seed the four Aldmoor pages of design.md. Verify: the seed imports cleanly.

## 2. Pages

- [ ] 2.1 `src/manifest.d/worlds-lore-pages.json` (Lore index, `type: "wiki"` read page, menu entry) and the Lore list on SettingDetail in `src/manifest.json` (REQ-WLP-001, REQ-WLP-004). Verify: `npm run check:manifest`; Playwright `tests/e2e/lore-pages.spec.ts` as a player and as a game master.

## 3. Strings and docs

- [ ] 3.1 Dutch and English strings (REQ-WLP-001). Verify: `npm run test:l10n`.
- [ ] 3.2 `docs/features/lore-pages.md` with screenshots of the index and a page (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
