<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->

<!--
 Larping app shell. Mounts CnAppRoot with the bundled manifest and the
 v2 kind-tagged registry (ADR-036); provides the `objectSidebarState` channel so
 detail pages (CnDetailPage) can drive a single host-rendered CnObjectSidebar
 through the #sidebar slot.

 This file follows the canonical Tier-4 scaffold for the JSON manifest
 renderer pattern (hydra ADR-024). The Settings menu entry uses
 action: "user-settings" → opens NcAppSettingsDialog via CnAppRoot's
 cnOpenUserSettings inject.

 @spec openspec/changes/manifest-v2-vue-scaffold/specs/manifest-v2-vue-scaffold/spec.md
-->
<template>
	<CnAppRoot
		:manifest="manifest"
		:registry="registry"
		:custom-components="customComponents"
		:page-types="pageTypes"
		app-id="larpingapp"
		:translate="translateForApp"
		:permissions="permissions">
		<template #sidebar>
			<CnObjectSidebar
				v-if="objectSidebarState.active"
				:title="objectSidebarState.title"
				:subtitle="objectSidebarState.subtitle"
				:object-type="objectSidebarState.objectType"
				:object-id="objectSidebarState.objectId"
				:register="objectSidebarState.register"
				:schema="objectSidebarState.schema"
				:hidden-tabs="objectSidebarState.hiddenTabs"
				:tabs="objectSidebarState.tabs"
				:open="objectSidebarState.open"
				@update:open="objectSidebarState.open = $event" />
		</template>
		<template #user-settings>
			<NcAppSettingsSection
				id="general"
				:name="t('larpingapp', 'General')">
				<p class="app-root__settings-hint">
					{{ t('larpingapp', 'Configure your Larping app settings here.') }}
				</p>
			</NcAppSettingsSection>
		</template>
	</CnAppRoot>
</template>

<script>
import Vue from 'vue'
import { translate as ncT } from '@nextcloud/l10n'
import { NcAppSettingsSection } from '@nextcloud/vue'
import { CnAppRoot, CnObjectSidebar } from '@conduction/nextcloud-vue'

export default {
	name: 'App',

	components: {
		CnAppRoot,
		CnObjectSidebar,
		NcAppSettingsSection,
	},

	/**
	 * @spec exclude Vue provide() lifecycle hook — exposes the reactive
	 * objectSidebarState channel to descendants; framework glue, no behavior.
	 */
	provide() {
		return {
			// Channel for CnDetailPage → host-rendered CnObjectSidebar.
			// Vue.observable makes the plain object reactive for Vue 2.
			objectSidebarState: this.objectSidebarState,
		}
	},

	props: {
		/**
		 * Manifest object — passed from main.js bootstrap. CnAppRoot reads
		 * `manifest.dependencies` for the dependency-check phase and
		 * `manifest.menu` for the default CnAppNav.
		 */
		manifest: {
			type: Object,
			required: true,
		},
		/**
		 * v2 kind-tagged component registry (ADR-036). Passed to CnAppRoot's
		 * `registry` prop. Each entry: `{ kind, component, ...kindMetadata }`
		 * where `kind` is one of "page" | "widget" | "actions" | "section" |
		 * "modal" | "form-field" | "cell-renderer". CnPageRenderer keys page
		 * dispatch off `kind === "page"` entries; other kinds serve
		 * dashboard widgets, settings sections, action menus, etc. Replaces
		 * the deprecated `customComponents` prop.
		 */
		registry: {
			type: Object,
			default: () => ({}),
		},
		/**
		 * Page-type registry — `{ index, detail, dashboard, settings, ... }`.
		 * Wired through to descendant `CnPageRenderer` instances via
		 * provide/inject.
		 */
		pageTypes: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			objectSidebarState: Vue.observable({
				active: false,
				open: true,
				objectType: '',
				objectId: '',
				title: '',
				subtitle: '',
				register: '',
				schema: '',
				hiddenTabs: [],
				tabs: undefined,
			}),
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial passthrough of window.OC.currentUser.permissions
		 * to CnAppRoot — reads framework global, no business logic.
		 */
		permissions() {
			return window.OC?.currentUser?.permissions ?? []
		},
		/**
		 * Flat name→component map derived from the v2 `registry` prop.
		 *
		 * `CnPageRenderer.effectiveCustomComponents` resolves `actionsComponent`,
		 * `headerComponent`, and `page.slots.*` keys against the legacy
		 * `customComponents` inject, not against `cnRegistry`. Until the library
		 * unifies both resolution paths, we derive a flat shim here so that
		 * slot-override lookups (e.g. `actionsComponent: "DashboardActions"`)
		 * continue to work when the registry uses the v2 kind-tagged format.
		 *
		 * Entries that carry a `component` field are included; pure-metadata
		 * entries without a `component` field are skipped.
		 *
		 * @spec exclude Framework shim — bridges v2 registry to legacy
		 * customComponents inject used by CnPageRenderer slot resolution.
		 */
		customComponents() {
			const result = {}
			for (const [key, entry] of Object.entries(this.registry || {})) {
				if (entry && typeof entry.component !== 'undefined') {
					result[key] = entry.component
				}
			}
			return result
		},
	},

	methods: {
		/**
		 * Translate function passed down to CnAppRoot / CnAppNav /
		 * CnPageRenderer. Closes over the Nextcloud `translate` import so
		 * the lib never has to know our app id.
		 *
		 * @param {string} key Translation key.
		 * @return {string} Translated string (or the key on miss).
		 *
		 * @spec exclude Trivial translate passthrough closing over the app id
		 * so the shared lib never needs to know it — framework glue.
		 */
		translateForApp(key) {
			return ncT('larpingapp', key)
		},
	},
}
</script>
