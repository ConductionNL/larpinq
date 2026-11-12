# The dashboard spec says the KPI tiles it shows

## Why

The dashboard spec's "Dashboard Content" still says the page shows a welcome message and no data widgets. The page has shown count tiles since the manifest dashboard landed: Characters, Events, Items and Players, each a count over the larpinq register (`src/manifest.json`, widgets `kpi-characters`, `kpi-events`, `kpi-items`, `kpi-players`). The KPI scenarios sat under "Planned Analytics Dashboard". Ruben decided on 12 Nov 2026 (decision 392) that the spec follows the KPI tiles.

## What changes

- "Dashboard Content" says the dashboard opens with four count tiles, recent characters, upcoming events and a skill usage chart. It takes the KPI and the empty-data scenarios from the planned requirement.
- "Planned Analytics Dashboard" is removed. Its KPI and empty scenarios move into "Dashboard Content". The skill chart is `dashboard-analytics-widgets` REQ-004, and the character type split is on the Character roster report.

No code changes: this describes the page as it is.

## Matrix rows (`openspec/parity/capabilities.json`)

| row | requirement |
|---|---|
| `ins-dashboard` | Dashboard Content |
| `ins-dashboard-counts` | Dashboard Content |
