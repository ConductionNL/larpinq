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

import WorldSwitcher from './components/WorldSwitcher.vue'
import WorldSwitcherActions from './components/WorldSwitcherActions.vue'
import ApplyBuildDialog from './dialogs/ApplyBuildDialog.vue'
import CharacterPdfDownloadDialog from './dialogs/CharacterPdfDownloadDialog.vue'
import CopyWorldDialog from './dialogs/CopyWorldDialog.vue'
import BuildReport from './views/BuildReport.vue'
import CharacterCustomFields from './views/CharacterCustomFields.vue'
import CharacterStatSheet from './views/CharacterStatSheet.vue'
import EventRoster from './views/EventRoster.vue'
import FlowDetailSidebar from './views/flows/FlowDetailSidebar.vue'
import LoreArticle from './views/LoreArticle.vue'
import ObjectDetail from './views/ObjectDetail.vue'
import GameSettingsSection from './views/settings/Settings.vue'
import SkillTree from './views/SkillTree.vue'
import {
	exportCampaignAction,
	importCampaignAction,
} from './services/campaignActions.js'
import { bulkEditCharactersAction } from './services/characterBulkEditAction.js'

export default {
	// The active-world switcher (events-world-scope-and-upcoming): the header of
	// the world-scoped index pages (it draws the page title it replaces) and
	// the dashboard's actions. It writes the page workspace the lists'
	// `@workspace.activeWorld?` filter reads.
	// @spec openspec/specs/setting-management/spec.md
	WorldSwitcher: {
		kind: 'header',
		component: WorldSwitcher,
	},
	WorldSwitcherActions: {
		kind: 'actions',
		component: WorldSwitcherActions,
	},
	// CharacterDetail "Download as PDF" header action (open-modal): a template
	// picker a declarative api-call cannot express, since the download opens
	// in a new tab with the chosen template in the URL.
	// @spec openspec/specs/pdf-export/spec.md
	CharacterPdfDownloadDialog: {
		kind: 'modal',
		component: CharacterPdfDownloadDialog,
		propsSchema: {},
	},
	CopyWorldDialog: {
		kind: 'modal',
		component: CopyWorldDialog,
		propsSchema: {},
	},
	// BuildDetail "Apply to character" header action (characters-multiple-builds).
	// @spec openspec/specs/character-builds/spec.md
	ApplyBuildDialog: {
		kind: 'modal',
		component: ApplyBuildDialog,
		propsSchema: {},
	},

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
	CharacterStatSheet: { kind: 'section', component: CharacterStatSheet },
	CharacterCustomFields: { kind: 'section', component: CharacterCustomFields },
	// Check tab on the build page. @spec openspec/specs/character-builds/spec.md
	BuildReport: { kind: 'section', component: BuildReport },
	// Skill-tree visualization — a read-only type:"custom" page
	// (skill-tree-visualization). Resolved by CnPageRenderer as the page body
	// component for the SkillTree manifest page.
	SkillTree: { kind: 'page', component: SkillTree },
	// Lore read page (worlds-lore-pages): CnWikiPage plus the fetch it leaves
	// to its host. @spec openspec/specs/world-lore/spec.md
	LoreArticle: { kind: 'page', component: LoreArticle },

	// Worlds page header actions (admin-import-export): the whole campaign as
	// one workbook out of, and back into, the larpinq register.
	// @spec openspec/specs/data-portability/spec.md
	larpinqExportCampaign: { kind: 'handler', handler: exportCampaignAction },
	larpinqImportCampaign: { kind: 'handler', handler: importCampaignAction },

	// Characters index bulk action "Edit selected" (characters-status-and-bulk-edit).
	// A handler, not open-modal: CnIndexPage only emits a bulk open-modal to
	// its host, which no host here listens to.
	// @spec openspec/specs/character-management/spec.md
	larpinqBulkEditCharacters: {
		kind: 'handler',
		handler: bulkEditCharactersAction,
	},
}
