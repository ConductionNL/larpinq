---
retrofit: true
---

# Dashboard Analytics Widgets

## Purpose

@e2e exclude larpingapp Vue SPA fails to mount at localhost:8080; DashboardKpi/DashboardRecentList/DashboardActions/SkillUsageChart components are inaccessible; GraphQL transport scenarios are JS unit-test scope

The larpingapp dashboard renders a set of manifest-driven analytics widgets that
surface live counts, recent items, quick-create actions, and a skill-distribution
chart. These widgets were previously described only as "Planned" REQs in the
`dashboard` capability (DASH-060..066); the code now realizes them as discrete
Vue components (`DashboardKpi`, `DashboardRecentList`, `DashboardActions`,
`SkillUsageChart`) backed by a thin GraphQL transport (`services/graphql.js`).
This capability specifies the observed behavior of those realized widgets.

**Key source files:**
- `src/views/dashboard/DashboardKpi.vue` — single-count KPI tile
- `src/views/dashboard/DashboardRecentList.vue` — recent-items list with view-all link
- `src/views/dashboard/DashboardActions.vue` — quick-create + refresh header actions
- `src/views/dashboard/SkillUsageChart.vue` — skill-distribution donut chart
- `src/services/graphql.js` — OpenRegister GraphQL transport

## Requirements

### REQ-001: KPI Count Tile

A KPI widget MUST render the live total count for its configured object type by
reading the shared object store's pagination metadata, so the tile reflects
whatever the index pages have already fetched.

#### Scenario: KPI reflects store pagination total

- WHEN the object store reports a pagination total of 25 for the `character` type
- THEN the `DashboardKpi` configured with `objectType: 'character'` MUST display `25`
- AND WHEN no pagination metadata exists for the type
- THEN the tile MUST display `0`

### REQ-002: Recent Items List

A recent-items widget MUST render the most-recent items of its configured object
type from the shared object store, expose a "view all ({count})" affordance when
the total exceeds the display limit, and navigate to the configured index route
when an item or the view-all control is activated.

#### Scenario: Recent list renders and navigates

- GIVEN the object store collection for `event` holds 8 results and a pagination total of 8
- AND the widget is configured with `limit: 5` and `indexRoute: 'Events'`
- WHEN the widget renders
- THEN at most 5 items MUST be shown
- AND a "view all (8)" button MUST be displayed because the total exceeds the limit
- WHEN the user clicks an item or the view-all button
- THEN the router MUST navigate to the `Events` route

### REQ-003: Quick-Create and Refresh Actions

The dashboard actions widget MUST load the OpenRegister schemas for the
character, item, and condition types, refresh the dashboard collections, and
create a new object from a submitted form — routing to the new object's detail
page on success.

#### Scenario: Refresh loads schemas and collections

- WHEN the actions widget mounts
- THEN it MUST fetch the character, item, and condition schemas from OpenRegister
- AND it MUST fetch the character, event, item, and player collections into the store
- AND a missing/failed schema fetch MUST resolve to `null` without throwing

#### Scenario: Create object and route to detail

- GIVEN the user submits the character create form
- WHEN `onCreate('character', formData, 'CharacterDetail', onSuccess)` runs
- THEN the object store MUST save the object
- AND on a successful save the dialog MUST close and the router MUST push the `CharacterDetail` route with the new id

### REQ-004: Skill Distribution Chart

The skill-usage widget MUST query character skill facets via GraphQL, render the
top skills as an ApexCharts donut series (bucketing the remainder into an "Other"
slice), and degrade gracefully when the character source is not OpenRegister, the
query fails, or no skill data exists.

#### Scenario: Chart aggregates skill facets

- GIVEN the character source is configured for OpenRegister
- WHEN the widget fetches skill facets and receives more than 10 buckets
- THEN the top 10 skills (by count) MUST become chart series/labels
- AND the remaining buckets MUST be summed into a single "Other" slice
- WHEN the character source is not OpenRegister
- THEN the widget MUST show a "Configure OpenRegister data source" empty state instead of the chart
- WHEN the GraphQL query throws
- THEN the widget MUST surface the error with a Retry control

### REQ-005: GraphQL Transport

A GraphQL transport helper MUST POST queries to the OpenRegister GraphQL endpoint
with the Nextcloud CSRF request token and same-origin credentials, and translate
HTTP and GraphQL error responses into thrown Errors.

#### Scenario: Query posts with auth and maps errors

- WHEN `queryGraphQL(query, variables)` is called
- THEN it MUST POST to the OpenRegister GraphQL endpoint with the `requesttoken` header and `same-origin` credentials
- AND a 401 response MUST throw an authentication error
- AND a 429 response MUST throw a rate-limit error referencing the Retry-After header
- AND a GraphQL `errors` payload with no `data` MUST throw the first error message
