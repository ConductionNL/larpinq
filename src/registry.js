// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// CnAppRoot typed registry (v2 manifest pattern per hydra ADR-036).
//
// Valid kinds: widget | modal | page | form-field | cell-renderer
//
// All dashboard components (DashboardKpi, DashboardRecentList,
// DashboardSkillUsage, DashboardActions) and the settings section
// (GameSettingsSection) are resolved via the customComponents prop
// (actionsComponent / slots / sections[].component), not via this
// typed registry. This file intentionally stays empty — components
// that belong here are typed widgets / modals registered with full
// metadata (defaultSize, minSize, maxSize, allowedSlots, propsSchema).

export default {}
