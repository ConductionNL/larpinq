# Tasks: admin-import-export

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 6. -->

- [x] 1.1 `lib/Settings/register.d/admin-import-export.json`: `configuration.exportable: true` on every larpinq schema (REQ-AIE-001). Verified: `tests/unit/Settings/AdminImportExportFragmentTest.php` over the real merge; `npm run check:register`.
- [x] 1.2 `allowExport` on every index page in `src/manifest.json` and the Cast fragment (REQ-AIE-001). Verified: `tests/vitest/campaignPortability.spec.js`; `npm run check:manifest`; Playwright `tests/e2e/workflows/data-portability.workflow.spec.ts` (written, not run: no isolated instance).
- [x] 1.3 Import on Characters and Players only, administrators only (REQ-AIE-002; see design "Changes at build" 2 and 3). Verified: vitest `applyImportGate` and manifest tests; Playwright imports two players.
- [x] 1.4 "Export campaign" and "Import campaign" on the Worlds index (REQ-AIE-003). Verified: vitest for the export URL, the import request and the count summary. The round trip into a fresh register was not run: no isolated instance.
- [x] 1.5 Strings in all 37 shipped locales (REQ-AIE-001). Verified: `npm run test:l10n`, `npm run check:l10n-js`.
- [x] 1.6 `docs/features/import-and-export.md` (ADR-010), linked from `docs/features/README.md`.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
