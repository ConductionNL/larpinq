// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// 5-kind component registry (v2 manifest pattern per hydra ADR-036).
//
// Larpingapp uses generic ObjectList/ObjectDetail wrappers around
// CnIndexPage/CnDetailPage. Each manifest page entry is type:'custom'
// with config.objectType driving the runtime register/schema selection.
// As the lib gains better support for parameterized typed pages, these
// can shrink toward zero.

import ObjectList from './views/ObjectList.vue'
import ObjectDetail from './views/ObjectDetail.vue'
import DashboardIndex from './views/dashboard/DashboardIndex.vue'
import Settings from './views/settings/Settings.vue'

export default {
	ObjectList: { kind: 'page', component: ObjectList },
	ObjectDetail: { kind: 'page', component: ObjectDetail },
	DashboardView: { kind: 'page', component: DashboardIndex },
	SettingsView: { kind: 'page', component: Settings },
}
