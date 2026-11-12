## MODIFIED Requirements

### Requirement: Dashboard Content

The dashboard MUST open with four count tiles in one row: Characters, Events, Items and Players. Each tile MUST show the number of objects of its schema in the larpinq register, filtered to the active world when one is chosen, and MUST open that entity's list when clicked. Below the tiles the dashboard MUST show the most recent characters, the upcoming events and a chart of skill usage by characters. The page MUST be the manifest page `Dashboard` of type `dashboard` on route `/`.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-020 | Dashboard MUST display a page header with text "Dashboard" | MUST | Implemented |
| DASH-021 | Dashboard MUST show count tiles for characters, events, items and players | MUST | Implemented |
| DASH-022 | Dashboard MUST show recent characters, upcoming events and skill usage | MUST | Implemented |
| DASH-023 | Each count tile MUST open its entity's list | MUST | Implemented |

#### Scenario: View dashboard content

- GIVEN 25 characters, 12 players, 5 events and 30 items exist
- WHEN the user opens the dashboard
- THEN the page shows the header "Dashboard"
- AND the tiles show Characters 25, Events 5, Items 30 and Players 12 in one responsive row

#### Scenario: Dashboard is minimal but functional

- GIVEN the user views the dashboard
- THEN the page shows only the count tiles, the recent characters, the upcoming events and the skill usage chart
- AND clicking the Characters tile opens the Characters list

#### Scenario: Dashboard after app install

@e2e exclude requires a clean-install Nextcloud environment; functional equivalent is covered by "user navigates to the app" with no data seeded; install-time behavior covered in PHPUnit

- GIVEN Larpinq was just installed and no data exists
- WHEN the admin navigates to the app
- THEN every count tile shows 0
- AND the skill usage chart shows its "No skill data available" state
- AND the sidebar is navigable to all entity views

## REMOVED Requirements

### Requirement: Planned Analytics Dashboard

**Reason**: the dashboard is built. Its KPI cards are the count tiles in "Dashboard Content", which also takes the empty-data scenario. The skill chart is `dashboard-analytics-widgets` REQ-004. The character type split is the "By type" chart on the Character roster report.

**Migration**: none. Read "Dashboard Content" and `dashboard-analytics-widgets` for what the page shows.
