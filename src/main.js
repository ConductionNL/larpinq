// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

import {
	buildManifest,
	CnPageRenderer,
	defaultPageTypes,
	registerBuiltinDashboardWidgets,
	registerIcons,
	registerTranslations,
} from '@conduction/nextcloud-vue'
import {
	loadTranslations,
	translatePlural as n,
	translate as t,
} from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { createApp, h } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import appIcons from './icons.js'
import bundledManifest from './manifest.json'
import menuLayout from './menu-layout.json'
import pinia from './pinia.js'
import registry from './registry.js'
import { applyImportGate, canImport } from './services/campaignPortability.js'

// MDI icons referenced by the manifest (menu, KPIs, widgets). CnIcon resolves
// names from the registry populated by registerIcons(), so every icon the
// manifest uses must be imported + registered here — otherwise it falls back
// to the help-circle placeholder.
// Library CSS — must be explicit import (webpack tree-shakes side-effect imports from aliased packages)
import '@conduction/nextcloud-vue/css/index.css'
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

// Register the MDI icons the manifest references + lib translations at bootstrap.
registerIcons(appIcons)
try {
	registerTranslations()
} catch (e) {
	// Non-fatal — lib translations fall back to English source.
	// eslint-disable-next-line no-console
	console.warn('[larpinq] registerTranslations failed; falling back to English', e)
}

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

// Shallow-clone CnPageRenderer because the lib's barrel exports are
// non-extensible (webpack ESM module records) and vue-router / Vue may attach
// internal caches to a component options object. Cloning gives the router an
// extensible component-options object without altering the lib's internals.
const RoutePageRenderer = { ...CnPageRenderer }

/**
 * Build the vue-router config from the manifest. Each manifest page becomes
 * one route; routes with `:` parameters receive `props: true`.
 *
 * @param {object} manifest The bundled manifest (with `pages[]`).
 * @return {Array<object>} vue-router 4 routes config.
 */
function routesFromManifest(manifest) {
	const routes = manifest.pages.map((page) => ({
		name: page.id,
		path: page.route,
		component: RoutePageRenderer,
		props: page.route.includes(':'),
	}))
	// Catch-all: redirect unknown paths to the dashboard.
	//
	// vue-router 4 REMOVED the bare `path: '*'` wildcard — it no longer matches
	// anything and no warning is emitted, so an unknown hash would render the
	// app shell with an empty `<main>`. The parameterised form below is its
	// replacement.
	routes.push({ path: '/:pathMatch(.*)*', redirect: '/' })
	return routes
}

/**
 * The router base for THIS page load.
 *
 * ⚠️ `generateUrl('/apps/larpinq')` alone is not enough. Nextcloud serves the
 * app under BOTH `/apps/larpinq/...` and `/index.php/apps/larpinq/...`, but
 * `generateUrl()` returns only the form the instance is configured for. A
 * visitor arriving on the other form falls outside the router base, vue-router
 * cannot resolve the path, and the catch-all redirects to `/`: they land on the
 * dashboard with no error and the deep link is silently swallowed.
 *
 * This app's own specs use BOTH spellings — `/apps/larpinq` in `_nav.ts` and
 * the docs-screenshots spec, `/index.php/apps/larpinq` in the visual spec — so
 * one or the other would break whichever base was hardcoded.
 *
 * @return {string} The base path vue-router should strip from the URL.
 */
function routerBase() {
	const match = window.location.pathname.match(/^(.*\/apps\/larpinq)(?:\/|$)/)
	return match ? match[1] : generateUrl('/apps/larpinq')
}

const router = createRouter({
	history: createWebHistory(routerBase()),
	routes: routesFromManifest(manifest),
})
try {
	registerTranslations()
} catch (e) {
	// Non-fatal — lib translations fall back to English source.
	// eslint-disable-next-line no-console
	console.warn(
		'[larpingapp] registerTranslations failed; falling back to English',
		e,
	)
}

tryLoadTranslations()

// Pass shallow copies of the registry maps to App.vue. The lib exports
// `defaultPageTypes` (and our `registry`) FROZEN in some bundle shapes, and
// anything that later attaches an internal cache to them throws against a
// frozen object. Cloning here yields extensible objects without changing the
// values the lib resolves at render time.
const pageTypesProp = { ...defaultPageTypes }
const registryProp = { ...registry }

const app = createApp({
	render: () =>
		h(App, {
			manifest,
			registry: registryProp,
			pageTypes: pageTypesProp,
		}),
})

// Vue 3: plugins and global mixins are installed on the APP INSTANCE, not on
// the Vue constructor. `PiniaVuePlugin` is gone — `app.use(pinia)` is the only
// installation path.
app.mixin({ methods: { t, n } })
app.use(pinia)
app.use(router)
app.mount('#content')
