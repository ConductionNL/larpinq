---
kind: code
---

# Skill-usage chart reimplements the shared CnChartWidget instead of consuming it

## Why

`src/views/dashboard/SkillUsageChart.vue` imports `vue-apexcharts` directly
(`SkillUsageChart.vue:33,41`) and hand-rolls ~70 lines of ApexCharts wiring: chart type,
`chartOptions` (donut plot options, dataLabels formatter, tooltip formatter), and a manual
dark-mode detector (`isDarkTheme()`, `SkillUsageChart.vue:111-116`) that reads
`document.body.dataset.themeDark`/`themeLight` and `window.matchMedia` by hand.

`@conduction/nextcloud-vue` already ships exactly this as `CnChartWidget`
(`nextcloud-vue/src/components/CnChartWidget/CnChartWidget.vue`) — a wrapper around the same
`vue-apexcharts` peer dependency that:

- Sets ApexCharts' `foreColor`, `grid.borderColor`, `legend.labels.colors`, and the color
  palette entirely from Nextcloud CSS variables
  (`var(--color-main-text, #222)`, `var(--color-border, #ededed)`,
  `var(--color-primary-element, #0082c9)`, etc. — `CnChartWidget.vue:335-391`), so dark/light
  theming is automatic and CSS-driven — **no JS media-query or dataset polling needed**.
- Supports `type="donut"` (SkillUsageChart's exact chart kind) with a `series`/`labels`/`colors`
  prop contract that maps directly onto `skillCounts`/`skillLabels`.
- Is already a `package.json` dependency of `@conduction/nextcloud-vue`
  (`nextcloud-vue/package.json:80,90` — `apexcharts` + `vue-apexcharts`), consistent with the
  fleet rule "apexcharts from nc-vue" (feedback `shared-deps`).

Because larpinq bypasses `CnChartWidget` and drives `VueApexCharts` itself, it (a) carries and
maintains its own copy of chart-theming logic that already exists and is tested in the shared
library, (b) has a **manual dark-mode detector that duplicates and can drift from**
`CnChartWidget`'s CSS-variable approach (if Nextcloud ever changes how
`document.body.dataset.themeDark` is set, `SkillUsageChart.vue:111-116` breaks silently while
`CnChartWidget`'s CSS-variable colors would not), and (c) does not benefit from any future
`CnChartWidget` improvement (loading states, `dataSource`/date-range integration, accessibility
fixes) without a manual backport.

This is not covered by the active `dashboard-widget-hygiene` change (dead widgets, hardcoded
manifest colors, raw URL fetch) — it is about the one **live, referenced** chart widget
reimplementing a component the shared library already provides.

## What Changes

- Replace `SkillUsageChart.vue`'s direct `VueApexCharts` usage with `CnChartWidget` from
  `@conduction/nextcloud-vue`, passing `type="donut"`, `series="skillCounts"`,
  `labels="skillLabels"`, and the `t('larpinq', '{val}%')`/`characters` tooltip formatting via
  `CnChartWidget`'s supported options-merge prop (or its documented override slot/prop — see
  `docs/components/cn-chart-widget.md` in nextcloud-vue for the exact contract).
- Delete `SkillUsageChart.vue`'s local `isDarkTheme` computed and `chartOptions`' hand-rolled
  `theme.mode`/color logic — `CnChartWidget` handles theming from CSS variables.
- Keep `SkillUsageChart.vue`'s own responsibilities unchanged: the loading/error/empty states,
  the `openRegisterConfigured` gate, and the `fetchData()` GraphQL facet query stay exactly as
  they are — only the chart-rendering internals move to the shared component.

Not BREAKING: same visual widget, same GraphQL data flow; only the rendering implementation
changes.
