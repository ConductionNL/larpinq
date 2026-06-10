# Tasks — retrofit-2026-05-26-larpingapp-display-capabilities

## 1. Annotate dashboard widget icon resolution

- [x] task-1 Annotate `DashboardKpi::iconComponent` against REQ-DWI-001 (KPI widget icon set) — `src/views/dashboard/DashboardKpi.vue:73` carries `@spec openspec/changes/retrofit-2026-05-26-larpingapp-display-capabilities/tasks.md#task-1`
- [x] task-2 Annotate `DashboardRecentList::iconComponent` against REQ-DWI-002 (recent-items widget icon set) — `src/views/dashboard/DashboardRecentList.vue:94` carries `@spec openspec/changes/retrofit-2026-05-26-larpingapp-display-capabilities/tasks.md#task-2`

## 2. Validate

- [~] task-3 `python3 /tmp/csc.py . --mode report` shows this app at zero uncovered — coverage script `/tmp/csc.py` is an external orchestrator tool not present in the build sandbox; the underlying `@spec` annotations on both `iconComponent` getters are in place (task-1 / task-2) so when the report tool runs it will record zero uncovered for these REQs
