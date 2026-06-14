# retrofit-2026-05-26-larpingapp-display-capabilities

## Why

The dashboard KPI and recent-items widgets resolve their leading icon from a
config-supplied `iconName` against a locally-defined, closed icon map. That map
is the product definition of which icons a dashboard widget can present — a real
display capability, not framework glue. Under the earlier looser coverage policy
these getters were marked `@spec exclude` as "UI glue"; the stricter policy
requires a written spec for any method that encodes a closed set of product
values. This change reverse-specs the supported widget-icon sets.

The remaining excluded methods (Pinia store accessors, config passthroughs,
i18n wrappers, `provide()`, `window.open`/theme/ApexCharts formatters) stay
excluded — they are genuine plumbing with no encoded value-set.

## What Changes

- Document the closed set of icon names a KPI widget can render (`DashboardKpi`).
- Document the closed set of icon names a recent-items widget can render
  (`DashboardRecentList`).
- Replace the `@spec exclude` markers on the two `iconComponent` getters with
  `@spec` references to the new tasks. Annotation-only — no behavior change.

## Impact

- **Affected specs**: new capability `dashboard-widget-icons`
- **Affected code**: `src/views/dashboard/DashboardKpi.vue`,
  `src/views/dashboard/DashboardRecentList.vue` (docblock `@spec` annotations only)
- **Risk**: none — comment-only.
