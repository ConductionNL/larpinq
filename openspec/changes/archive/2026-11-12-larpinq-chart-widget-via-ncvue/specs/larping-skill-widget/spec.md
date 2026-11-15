# Larping Skill Widget — Chart Rendering via Shared CnChartWidget Delta

**Spec refs**: `larping-skill-widget` (skill-usage donut chart rendering + NL Design theming
requirements)

## MODIFIED Requirements

### Requirement: Skill Usage Chart Rendering

The skill-usage donut chart MUST render via `@conduction/nextcloud-vue`'s `CnChartWidget`
component rather than mounting `vue-apexcharts` directly. Chart theming (colors, fonts, grid,
legend text) MUST come from `CnChartWidget`'s CSS-variable-driven defaults, not from a
component-local dark/light detector reading `document.body.dataset` or `matchMedia`.

**Feature tier**: MVP

#### Scenario: Donut chart renders through the shared chart component

- GIVEN the skill-usage GraphQL facet query has returned bucketed skill counts
- WHEN `SkillUsageChart.vue` renders the donut chart
- THEN it MUST do so via `CnChartWidget` with `type="donut"`
- AND MUST NOT import or mount `vue-apexcharts` directly in this component

#### Scenario: Chart colors adapt to theme via CSS variables, not JS detection

- GIVEN a custom NL Design theme changes `--color-main-text` / `--color-primary-element`
- WHEN the donut chart renders in light or dark mode
- THEN the chart's text and palette colors MUST update via the CSS variables `CnChartWidget`
  reads
- AND the widget MUST NOT contain a local `isDarkTheme`-style computed property that reads
  `document.body.dataset` or `window.matchMedia` to pick a chart theme mode
