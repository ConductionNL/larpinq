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
// pages (page.slots[*] or page.config.sections[*].component):
//   - DashboardHomeWidget — full-page widget inside type:"dashboard"
//   - GameSettingsSection — section body inside type:"settings"
//
// Future cleanup: decompose the Dashboard into per-KPI widgets + an
// integration-registry-driven recent-activity widget; split Settings
// into typed-form sections.

import DashboardHomeWidget from './views/dashboard/DashboardIndex.vue'
import GameSettingsSection from './views/settings/Settings.vue'

export default {
	DashboardHomeWidget: { kind: 'widget',  component: DashboardHomeWidget },
	GameSettingsSection: { kind: 'section', component: GameSettingsSection },
}
