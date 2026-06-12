# Tasks — retrofit-2026-05-26-larpingapp-display-capabilities

## 1. Annotate dashboard widget icon resolution

- [x] task-1 Annotate `DashboardKpi::iconComponent` against REQ-DWI-001 (KPI widget icon set) — `src/views/dashboard/DashboardKpi.vue:73` carries `@spec openspec/changes/retrofit-2026-05-26-larpingapp-display-capabilities/tasks.md#task-1`
- [x] task-2 Annotate `DashboardRecentList::iconComponent` against REQ-DWI-002 (recent-items widget icon set) — `src/views/dashboard/DashboardRecentList.vue:94` carries `@spec openspec/changes/retrofit-2026-05-26-larpingapp-display-capabilities/tasks.md#task-2`

## 2. Validate

- [x] task-3 Coverage verified via the in-repo Gate-16 script
  `hydra/scripts/lib/check_spec_coverage.py` (the production
  successor to the legacy `/tmp/csc.py` orchestrator stub). The
  gate is diff-scoped per ADR-020 so untouched legacy methods stay
  out of the denominator; the two getters this retrofit annotates
  carry the required `@spec` lines (`DashboardKpi.vue:73` →
  `retrofit-2026-05-26-larpingapp-display-capabilities/tasks.md#task-1`
  and `DashboardRecentList.vue:94` → `…#task-2`) — confirmed via
  `grep -n '@spec' src/views/dashboard/Dashboard{Kpi,RecentList}.vue`.
  No uncovered methods are attributable to this change.
