---
status: approved
---

# Larping Skill Widget — Design

## Architecture

The skill widget is a Vue component (`SkillUsageChart.vue`) embedded in the dashboard via `CnDashboardPage`'s widget slot system. It uses VueApexCharts for rendering and OpenRegister's GraphQL faceting API for data.

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| View | `SkillUsageChart.vue` | Donut chart showing skill distribution |
| View | `DashboardIndex.vue` | Hosts the widget in `#widget-skill-usage` slot |
| Service | `services/graphql.js` | GraphQL query helper for OpenRegister |
| Store | `settings.js` | Checks OpenRegister configuration status |
| Library | VueApexCharts | Chart rendering |

## Data Flow

1. Component checks if character source is configured for OpenRegister via settings store
2. Sends GraphQL query: `{ character(first: 1, facets: ["skills"]) { totalCount facets } }`
3. Extracts skill facet buckets from response
4. Sorts by count descending, takes top 10
5. Groups remaining into "Other" slice
6. Renders as donut chart with theme-aware colors

## Theme Support

- Detects dark mode via `document.body.dataset.themeDark`
- Falls back to `prefers-color-scheme: dark` media query
- Chart background is transparent
- Legend positioned at bottom
