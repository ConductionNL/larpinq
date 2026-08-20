// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

import {
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
import { createRouter, createWebHashHistory } from 'vue-router'
import App from './App.vue'
import appIcons from './icons.js'
import bundledManifest from './manifest.json'
import menuLayout from './menu-layout.json'
import pinia from './pinia.js'
import registry from './registry.js'

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
	console.warn(
		'[larpingapp] registerTranslations failed; falling back to English',
		e,
	)
}

// Fire-and-forget translation load — wrap in try/catch because
// some Nextcloud installs 404 on l10n JSON requests.
/**
 *
 */
function tryLoadTranslations() {
	try {
		const result = loadTranslations('larpingapp', () => {})
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

/**
 * Merge an array of incoming menu items into a target array, keyed by `id`.
 * New ids are appended; existing ids are merged in place (first definition
 * of label/icon/route wins) with children unioned recursively.
 *
 * @param {Array<object>} target The accumulated menu (mutated in place).
 * @param {Array<object>} incoming Menu items from a fragment.
 * @return {void}
 */
function mergeMenuItems(target, incoming) {
	incoming.forEach((item) => {
		const existing = target.find((t) => t.id === item.id)
		if (!existing) {
			target.push({
				...item,
				children: Array.isArray(item.children)
					? [...item.children]
					: item.children,
			})
			return
		}
		for (const key of [
			'label',
			'icon',
			'route',
			'order',
			'section',
			'permission',
			'href',
		]) {
			if (existing[key] === undefined && item[key] !== undefined) {
				existing[key] = item[key]
			}
		}
		if (Array.isArray(item.children) && item.children.length > 0) {
			if (!Array.isArray(existing.children)) {
				existing.children = []
			}
			mergeMenuItems(existing.children, item.children)
		}
	})
}

/**
 * Re-home merged menu entries onto the canonical navigation layout declared
 * by `src/menu-layout.json#relocations` (`{ sourceId: targetGroupId }`).
 *
 * A relocated GROUP dissolves: its children merge into the target and the
 * shell is dropped. A relocated LEAF moves under the target group.
 * Unknown source ids are inert; a missing target group keeps the entry at
 * the top level so nothing silently disappears. Runs in passes until stable.
 *
 * @param {Array<object>} menu The merged menu (mutated in place).
 * @param {Record<string, string>|undefined} relocations Source-id → target-group-id map.
 * @return {Array<object>} The menu with relocations applied.
 */
function applyMenuRelocations(menu, relocations) {
	if (!relocations || typeof relocations !== 'object') return menu
	for (let pass = 0; pass < 5; pass++) {
		const moves = []
		for (let i = menu.length - 1; i >= 0; i--) {
			const node = menu[i]
			const target = relocations[node.id]
			if (target && target !== node.id) {
				menu.splice(i, 1)
				moves.push({ node, target })
				continue
			}
			if (!Array.isArray(node.children)) continue
			for (let j = node.children.length - 1; j >= 0; j--) {
				const child = node.children[j]
				const childTarget = relocations[child.id]
				if (!childTarget) continue
				if (childTarget === node.id && !Array.isArray(child.children))
					continue
				node.children.splice(j, 1)
				moves.push({ node: child, target: childTarget })
			}
		}
		if (moves.length === 0) break
		moves.forEach(({ node, target }) => {
			const group = menu.find((m) => m.id === target)
			if (!group) {
				menu.push(node)
				return
			}
			if (!Array.isArray(group.children)) group.children = []
			if (Array.isArray(node.children)) {
				mergeMenuItems(group.children, node.children)
			} else {
				mergeMenuItems(group.children, [node])
			}
		})
	}
	return menu.filter(
		(m) =>
			m.route
			|| m.href
			|| m.action
			|| (Array.isArray(m.children) && m.children.length > 0),
	)
}

/**
 * Remove individual leaf menu entries by id after relocation — used to retire
 * duplicate navigation entries whose PAGE must stay routable.
 *
 * @param {Array<object>} menu The merged menu (mutated in place).
 * @param {Array<string>|undefined} removals Menu-entry ids to drop.
 * @return {Array<object>} The menu without the removed entries.
 */
function applyMenuRemovals(menu, removals) {
	if (!Array.isArray(removals) || removals.length === 0) return menu
	const drop = new Set(removals)
	const isLeaf = (n) => !Array.isArray(n.children) || n.children.length === 0
	menu.forEach((node) => {
		if (Array.isArray(node.children)) {
			node.children = node.children.filter(
				(c) => !(drop.has(c.id) && isLeaf(c)),
			)
		}
	})
	return menu.filter((node) => !(drop.has(node.id) && isLeaf(node)))
}

/**
 * Promote the menu entries listed in `src/menu-layout.json#settingsSection`
 * into Nextcloud's settings foldout — the NcAppNavigationSettings gear at the
 * bottom-left of the navigation, OUTSIDE the scrollable list. CnAppNav renders
 * every TOP-LEVEL item carrying `section: "settings"` as a flat entry inside
 * that foldout (with an auto-prepended "Personal settings"). This lifts each
 * listed id out of wherever it currently sits, tags it `section: "settings"`,
 * flattens it (the foldout has no nested groups), and appends it to the top
 * level. Empty non-clickable groups left behind are dropped; a clickable group
 * (one with route/href/action) is kept.
 *
 * @param {Array<object>} menu        The merged + relocated + pruned menu.
 * @param {Array<string>|undefined} settingsIds Entry ids to move to the foldout.
 * @return {Array<object>} The menu with the settings entries lifted out.
 */
function applySettingsSection(menu, settingsIds) {
	if (!Array.isArray(settingsIds) || settingsIds.length === 0) return menu
	const want = new Set(settingsIds)
	const isClickable = (n) =>
		n.route !== undefined || n.href !== undefined || n.action !== undefined
	const lifted = []
	const strip = (nodes) =>
		nodes.reduce((acc, n) => {
			if (want.has(n.id)) {
				// Drop `children` explicitly rather than by rest-destructuring:
				// NC's config runs `no-unused-vars` with `ignoreRestSiblings: false`,
				// so the omit-a-key idiom reports the omitted binding as unused.
				const leaf = { ...n }
				delete leaf.children
				lifted.push({ ...leaf, section: 'settings' })
				return acc
			}
			if (Array.isArray(n.children)) {
				const children = strip(n.children)
				if (
					children.length === 0
					&& n.children.length > 0
					&& !isClickable(n)
				)
					return acc
				acc.push({ ...n, children })
				return acc
			}
			acc.push(n)
			return acc
		}, [])
	const remaining = strip(menu)
	return [...remaining, ...lifted]
}

/**
 * ADR-037: Merge modular manifest fragments onto the bundled manifest.
 *
 * Every `*.json` file under `src/manifest.d/` is merged (in sorted filename
 * order) onto the bundled manifest. This lets concurrent same-app builds add
 * pages/menu entries via isolated fragment files instead of all editing
 * `src/manifest.json` and conflicting. `pages` and `menu` arrays are
 * concatenated; any other key on a fragment overrides the base value.
 * After merging, src/menu-layout.json relocations and removals are applied to
 * consolidate entries into their canonical navigation clusters.
 *
 * @param {object} base The bundled manifest.
 * @return {object} The merged manifest.
 */
function mergeManifestFragments(base) {
	// `require.context` is resolved at build time by webpack; the
	// `manifest.d/_placeholder.json` keeps the context non-empty so this
	// never throws when no real fragments exist yet.
	const context = require.context('./manifest.d', false, /\.json$/)
	const merged = { ...base }
	// Defensive copies so fragments never mutate the imported manifest.
	merged.pages = Array.isArray(base.pages) ? [...base.pages] : []
	merged.menu = Array.isArray(base.menu) ? [...base.menu] : []

	context
		.keys()
		.sort()
		.forEach((key) => {
			const fragment = context(key)
			if (!fragment || typeof fragment !== 'object') {
				return
			}
			Object.keys(fragment).forEach((prop) => {
				if (prop === 'pages' && Array.isArray(fragment.pages)) {
					merged.pages = merged.pages.concat(fragment.pages)
				} else if (prop === 'menu' && Array.isArray(fragment.menu)) {
					merged.menu = merged.menu.concat(fragment.menu)
				} else {
					merged[prop] = fragment[prop]
				}
			})
		})

	merged.menu = applyMenuRelocations(merged.menu, menuLayout.relocations)
	merged.menu = applyMenuRemovals(merged.menu, menuLayout.removals)
	merged.menu = applySettingsSection(merged.menu, menuLayout.settingsSection)

	return merged
}

// Apply ADR-037 manifest fragments before routes/app consume the manifest.
const manifest = mergeManifestFragments(bundledManifest)

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

const router = createRouter({
	history: createWebHashHistory(generateUrl('/apps/larpingapp')),
	routes: routesFromManifest(manifest),
})

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
