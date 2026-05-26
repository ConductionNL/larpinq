// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// 5-kind component registry (v2 manifest pattern per hydra ADR-036).
//
// All page-level entries removed — manifest pages are typed primitives
// (type: "index" / "detail" / "dashboard" / "settings"), so the renderer
// resolves them directly without going through this registry.
//
// Remaining entries are non-page kinds referenced from INSIDE typed
// pages (page.slots[*], page.actionsComponent, page.config.sections[*].component):
//   - DashboardKpi          — generic KPI widget, driven by config.objectType
//   - DashboardRecentList   — generic recent-items list, driven by config.objectType
//   - DashboardSkillUsage   — skill-usage chart widget
//   - DashboardActions      — header actions (create dialogs + refresh)
//   - GameSettingsSection   — settings section body (still bespoke)
//
// Future cleanup: decompose GameSettingsSection into version-info +
// register-mapping built-in settings widgets.

import DashboardKpi from './views/dashboard/DashboardKpi.vue'
import DashboardRecentList from './views/dashboard/DashboardRecentList.vue'
import DashboardSkillUsage from './views/dashboard/DashboardSkillUsage.vue'
import DashboardActions from './views/dashboard/DashboardActions.vue'
import GameSettingsSection from './views/settings/Settings.vue'

export default {
	DashboardKpi: { kind: 'widget', component: DashboardKpi },
	DashboardRecentList: { kind: 'widget', component: DashboardRecentList },
	DashboardSkillUsage: { kind: 'widget', component: DashboardSkillUsage },
	DashboardActions: { kind: 'actions', component: DashboardActions },
	GameSettingsSection: { kind: 'section', component: GameSettingsSection },
}
