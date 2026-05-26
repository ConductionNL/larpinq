// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// Custom-component registry (v1 pattern) for the manifest-driven app shell.
//
// All page-level entries are typed primitives. Remaining entries are
// focused widget / section / actions components referenced from
// inside typed pages:
//   - Dashboard widgets (KPIs, recent lists, skill-usage chart) — slot resolution
//   - Dashboard actions (header buttons + create dialogs) — actionsComponent resolution
//   - Game Settings section — section.component resolution

import DashboardKpi from './views/dashboard/DashboardKpi.vue'
import DashboardRecentList from './views/dashboard/DashboardRecentList.vue'
import DashboardSkillUsage from './views/dashboard/DashboardSkillUsage.vue'
import DashboardActions from './views/dashboard/DashboardActions.vue'
import GameSettingsSection from './views/settings/Settings.vue'

export default {
	DashboardKpi,
	DashboardRecentList,
	DashboardSkillUsage,
	DashboardActions,
	GameSettingsSection,
}
