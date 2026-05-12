// SPDX-License-Identifier: AGPL-3.0-or-later
// SPDX-FileCopyrightText: Conduction B.V. <info@conduction.nl>

import Vue from 'vue'
import VueRouter from 'vue-router'
import { PiniaVuePlugin } from 'pinia'
import { translate as t, translatePlural as n, loadTranslations } from '@nextcloud/l10n'
import {
	CnPageRenderer,
	defaultPageTypes,
	registerIcons,
	registerTranslations,
} from '@conduction/nextcloud-vue'
import pinia from './pinia.js'
import App from './App.vue'
import bundledManifest from './manifest.json'
import customComponents from './customComponents.js'

// Library CSS — must be explicit import (webpack tree-shakes side-effect imports from aliased packages)
import '@conduction/nextcloud-vue/css/index.css'

// MDI icons used by LarpingApp schemas so CnIcon / CnDataTable can render them.
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import Cog from 'vue-material-design-icons/Cog.vue'

Vue.mixin({ methods: { t, n } })
Vue.use(PiniaVuePlugin)
Vue.use(VueRouter)

// Register the library icon set, then merge in LarpingApp's schema icons.
registerIcons()
registerIcons({
	BriefcaseAccountOutline,
	AccountGroupOutline,
	Sword,
	SwordCross,
	ShieldSwordOutline,
	MagicStaff,
	CalendarMonthOutline,
	EmoticonSickOutline,
	Cog,
})
try {
	registerTranslations()
} catch (e) {
	// Non-fatal — lib translations fall back to English source.
	// eslint-disable-next-line no-console
	console.warn('[larpingapp] registerTranslations failed; falling back to English', e)
}

// Fire-and-forget translation load. Some Nextcloud installs only allow the
// JS/CSS allowlist through Apache and rewrite everything else to index.php —
// `loadTranslations` then rejects on 404, so boot must never await it.
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
// non-extensible (webpack ESM module records). Vue 2's `Vue.extend()` adds an
// internal `_Ctor` cache to the component definition; mutating a non-extensible
// export throws "Cannot add property _Ctor, object is not extensible". Cloning
// gives Vue Router an extensible component-options object.
const RoutePageRenderer = { ...CnPageRenderer }

/**
 * Build the vue-router config from the manifest. Each manifest page becomes one
 * route; the route's `name` IS `page.id` (per the lib's manifest contract).
 * Routes whose path declares a `:` parameter receive `props: true`.
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
	routes.push({ path: '*', redirect: '/' })
	return routes
}

const router = new VueRouter({
	mode: 'hash',
	routes: routesFromManifest(bundledManifest),
})

tryLoadTranslations()

// Pass shallow copies of the registry maps to CnAppRoot — the lib may export
// `defaultPageTypes` / `customComponents` as frozen module objects in some
// bundle shapes, and Vue 2's `Vue.extend()` mutates component definitions.
const pageTypesProp = { ...defaultPageTypes }
const customComponentsProp = { ...customComponents }

new Vue({
	pinia,
	router,
	render: (h) => h(App, {
		props: {
			manifest: bundledManifest,
			customComponents: customComponentsProp,
			pageTypes: pageTypesProp,
		},
	}),
}).$mount('#content')
