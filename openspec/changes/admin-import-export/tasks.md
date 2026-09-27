# Tasks: admin-import-export

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 6. -->

- [ ] 1.1 `lib/Settings/register.d/admin-import-export.json`: `exportable: true` on every larpinq schema (REQ-AIE-001). Verify: `npm run check:register`.
- [ ] 1.2 `allowExport` and `exportFormats` on every index page in `src/manifest.json` (REQ-AIE-001). Verify: `npm run check:manifest`; Playwright `tests/e2e/list-export.spec.ts` downloads a CSV of the Characters list with the current filter; as a player, the file has no private column.
- [ ] 1.3 Import on Characters and Players with templates, game masters only (REQ-AIE-002). Verify: Playwright imports two players from the template and sees the counts; a player sees no import action.
- [ ] 1.4 "Export campaign" and "Import campaign" on the Worlds index (REQ-AIE-003). Verify: on the dev instance, the export has one sheet per larpinq schema, and importing it into a fresh register recreates the objects with their ids; the result is noted in the PR.
- [ ] 1.5 Dutch and English strings (REQ-AIE-001). Verify: `npm run test:l10n`.
- [ ] 1.6 `docs/features/import-and-export.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
