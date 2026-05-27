/**
 * LarpingApp v2 component registry (ADR-036).
 *
 * Kind-tagged map passed as the `registry` prop to CnAppRoot. CnPageRenderer
 * resolves each manifest page's `component` string against entries whose
 * `kind === "page"` (with precedence over the deprecated `customComponents`
 * prop, which LarpingApp no longer ships).
 *
 * LarpingApp's manifest pages are typed primitives
 * (type: "index" / "detail" / "dashboard" / "settings"), so the renderer
 * resolves them directly via `pageTypes` — no page-kind entries are needed
 * here. The remaining non-page entries are referenced from INSIDE typed
 * pages (page.slots[*], page.actionsComponent, page.config.sections[*].component):
 *   - DashboardKpi          — generic KPI widget, driven by config.objectType
 *   - DashboardRecentList   — generic recent-items list, driven by config.objectType
 *   - DashboardSkillUsage   — skill-usage chart widget
 *   - DashboardActions      — header actions (create dialogs + refresh)
 *   - GameSettingsSection   — settings section body (still bespoke)
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

import DashboardKpi from './views/dashboard/DashboardKpi.vue'
import DashboardRecentList from './views/dashboard/DashboardRecentList.vue'
import DashboardSkillUsage from './views/dashboard/DashboardSkillUsage.vue'
import DashboardActions from './views/dashboard/DashboardActions.vue'
import GameSettingsSection from './views/settings/Settings.vue'

/**
 * Wrap a Vue component into the v2 registry shape required by CnAppRoot's
 * `registry` prop (`kind: "page"` is the discriminator CnPageRenderer keys
 * page dispatch off — `kind: "widget"`/`"modal"`/`"form-field"`/
 * `"cell-renderer"` entries with the same name are NOT used for page
 * dispatch).
 *
 * @param {object} component Vue component options.
 *
 * @return {object} A `{ kind: "page", component }` registry entry.
 */
function page(component) {
	return { kind: 'page', component }
}

export default {
	DashboardKpi: { kind: 'widget', component: DashboardKpi },
	DashboardRecentList: { kind: 'widget', component: DashboardRecentList },
	DashboardSkillUsage: { kind: 'widget', component: DashboardSkillUsage },
	DashboardActions: { kind: 'actions', component: DashboardActions },
	GameSettingsSection: { kind: 'section', component: GameSettingsSection },
}
