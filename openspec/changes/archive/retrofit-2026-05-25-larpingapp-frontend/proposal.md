# Retrofit — larpingapp frontend (dashboard analytics widgets + settings management UI)

## Why

The 2026-05-24 Bucket 1 retrofit annotated only the PHP `lib/` layer and explicitly
deferred the frontend. The gate-16 coverage report still flags the Vue dashboard
analytics widgets and the settings store/UI layer as missing `@spec` traceability.
These are real, observable behaviors — not previously specified — so they are
reverse-specified here as two new frontend capabilities rather than annotated against
existing REQs.

## What Changes

- New capability `dashboard-analytics-widgets` (5 REQs) describing the realized
  KPI tile, recent-items list, quick-create/refresh actions, skill-distribution
  donut chart, and the OpenRegister GraphQL transport. These realize behavior the
  `dashboard` spec previously listed only as "Planned" (DASH-060..066).
- New capability `settings-management-ui` (5 REQs) describing the Pinia settings
  store lifecycle, store bootstrap + object-type registration, the admin
  `Settings.vue` load/persist/cascade behavior, and the re-import action shared by
  the admin panel and the in-app user-settings dialog. The backend REST contract
  already exists in `admin-settings` / `user-settings`; this specifies the frontend
  layer that drives it.
- Annotates the realizing Vue/JS methods with
  `@spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-N`.
- Marks genuine UI/framework glue (store accessors, trivial config getters,
  icon-map lookups, translate/formatter passthroughs, lifecycle/provide hooks,
  theme detection) with `@spec exclude <reason>`.
- No code logic changes.

## Affected code units

dashboard-analytics-widgets:
- src/views/dashboard/DashboardKpi.vue::count
- src/views/dashboard/DashboardRecentList.vue::items, totalCount, goToIndex
- src/views/dashboard/DashboardActions.vue::loadSchema, refreshData, onCreate
- src/views/dashboard/SkillUsageChart.vue::fetchData, chartOptions, chartSeries
- src/services/graphql.js::queryGraphQL

settings-management-ui:
- src/store/modules/settings.js::fetchSettings, saveSettings, reimportConfiguration
- src/store/store.js::initializeStores
- src/views/settings/Settings.vue::loadSettings, saveAll, reimport, getRegisterLabel,
  getSchemaLabel, getSchemaOptions, registerOptions, handleSourceChange, handleRegisterChange
- src/views/settings/UserSettings.vue::reimport

## Source

gate-16 spec-coverage report (`csc.py --mode report`) generated 2026-05-25.
See [retrofit playbook](../../../.github/docs/claude/retrofit.md).
