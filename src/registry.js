/**
 * LarpingApp v2 component registry (ADR-036).
 *
 * Kind-tagged map passed as the `registry` prop to CnAppRoot. Replaces the
 * deprecated `customComponents` prop.
 *
 * LarpingApp's manifest pages are typed primitives
 * (type: "index" / "detail" / "dashboard" / "settings"), so no `kind: "page"`
 * entries are needed here — the renderer resolves them directly via
 * `pageTypes`. The entries below are non-page kinds referenced from inside
 * typed pages via slot keys (page.slots[*]), page.actionsComponent, and
 * page.config.sections[*].component. CnPageRenderer's slot-override
 * resolution is kind-agnostic — any entry with a `component` field
 * resolves — so semantic kinds (widget / actions / section) document the
 * intent without affecting dispatch.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

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
