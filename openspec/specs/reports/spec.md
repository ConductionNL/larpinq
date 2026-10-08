---
status: implemented
retrofit: true
---

# Reports

## Purpose

Game masters read three reports over the campaign's own data: the character roster, experience and progression, and the world content. Each report is a declarative `type: "dashboard"` page in `src/manifest.json` over larpinq's own register. There is no bespoke component and no larpinq controller: the widgets (`stat`, `chart`, `object-table`) are the shared nextcloud-vue dashboard widgets, and the counts, sums and group-bys come from OpenRegister's aggregation endpoint. This spec was written after the fact (spec round of 7 October 2026) and describes what the code does today.

**Source files:**
- `src/manifest.json` menu entry `ReportsMenu` (section `settings`, the Advanced foldout of the navigation)
- `src/manifest.json` page `Reports` (route `/reports`, type `reports`): the landing page with one card per report
- `src/manifest.json` page `CharacterRosterReport` (route `/reports/characters`)
- `src/manifest.json` page `ProgressionReport` (route `/reports/progression`)
- `src/manifest.json` page `WorldContentReport` (route `/reports/content`)

Capability rows: `ins-roster-report`, `ins-progression-report`, `ins-content-report`.

@e2e exclude covered in the browser by tests/e2e/app-chrome.spec.ts ("Reports lists the three reports", "the roster report renders real numbers, not empty cards", "the world report reads the prefixed schema slugs, not the seed keys", "the progression report is reachable and titled") and tests/e2e/workflows/plots-and-writing.workflow.spec.ts (the writing-step widgets); those tests do not yet carry per-scenario @e2e anchors, which belong in a code change, not in this docs-only spec round.

## Requirements

### Requirement: The app MUST offer a reports landing page that lists every report

The app MUST provide a `Reports` page at route `/reports`, reached from the `Reports` entry in the navigation's settings section. The page MUST show one card per report with a label, a one-line description and an icon, grouped under the categories "Characters" (character roster, progression) and "The world" (world content). Choosing a card MUST open that report's page.

#### Scenario: A game master opens the reports list

- WHEN a game master opens the Advanced foldout of the navigation and chooses Reports
- THEN the page at `/reports` MUST show the cards "Character roster" and "Progression" under "Characters"
- AND the card "World content" under "The world"

#### Scenario: A card opens its report

- GIVEN the reports landing page is open
- WHEN the game master chooses the "Progression" card
- THEN the app MUST navigate to `/reports/progression`

### Requirement: The character roster report MUST count characters by type, approval and writing step

The `CharacterRosterReport` page at `/reports/characters` MUST show, over the `character` schema of the `larpinq` register:
- stat tiles for the total number of characters, the number of player characters (`type` is `player`) and the number awaiting approval (`approved` is `no`), each linking to the Characters list;
- donut charts of characters grouped by `type`, by `approved` and by `writingStep`;
- a table of characters still in writing (`writingStep` is `draft` or `ready`) with name, writer and writing step, sorted by writer, at most 20 rows, each row opening the character;
- a table of the eight most recently created characters with a link to the full Characters list.

Every filter on this page MUST be a scalar equality or a list of allowed values, because OpenRegister's aggregation endpoint evaluates no other kind.

#### Scenario: The roster shows real counts

- GIVEN the register holds 12 characters, 9 of type `player`, 3 with `approved` set to `no`
- WHEN a game master opens `/reports/characters`
- THEN the tiles MUST read 12 characters, 9 player characters and 3 awaiting approval
- AND the "By type" donut MUST show a slice per character type

#### Scenario: Characters still in writing are listed with their writer

- GIVEN two characters have `writingStep` `draft` and one has `writingStep` `approved`
- WHEN a game master opens the roster report
- THEN the "Not yet approved in writing" table MUST list the two draft characters with their writer
- AND MUST NOT list the approved one

#### Scenario: An empty campaign shows empty states, not errors

- GIVEN the register holds no characters
- WHEN a game master opens the roster report
- THEN each chart MUST read "No characters yet"

### Requirement: The progression report MUST show experience awarded and who earned it

The `ProgressionReport` page at `/reports/progression` MUST show, over the `xpAward` schema:
- a stat tile with the number of awards and a stat tile with the sum of `amount`, both linking to the XP Awards list;
- a bar chart of the summed `amount` per character, limited to the top ten with the rest in an "other" bucket, labelled with the character's `name`;
- a table of the eight most recent awards (date, character, experience, reason), sorted by `awardedAt` descending, with a link to all awards.

#### Scenario: Experience is summed per character

- GIVEN character "Mila" received awards of 3 and 2 experience and "Sanne" one award of 4
- WHEN a game master opens `/reports/progression`
- THEN the awards tile MUST read 3 and the experience tile MUST read 9
- AND the "Per character" chart MUST show Mila at 5 and Sanne at 4, labelled by name

#### Scenario: No awards yet

- GIVEN no xpAward exists
- WHEN a game master opens the progression report
- THEN the chart MUST read "Nothing awarded yet"

### Requirement: The world content report MUST show what the world holds and what characters carry

The `WorldContentReport` page at `/reports/content` MUST show stat tiles counting skills, items, conditions and effects, each linking to its list, and two donut charts over the `character` schema: items carried (grouped by the `items` array) and conditions held (grouped by the `conditions` array), each limited to the top ten with an "other" bucket and labelled with the item's or condition's `name`. The widgets MUST name the registered schema slugs (`larping_skill`, `larping_item`), not the seed keys, because schema slugs are global on a shared OpenRegister.

#### Scenario: Counts use the registered slugs

- GIVEN the register holds 40 skills under the schema slug `larping_skill`
- WHEN a game master opens `/reports/content`
- THEN the Skills tile MUST read 40, not an empty value

#### Scenario: What characters actually carry

- GIVEN three characters carry the item "Healing potion" and one carries "Rope"
- WHEN a game master opens the world content report
- THEN the "Items carried by characters" donut MUST show "Healing potion" at 3 and "Rope" at 1
