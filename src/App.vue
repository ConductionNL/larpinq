<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: Conduction B.V. <info@conduction.nl> -->

<!--
 LarpingApp shell. Mounts CnAppRoot with the bundled manifest. CnAppRoot owns
 the dependency check (manifest.dependencies → "OpenRegister required" empty
 state), the default CnAppNav (manifest.menu) and per-route page dispatch
 (manifest.pages[].type → Cn{Dashboard,Index,Detail,Settings}Page) — every
 LarpingApp page is now a declarative manifest page (no per-page Vue files).
-->
<template>
	<CnAppRoot
		:manifest="manifest"
		:custom-components="customComponents"
		:page-types="pageTypes"
		app-id="larpingapp"
		:translate="translateForApp"
		:permissions="permissions" />
</template>

<script>
import Vue from 'vue'
import { translate as ncT } from '@nextcloud/l10n'
import { CnAppRoot } from '@conduction/nextcloud-vue'
import { initializeStores } from './store/store.js'

export default {
	name: 'App',

	components: {
		CnAppRoot,
	},

	provide() {
		return {
			// Provide/inject channel for index/detail pages that auto-mount
			// sidebar content (matches the decidesk/procest pattern).
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
		 * Consumer-injected components used by `type: "custom"` pages and
		 * `headerComponent` / sidebar-tab / settings-section overrides. Empty
		 * for larpingapp — see src/customComponents.js.
		 */
		customComponents: {
			type: Object,
			default: () => ({}),
		},
		/**
		 * Page-type registry — `{ index, detail, dashboard, settings, ... }`.
		 */
		pageTypes: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			objectSidebarState: Vue.observable({
				active: false,
				open: true,
				schema: null,
				visibleColumns: null,
				searchValue: '',
				activeFilters: {},
				facetData: {},
				onSearch: null,
				onColumnsChange: null,
				onFilterChange: null,
			}),
		}
	},

	computed: {
		permissions() {
			return window.OC?.currentUser?.permissions ?? []
		},
	},

	async created() {
		// Bring the Pinia stores up so the admin-settings store keeps working
		// and the shared object store has every LarpingApp schema registered.
		await initializeStores()
	},

	methods: {
		/**
		 * Translate function passed down to CnAppRoot / CnAppNav /
		 * CnPageRenderer. Closes over the Nextcloud `translate` import.
		 *
		 * @param {string} key Translation key.
		 * @return {string} Translated string (or the key on miss).
		 */
		translateForApp(key) {
			return ncT('larpingapp', key)
		},
	},
}
</script>
