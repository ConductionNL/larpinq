---
kind: code
---

# Dashboard widget hygiene: dead custom widgets, hardcoded colors, raw URL fetch

## Why

The dashboard migrated to ADR-036 declarative widgets (`type: "stat"` /
`type: "object-list"` in `src/manifest.json`), but the migration left
three hygiene debts behind:

1. **Dead custom-widget components.** `src/views/dashboard/DashboardKpi.vue`
   (124 lines) and `src/views/dashboard/DashboardRecentList.vue` are
   registered in `src/registry.js:29-30` but referenced by NOTHING: the
   Dashboard page's `slots` map contains only
   `"widget-skill-usage": "DashboardSkillUsage"` and its widgets are all
   declarative `stat` / `object-list` types rendered by the manifest
   engine. The superseded components ship in every bundle and their
   registry entries suggest they are live. DashboardKpi even documents a
   stale contract ("reads the count from the shared object store's
   pagination metadata") that no longer drives anything.
2. **Hardcoded hex colors in the manifest.** All four stat widgets carry
   `"valueColor": "#0082c9"` (`src/manifest.json:85-88`). Fleet rule
   (ADR-010 / ADR-004): NC CSS variables only, no hardcoded colors — the
   hex breaks NL Design System theming and dark mode.
3. **Raw URL + global token in DashboardActions.**
   `src/views/dashboard/DashboardActions.vue:119-120` fetches schemas via
   a hand-built path string
   `fetch('/index.php/apps/openregister/api/schemas/' + …)` with the
   global `OC.requestToken`. ADR-004 requires `generateUrl()` from
   `@nextcloud/router` and `getRequestToken()` from `@nextcloud/auth`
   (this very app already does it right in `src/services/graphql.js:8-9`).
   The literal path 404s on any instance served from a sub-directory or
   without `index.php` in the URL.

None of this is covered by the active changes: `larpinq-manifest-tier-4`
audits registry `kind:` fields (not dead entries), and `adopt-apphost` is
backend-only.

## What Changes

- Delete `src/views/dashboard/DashboardKpi.vue` and
  `src/views/dashboard/DashboardRecentList.vue`; remove their imports and
  entries from `src/registry.js`.
- Remove the `"valueColor": "#0082c9"` keys from the four stat widgets in
  `src/manifest.json` so the renderer's themed default applies (or, if a
  color override is genuinely wanted, use the CSS-variable form the
  renderer supports — never a hex literal).
- Rework `DashboardActions.loadSchema()` to build the URL with
  `generateUrl('/apps/openregister/api/schemas/{id}', { id })` and send
  `requesttoken: getRequestToken()`, mirroring `src/services/graphql.js`.
- Spec sync: `dashboard-analytics-widgets` — the KPI/recent-list
  requirements are re-anchored on the declarative manifest widgets; the
  transport requirement gains the router/auth-helper MUST.

Not BREAKING: no user-visible behavior changes on standard installs;
sub-path installs gain a working quick-create schema fetch.
