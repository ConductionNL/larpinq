// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// Custom-component registry (v1 pattern) for the manifest-driven app shell.
//
// Preserved alongside the v2 registry.js during the migration window.
// CnAppRoot accepts both props; v2 renderer emits a one-shot deprecation
// warning when both are present and the manifest is v2.

import ObjectList from './views/ObjectList.vue'
import ObjectDetail from './views/ObjectDetail.vue'
import DashboardIndex from './views/dashboard/DashboardIndex.vue'
import Settings from './views/settings/Settings.vue'

export default {
	ObjectList,
	ObjectDetail,
	DashboardView: DashboardIndex,
	SettingsView: Settings,
}
