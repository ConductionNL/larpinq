# Tasks — larpinq-chart-widget-via-ncvue

## 1. Preconditions

- [ ] 1.1 Read `nextcloud-vue/docs/components/cn-chart-widget.md` and `CnChartWidget.vue`'s prop list to confirm the exact prop names for `series`, `labels`, `colors`, `dataLabels` formatter, and tooltip formatter override (component may expose a raw `options` merge-in prop rather than per-facet props — confirm before editing)
- [ ] 1.2 Confirm `CnChartWidget` is already exported from `@conduction/nextcloud-vue`'s barrel (`src/index.js`) and importable the same way other Cn* components are imported elsewhere in this app (e.g. `NcButton`/`NcLoadingIcon` import pattern already in `SkillUsageChart.vue:34`)

## 2. Swap the chart implementation

- [ ] 2.1 In `src/views/dashboard/SkillUsageChart.vue`, replace the `VueApexCharts` import and `<VueApexCharts>` template usage with `CnChartWidget` (`type="donut"`, `:series="chartSeries"`, `:labels="skillLabels"`, `:height="280"`)
- [ ] 2.2 Move the `Math.round(val) + '%'` dataLabel formatter and the `val + ' ' + t('larpinq', 'characters')` tooltip formatter onto whatever override mechanism `CnChartWidget` exposes (per 1.1) — if it does not support per-instance formatter overrides, keep the minimal delta needed as a documented exception in this task and note the nc-vue follow-up
- [ ] 2.3 Delete the local `isDarkTheme` computed (`SkillUsageChart.vue:107-116`) and the `theme: { mode: ... }` key from `chartOptions` — `CnChartWidget` themes via CSS variables and needs neither
- [ ] 2.4 Remove the direct `vue-apexcharts` import from `SkillUsageChart.vue`'s `components` map; keep `package.json`'s `vue-apexcharts`/`apexcharts` dependency only if another component in this app still uses it directly (grep first — expected: none after this change, but do not remove the dependency speculatively without confirming)

## 3. Tests

- [ ] 3.1 Update/extend `tests/vitest/graphql.spec.js` or add a component test asserting `SkillUsageChart` renders `CnChartWidget` with the expected `series`/`labels` props for a given facet response
- [ ] 3.2 Re-run the existing dashboard e2e coverage (`tests/e2e/spec-coverage/spa-ui.spec.ts` skill-usage scenarios, if any) to confirm the donut chart still renders after the swap

## 4. Spec sync

- [ ] 4.1 `openspec/specs/larping-skill-widget/spec.md` — update the skill-usage chart requirement to reference `CnChartWidget` instead of a bespoke `VueApexCharts` mount, and drop/rewrite the CSS-computed-style `@e2e exclude` entries that describe the old manual theme detection if they no longer apply
- [ ] 4.2 `@spec` annotations on the changed computed properties pointing at this change (gate-16)

## 5. Quality

- [ ] 5.1 `npm run build` succeeds with no missing-import errors
- [ ] 5.2 Existing lint/test suite green; hydra gates unaffected (no forbidden patterns introduced)
