# data-portability Specification

**Status**: implemented
**Scope**: larpinq
**OpenSpec changes**:
- `admin-import-export` (archived 2026-09-30)

## Purpose
Every larpinq list exports to a spreadsheet, characters and players import from
one, and a whole campaign goes out and back in one workbook, all through
OpenRegister. From larpinq matrix rows `ins-export-csv`, `adm-import-data` and
`adm-backup-export`.

The flag is set in `lib/Settings/register.d/admin-import-export.json` and pinned
by `tests/unit/Settings/AdminImportExportFragmentTest.php`; the manifest wiring,
the import gate and the campaign requests are pinned by
`tests/vitest/campaignPortability.spec.js`; the browser proof is
`tests/e2e/workflows/data-portability.workflow.spec.ts`.

## Requirements

### Requirement: Every list exports to CSV or Excel (REQ-AIE-001)

Every larpinq index page SHALL offer an export of the current list, with its
filter, to CSV and Excel, and the export MUST contain only fields the user may
read. Every larpinq schema SHALL be marked `configuration.exportable: true`.

#### Scenario: A game master exports the participants

- GIVEN the Characters list filtered on world Aldmoor
- WHEN a game master chooses Export and CSV
- THEN a CSV downloads with the Aldmoor characters and their columns

### Requirement: Characters and players import from a spreadsheet (REQ-AIE-002)

The Characters and Players pages SHALL let the people OpenRegister allows to
manage the larpinq register (administrators, while the register has no
`manage` rule) import CSV or Excel files, and report created, updated,
unchanged and failed rows. Other users MUST NOT be offered an import, and no
other larpinq list SHALL offer one.

#### Scenario: Moving from a spreadsheet

- GIVEN an administrator with a CSV of two players
- WHEN she imports the file on the Players page
- THEN two players are created and the result shows 2 created, 0 failed

#### Scenario: Only characters and players import

- GIVEN any larpinq list other than Characters and Players
- WHEN anyone opens its actions menu
- THEN it has no Import action
- AND a user who is not an administrator sees no Import on Characters and Players either

### Requirement: A campaign exports and imports as one workbook (REQ-AIE-003)

The Worlds page SHALL offer "Export campaign", which downloads every larpinq
schema as one Excel workbook with a sheet per schema holding the records the
user may read, and "Import campaign" for the people who may manage the
register, which imports such a workbook with each sheet matched to its schema
and objects upserted by id, and reports the counts.

#### Scenario: A backup before the season

- GIVEN world Aldmoor with characters, skills, items and events
- WHEN a game master chooses "Export campaign" on the Worlds page
- THEN a workbook downloads with one sheet per larpinq schema
- AND importing it as an administrator with "Import campaign" upserts the objects by id
