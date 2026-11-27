// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

import {
	buildManifest,
	defaultPageTypes,
	mountCnApp,
	registerBuiltinDashboardWidgets,
} from '@conduction/nextcloud-vue'
import { loadTranslations } from '@nextcloud/l10n'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import appIcons from './icons.js'
import bundledManifest from './manifest.json'
import menuLayout from './menu-layout.json'
import pinia from './pinia.js'
import registry from './registry.js'
import { applyImportGate, canImport } from './services/campaignPortability.js'

// MDI icons referenced by the manifest (menu, KPIs, widgets) live in
// ./icons.js and are handed to mountCnApp below, which registers them. CnIcon
// resolves names from that registry, so every icon the manifest uses must be
// listed there — otherwise it falls back to the help-circle placeholder.
// Library CSS — must be explicit import (webpack tree-shakes side-effect imports from aliased packages)
// The library stylesheet is linked by templates/index.php (css/nextcloud-vue.css,
// copied by LibraryStylesheetCopy), not inserted from this bundle as text.
// gridstack v12 CSS. The manifest declares a `type: "dashboard"` page, whose
// widgets nc-vue lays out with gridstack. Without this stylesheet every
// dashboard item renders 0 px wide — v12 sizes items with
// `width: var(--gs-column-width)` — and NOTHING is logged.
import 'gridstack/dist/gridstack.min.css'
// Global (unscoped) app styles
import './assets/app.css'

// Populate the dashboard widget-type registry.
//
// This call is an intentional no-op in the library — its ONLY job is to make
// the bundler evaluate `registerDashboardWidgets.js`, which self-registers the
// built-in widget catalog. nc-vue's package.json declares
// `sideEffects: ["**/*.css"]` (ADR-061 tree-shaking), which lets webpack
// legally DROP that module's bare side-effect imports.
//
// The manifest's `stat` KPI tiles and `object-table` lists are registered
// exactly that way — via `import '../CnStatWidget/index.js'` and
// `import '../CnWidgetObjectTable/dashboardRegistration.js'` — so without this
// call `getWidgetTypeEntry()` misses and CnDashboardPage falls through to its
// "Widget not available" placeholder. Verified live on the isolated instance:
// 5 of the 7 dashboard widgets rendered that placeholder, and the one that
// worked (`chart`) is registered by an INLINE `registerDashboardWidget()` call
// that tree-shaking cannot reach.
registerBuiltinDashboardWidgets()

// Fire-and-forget translation load — wrap in try/catch because
// some Nextcloud installs 404 on l10n JSON requests.
/**
 *
 */
function tryLoadTranslations() {
	try {
		const result = loadTranslations('larpinq', () => {})
		if (result && typeof result.then === 'function') {
			result.then(
				() => {},
				() => {},
			)
		}
	} catch {
		// no-op
	}
}

// ADR-044: the effective manifest is the bundled manifest with every
// `src/manifest.d/*.json` fragment merged on (sorted filename order) and the
// canonical layout of `src/menu-layout.json` applied, built by the shared
// buildManifest(). Collecting the fragments is the only app-local step:
// `require.context` is resolved by this app's webpack build, and
// `manifest.d/_placeholder.json` keeps the context non-empty. Then remove
// every import for a user OpenRegister will not let import
// (admin-import-export: the register import is administrators only).
const fragmentContext = require.context('./manifest.d', false, /\.json$/)
const manifest = applyImportGate(
	buildManifest(
		bundledManifest,
		fragmentContext
			.keys()
			.sort()
			.map((key) => fragmentContext(key)),
		menuLayout,
	),
	canImport(),
)

tryLoadTranslations()

// Boot on CnAppRoot in one call (larpinq-manifest-tier-4, task 2.1).
// mountCnApp builds the router from the manifest's pages (one CnPageRenderer
// route per page, props on parameterised routes, a catch-all to `/`), takes
// the router base from the URL the visitor actually used (`/apps/larpinq` or
// `/index.php/apps/larpinq`), registers the MDI icons the manifest names and
// the library's translations, installs the t / n mixin, pinia and the router,
// and mounts on #content. App.vue is the root: it wraps CnAppRoot to fill its
// #sidebar and #user-settings slots, and receives the manifest, registry and
// page types as props.
//
// @spec openspec/specs/app-manifest/spec.md#requirement-cnapproot-shall-be-the-boot-entry-point
mountCnApp('#content', {
	appId: 'larpinq',
	manifest,
	registry,
	pageTypes: defaultPageTypes,
	icons: appIcons,
	vueRouter: { createRouter, createWebHistory },
	plugins: [pinia],
	rootComponent: App,
})
