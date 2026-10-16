---
kind: config
depends_on: []
---

# Proposal: admin-import-export

## Summary

Groups arrive with their data in spreadsheets and leave, or back up, with
their data in files. Larpinq offers neither: no list can be exported, nothing
can be imported, and there is no way to take a whole campaign out. OpenRegister
already imports and exports CSV and Excel for any schema, including a whole
register as one workbook with a sheet per schema. This change switches that on
in larpinq: an export menu on every list, an import action on the Characters
and Players lists with downloadable templates, and "Export campaign" and
"Import campaign" actions for game masters that move every larpinq schema in
one workbook.

## Motivation

Three rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for all three: each has two competitors rated
yes.

**`adm-backup-export`**, "Export all campaign data for a backup or to move
elsewhere." Larpinq rates it no. Matrix evidence: "grep -rniE
'backup|full.?export' lib/ src/: no hits".

- LarpManager (yes): "larpmanager/views/orga/event.py:626-660 orga_backup and orga_restore of a full event ZIP (larpmanager/utils/io/download/backup.py:47-77)" (source read at main 36f23d3).
- Kanka (yes): "full campaign export (routes/campaigns/campaign.php:207-208; app/Services/Campaign/ExportService.php), gallery assets export premium (app/Http/Controllers/Campaign/ExportController.php:43)" (source read at tag 3.15).
- pretix (partial): "src/pretix/base/exporters/json.py:49 JSONExporter dumps an event's products, questions and orders ...; a whole-instance backup is a database dump, not an in-app export".

**`adm-import-data`**, "Import existing characters and players from a
spreadsheet or another tool." Larpinq rates it no. Matrix evidence: "grep
-rniE 'import.*csv|csv.*import|spreadsheet|xlsx' lib/ src/: no hits; the only
'import'/'reimport' functionality in this app re-loads the app's own bundled
developer configuration (registers/schemas), not user data from an external
file".

- LarpManager (yes): "larpmanager/utils/io/upload/ (writing, registration, experience, relations, tickets CSV importers), larpmanager/urls/orga.py:1716 orga_upload, larpmanager/tests/unit/test_experience_upload_download.py".
- Kanka (yes): "campaign import from Kanka export zip or CSV (routes/campaigns/campaign.php:209-212; app/Services/CsvImportService.php)".
- pretix (partial): "src/pretix/control/urls.py:462 orders/import runs src/pretix/base/modelimport_orders.py (CSV import of orders and attendees ...); there are no characters to import".

**`ins-export-csv`**, "Export any list, such as characters or participants, to
a spreadsheet." Larpinq rates it no. Matrix evidence: "grep -rniE 'export|csv'
src/manifest.json src/views src/store/modules: no list-export action or button
found on any index/detail/report page".

- LarpManager (yes): "larpmanager/utils/io/download/core.py:34-73 CSV and ZIP exports used by character, registration, form and 12 experience list views, larpmanager/views/orga/writing.py:706 orga_export".
- pretix (yes): "src/pretix/base/exporters/orderlist.py:85,969 OrderListExporter writes orders and attendees to CSV or xlsx, with list exporters for customers, answers, waiting list, check-in lists (src/pretix/plugins/checkinlists/exporters.py:476) and more under src/pretix/base/exporters/".

All three are OpenRegister import and export turned on in larpinq's pages.

## Affected Projects

- [ ] Project: `larpinq`: exportable flags on the schemas, export and import options on the index pages, and campaign export and import actions.

## Scope

### In Scope

- Every larpinq schema marked exportable; the Export menu (CSV and Excel) on every index page, exporting the current filter and honouring each user's field rules.
- Import (CSV and Excel) on the Characters and Players index pages for game masters, with a template per schema and OpenRegister's error report.
- "Export campaign": one Excel workbook with a sheet per larpinq schema, for game masters, from the Worlds page; "Import campaign": the same workbook into an empty or existing larpinq register, matching sheets to schemas and upserting by id.

### Out of Scope

- Files (portraits, map images) in the campaign export; they live in Nextcloud Files under the register folder and are backed up with Nextcloud.
- Importers for other tools' formats (a Kanka zip, a LarpManager ZIP).
- Scheduled exports (row `ins-scheduled-exports`, deferred).

## Approach

Declarative: `exportable` on the schemas in a register fragment, and
`allowExport`, `showMassImport`, `exportFormats` and header actions on the
manifest pages; the actions call OpenRegister's register-level export and
import endpoints. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/admin-import-export.json` (new): `exportable: true` on each schema.
- `src/manifest.json`: index pages gain `allowExport`; Characters and Players gain import; Settings (Worlds) index gains the two campaign actions (existing pages, edited in place).

## Cross-Project Dependencies

OpenRegister (spec `data-import-export`, status in progress on development):
`GET /api/objects/{register}/{schema}/export` in CSV and Excel with property
rules applied, register-wide Excel export with one sheet per schema,
`POST /api/objects/{register}/import` with multi-sheet matching, templates at
`GET /api/objects/{register}/{schema}/template`, and RBAC on both.
`@conduction/nextcloud-vue` `CnIndexPage` `allowExport`, `showMassImport`,
`exportFormats`.

## Risks

### Risk 1: An import overwrites good data
**Severity:** Medium. **Mitigation:** import is for game masters only; OpenRegister reports created, updated and unchanged counts and a downloadable error file; the Worlds page advises a campaign export before a campaign import.

### Risk 2: A player exports what they may not see
**Severity:** Low. **Mitigation:** OpenRegister applies property rules to export headers and rows, so a player's export carries only what they can read (see `characters-player-visibility`).

## Rollback Strategy

Revert the manifest options and the fragment; nothing is stored by the change.

## Open Questions

None.
