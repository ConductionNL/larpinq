# Tasks: worlds-lore-pages

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

- [x] 1.1 `openspec/architecture/adr-003-lore-pages-are-register-objects.md`, linked from ADR-001's consequences.
- [x] 1.2 `lib/Settings/register.d/worlds-lore-pages.json`: `lorePage` with the D2 rules (REQ-WLP-001, REQ-WLP-002, REQ-WLP-003). Verified: `tests/unit/Settings/LorePagesFragmentTest.php` over the real merge; `npm run check:register`; `npm run check:schema-l10n`.
- [x] 1.3 The read rule on every path (REQ-WLP-002, REQ-WLP-003): Playwright `tests/e2e/workflows/lore-pages.workflow.spec.ts` reads the index, the object and search as a `larpers` user, before and after the reveal moment (design "Changes at build" 6). Written, not run: no isolated instance.
- [x] 1.4 The four Aldmoor pages in `lib/Settings/larpinq_mock_register.json`. Verified: `LorePagesFragmentTest::testTheSeedPagesValidate` validates each with Opis against the merged schema.
- [x] 2.1 `src/manifest.d/worlds-lore-pages.json` (Lore index, LoreArticle page, menu entry), the Lore list on SettingDetail, the menu relocation, and the LoreArticle wrapper (REQ-WLP-001, REQ-WLP-004). Verified: `tests/vitest/loreArticle.spec.js`; `npm run check:manifest`.
- [x] 3.1 Strings in all 37 shipped locales (REQ-WLP-001). Verified: `npm run test:l10n`.
- [x] 3.2 `docs/features/lore-pages.md`, linked from `docs/features/README.md`. Screenshots not taken: no isolated instance.
