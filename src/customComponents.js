// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// Custom-component registry (v1 pattern) for the manifest-driven app shell.
//
// All page-level entries migrated to typed primitives in manifest v2.
// Remaining entries live inside typed pages (Dashboard widget slot,
// Settings section). Kept exported for CnAppRoot's `customComponents`
// prop (v1 compat path).

import DashboardHomeWidget from './views/dashboard/DashboardIndex.vue'
import GameSettingsSection from './views/settings/Settings.vue'

export default {
	DashboardHomeWidget,
	GameSettingsSection,
}
