# data-portability Specification

## Purpose

Every larpinq list exports to a spreadsheet, characters and players import from
one, and a whole campaign goes out and back in one workbook, all through
OpenRegister. From larpinq matrix rows `ins-export-csv`, `adm-import-data` and
`adm-backup-export`.

## ADDED Requirements

### Requirement: Every list exports to CSV or Excel (REQ-AIE-001)

Every larpinq index page SHALL offer an export of the current list, with its
filter, to CSV and Excel, and the export MUST contain only fields the user may
read.

#### Scenario: A game master exports the participants

- GIVEN the Characters list filtered on world Aldmoor
- WHEN a game master chooses Export and CSV
- THEN a CSV downloads with the Aldmoor characters and their columns

### Requirement: Game masters import characters and players (REQ-AIE-002)

The Characters and Players pages SHALL let game masters import CSV or Excel
files, offer a template per schema, and report created, updated, unchanged and
failed rows with a downloadable error file. Other users MUST NOT be offered the
import.

#### Scenario: Moving from a spreadsheet

- GIVEN a game master downloads the Players template and fills in two players
- WHEN she imports the file on the Players page
- THEN two players are created and the result shows 2 created, 0 failed

### Requirement: A campaign exports and imports as one workbook (REQ-AIE-003)

Game masters SHALL be able to export every larpinq schema as one Excel
workbook with a sheet per schema, and import such a workbook, with each sheet
matched to its schema and objects upserted by id.

#### Scenario: A backup before the season

- GIVEN world Aldmoor with characters, skills, items and events
- WHEN a game master chooses "Export campaign" on the Worlds page
- THEN a workbook downloads with one sheet per larpinq schema
- AND importing it into an empty larpinq register recreates the objects
