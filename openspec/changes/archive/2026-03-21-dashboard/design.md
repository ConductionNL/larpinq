---
status: approved
---

# Dashboard — Design

## Architecture

The dashboard is a Vue SPA entry point served by `DashboardController.page()`. It uses the `CnDashboardPage` component from `@conduction/nextcloud-vue` for a grid-based widget layout.

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| PHP Controller | `DashboardController` | Serves `index` TemplateResponse for route `/` |
| View | `DashboardIndex.vue` | Dashboard with KPI cards, recent lists, skill chart |
| View | `SkillUsageChart.vue` | Donut chart via VueApexCharts + GraphQL faceting |
| Store | `object.js` | Fetches character/event/item/player counts |
| Router | `router/index.js` | `/` → Dashboard route |
| Navigation | `MainMenu.vue` | Dashboard nav item with ViewDashboard icon |

## Widget Layout

- 4 KPI cards: Characters, Events, Items, Players (counts from pagination)
- Recent Characters list (top 5)
- Recent Events list (top 5)
- Skill Usage donut chart (top 10 skills by character count)
- Quick-create buttons: New Character, New Item, New Condition

## Annotations

- `@NoAdminRequired` + `@NoCSRFRequired` on `page()` method
- Dashboard is the default route (`/`)
- Uses CSS variables for theming compatibility (no hardcoded colors)
