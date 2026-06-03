// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

import Vue from 'vue'
import VueRouter from 'vue-router'
import { PiniaVuePlugin } from 'pinia'
import { translate as t, translatePlural as n, loadTranslations } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
	CnPageRenderer,
	defaultPageTypes,
	registerIcons,
	registerTranslations,
} from '@conduction/nextcloud-vue'
import pinia from './pinia.js'
import App from './App.vue'
import baseManifest from './manifest.json'
import registry from './registry.js'

// Library CSS — must be explicit import (webpack tree-shakes side-effect imports from aliased packages)
import '@conduction/nextcloud-vue/css/index.css'

// Global (unscoped) app styles
import './assets/app.css'

/**
 * Deep-merge every src/manifest.d/*.json fragment onto the bundled base
 * manifest (ADR-037). Each concurrent same-app build adds its own fragment
 * file under src/manifest.d/ instead of editing the shared manifest.json, so
 * disjoint builds never conflict. `pages` and `menu` arrays concatenate;
 * other top-level keys from the base are preserved.
 *
 * @param {object} base The bundled base manifest (with `pages[]` + `menu[]`).
 * @return {object} The merged manifest.
 */
function mergeManifestFragments(base) {
	const merged = {
		...base,
		pages: [...(base.pages || [])],
		menu: [...(base.menu || [])],
	}
	// require.context is resolved at build time by webpack; the _placeholder
	// fragment guarantees at least one match so the context never throws.
	const context = require.context('./manifest.d', false, /\.json$/)
	context.keys().sort().forEach((key) => {
		const fragment = context(key)
		if (Array.isArray(fragment.pages)) {
			merged.pages.push(...fragment.pages)
		}
		if (Array.isArray(fragment.menu)) {
			merged.menu.push(...fragment.menu)
		}
	})
	return merged
}

const bundledManifest = mergeManifestFragments(baseManifest)

Vue.mixin({ methods: { t, n } })
Vue.use(PiniaVuePlugin)
Vue.use(VueRouter)

// Register library-side icon set + lib translations once at bootstrap.
registerIcons()
try {
	registerTranslations()
} catch (e) {
	// Non-fatal — lib translations fall back to English source.
	// eslint-disable-next-line no-console
	console.warn('[larpingapp] registerTranslations failed; falling back to English', e)
}

// Fire-and-forget translation load — wrap in try/catch because
// some Nextcloud installs 404 on l10n JSON requests.
function tryLoadTranslations() {
	try {
		const result = loadTranslations('larpingapp', () => {})
		if (result && typeof result.then === 'function') {
			result.then(() => {}, () => {})
		}
	} catch {
		// no-op
	}
}

// Shallow-clone CnPageRenderer because the lib's barrel exports are
// non-extensible (webpack ESM module records). Vue 2's `Vue.extend()`
// adds an internal `_Ctor` cache to the component definition; mutating
// a non-extensible export throws "Cannot add property _Ctor, object is
// not extensible". Cloning gives Vue Router an extensible
// component-options object without altering the lib's internals.
const RoutePageRenderer = { ...CnPageRenderer }

/**
 * Build the vue-router config from the manifest. Each manifest page becomes
 * one route; routes with `:` parameters receive `props: true`.
 *
 * @param {object} manifest The bundled manifest (with `pages[]`).
 * @return {Array<object>} vue-router 3 routes config.
 */
function routesFromManifest(manifest) {
	const routes = manifest.pages.map((page) => ({
		name: page.id,
		path: page.route,
		component: RoutePageRenderer,
		props: page.route.includes(':'),
	}))
	// Catch-all: redirect unknown paths to the dashboard.
	routes.push({ path: '*', redirect: '/' })
	return routes
}

const router = new VueRouter({
	mode: 'history',
	base: generateUrl('/apps/larpingapp'),
	routes: routesFromManifest(bundledManifest),
})

tryLoadTranslations()

// Pass shallow copies of the registry maps to App.vue. The lib exports
// `defaultPageTypes` (and our `registry`) as frozen module objects in
// some bundle shapes — Vue 2's `Vue.extend()` mutates component definitions
// to attach an internal `_Ctor` cache, which throws "Cannot add property
// _Ctor, object is not extensible" against a frozen source map. Cloning
// here yields extensible objects without changing the values the lib
// resolves at render time.
const pageTypesProp = { ...defaultPageTypes }
const registryProp = { ...registry }

// eslint-disable-next-line no-new
new Vue({
	pinia,
	router,
	render: (h) => h(App, {
		props: {
			manifest: bundledManifest,
			registry: registryProp,
			pageTypes: pageTypesProp,
		},
	}),
}).$mount('#content')
