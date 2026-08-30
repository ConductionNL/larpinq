/**
 * Larpinq v2 component registry (ADR-036).
 *
 * Kind-tagged map passed as the `registry` prop to CnAppRoot. Replaces the
 * deprecated `customComponents` prop.
 *
 * Larpinq's manifest pages are typed primitives
 * (type: "index" / "detail" / "dashboard" / "settings"), so no `kind: "page"`
 * entries are needed here — the renderer resolves them directly via
 * `pageTypes`. The entries below are non-page kinds referenced from inside
 * typed pages via slot keys (page.slots[*]) and page.config.sections[*].component.
 * CnPageRenderer's slot-override resolution is kind-agnostic — any entry with a
 * `component` field resolves — so the semantic `section` kind documents the
 * intent without affecting dispatch.
 *
 * The dashboard is fully declarative (ADR-049): its KPI tiles (`stat`), recent
 * lists (`object-table`), skill-usage chart (`chart` with an aggregate
 * dataSource) and header actions (`config.headerActions[]` open-form + refresh)
 * are all built-in manifest widgets — no custom `kind: "widget"` components.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

import EventRoster from './views/EventRoster.vue'
import ObjectDetail from './views/ObjectDetail.vue'
import GameSettingsSection from './views/settings/Settings.vue'
import SkillTree from './views/SkillTree.vue'
import FlowDetailSidebar from './views/flows/FlowDetailSidebar.vue'

export default {
	// --- Flows (ADR-110 Decision 4). Only the SIDEBAR is an app component;
	//     the list and the canvas are the shared `index` / `flow` manifest
	//     page types. CnFlowSidebar has to mount in the NC app sidebar for
	//     the canvas to keep full width. ---
	FlowDetailSidebar: { kind: 'page', component: FlowDetailSidebar },

	GameSettingsSection: { kind: 'section', component: GameSettingsSection },
	ObjectDetail: { kind: 'section', component: ObjectDetail },
	// Event check-in roster — a sidebar-tab section on the event detail page
	// (event-checkin-roster). Not a kind:"widget" (no custom-widget-ratchet
	// entry); it renders inside the CnObjectSidebar tab strip.
	EventRoster: { kind: 'section', component: EventRoster },
	// Skill-tree visualization — a read-only type:"custom" page
	// (skill-tree-visualization). Resolved by CnPageRenderer as the page body
	// component for the SkillTree manifest page.
	SkillTree: { kind: 'page', component: SkillTree },
}
