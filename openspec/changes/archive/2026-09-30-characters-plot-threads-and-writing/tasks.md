# Tasks: characters-plot-threads-and-writing

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

- [x] 1.1 Fragment with `plot` (lifecycle), `plotPart` (rules, materialised owner) and `character.writer` / `writingStep` (REQ-CPW-001 to REQ-CPW-004). Verified: `tests/unit/Settings/PlotsFragmentTest.php` over the real merge; `npm run check:register` (which caught `format: user`, design "Changes at build" 1); `npm run check:schema-l10n`.
- [x] 1.2 The read rules as Anna (REQ-CPW-002): Playwright `tests/e2e/workflows/plots-and-writing.workflow.spec.ts` reads her part (player text, no private text, owner copied), and is refused Sir Bertram's part and the plot. Written, not run: no isolated instance.
- [x] 1.3 Seed plot and two parts in `lib/Settings/larpinq_mock_register.json`. Verified: `PlotsFragmentTest::testTheSeedObjectsValidate` validates each with Opis against the merged schema.
- [x] 2.1 `src/manifest.d/characters-plot-threads-and-writing.json` (Plots, PlotDetail with lifecycle buttons, menu entry); CharacterDetail and CharacterRosterReport edits in `src/manifest.json`; the menu relocation (REQ-CPW-001, REQ-CPW-003, REQ-CPW-004). Verified: `tests/vitest/plotsManifest.spec.js`; `npm run check:manifest`.
- [x] 2.2 Playwright workflow for the plot, the ready transition and the report (REQ-CPW-001, REQ-CPW-003, REQ-CPW-004). Written, not run: no isolated instance.
- [x] 3.1 Strings in all 37 shipped locales (REQ-CPW-001). Verified: `npm run test:l10n`.
- [x] 3.2 `docs/features/plots-and-writing.md`, linked from `docs/features/README.md`. Screenshot not taken: no isolated instance.
