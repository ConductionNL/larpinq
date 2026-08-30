# dashboard-analytics-widgets — delta for dashboard-widget-hygiene

## ADDED Requirements

### Requirement: Declarative Widgets Are the Only KPI and Recent-List Surface

The dashboard's KPI counts and recent-item lists MUST be rendered
exclusively by the declarative manifest widgets (`type: "stat"` and
`type: "object-list"` on the Dashboard page in `src/manifest.json`). The
app MUST NOT ship or register bespoke Vue components that duplicate these
widget types; the component registry (`src/registry.js`) MUST contain only
entries that are actually referenced from the manifest (slot keys,
`actionsComponent`, or section components).

#### Scenario: Registry holds no unreferenced widget components

- GIVEN the built app at HEAD
- WHEN the entries of `src/registry.js` are cross-checked against `src/manifest.json`
- THEN every registry entry MUST be referenced by a manifest slot key, `actionsComponent`, or section component
- AND no component duplicating a declarative `stat` or `object-list` widget MUST exist under `src/views/dashboard/`

### Requirement: Manifest Widgets Use Theme Tokens, Never Hex Literals

Dashboard widget definitions in `src/manifest.json` MUST NOT contain
hardcoded hex color values. Widget coloring MUST come from the renderer's
themed defaults or NC CSS variables so NL Design System theming and dark
mode apply.

#### Scenario: Stat widgets carry no hex colors

- GIVEN the Dashboard page definition in `src/manifest.json`
- WHEN its widget `content` blocks are inspected
- THEN no `valueColor` (or any other color key) MUST hold a `#rrggbb` literal

## MODIFIED Requirements

### Requirement: Dashboard Transport Uses Nextcloud Router and Auth Helpers

Every HTTP request issued by dashboard components MUST build its URL via
`generateUrl()` from `@nextcloud/router` and authenticate via
`getRequestToken()` from `@nextcloud/auth`. Literal `/index.php/...`
paths and the global `OC.requestToken` MUST NOT be used.

#### Scenario: Quick-create schema fetch works on a sub-path install

- GIVEN a Nextcloud instance served from `https://host/nextcloud/`
- WHEN the dashboard actions component loads the character schema for the quick-create dialog
- THEN the request URL MUST be produced by `generateUrl('/apps/openregister/api/schemas/{id}', …)`
- AND the request MUST carry the token from `getRequestToken()`
- AND the schema MUST load successfully
