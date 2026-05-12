<template>
	<NcContent app-name="larpingapp">
		<!-- Normal state: app loaded -->
		<template v-if="storesReady">
			<MainMenu @open-settings="showSettingsDialog = true" />
			<NcAppContent>
				<NcNoteCard v-if="!hasOpenRegisters" type="warning" class="open-register-warning">
					{{ t('larpingapp', 'OpenRegister is not configured. Some features may be limited.') }}
					<NcButton v-if="isAdmin" type="tertiary" :href="appStoreUrl" size="small">
						{{ t('larpingapp', 'Configure') }}
					</NcButton>
				</NcNoteCard>
				<router-view />
			</NcAppContent>
			<CnIndexSidebar
				v-if="sidebarState.active && !objectSidebarState.active"
				:schema="sidebarState.schema"
				:visible-columns="sidebarState.visibleColumns"
				:search-value="sidebarState.searchValue"
				:active-filters="sidebarState.activeFilters"
				:facet-data="sidebarState.facetData"
				:open="sidebarState.open"
				@update:open="sidebarState.open = $event"
				@search="onSidebarSearch"
				@columns-change="onSidebarColumnsChange"
				@filter-change="onSidebarFilterChange" />
			<CnObjectSidebar
				v-if="objectSidebarState.active"
				:object-type="objectSidebarState.objectType"
				:object-id="objectSidebarState.objectId"
				:title="objectSidebarState.title"
				:subtitle="objectSidebarState.subtitle"
				:register="objectSidebarState.register"
				:schema="objectSidebarState.schema"
				:hidden-tabs="objectSidebarState.hiddenTabs"
				:open.sync="objectSidebarState.open" />
			<UserSettings :open.sync="showSettingsDialog" />
		</template>
		<!-- Loading state -->
		<NcAppContent v-else>
			<div style="display: flex; justify-content: center; align-items: center; height: 100%;">
				<NcLoadingIcon :size="64" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import Vue from 'vue'
import { NcContent, NcAppContent, NcLoadingIcon, NcButton, NcNoteCard } from '@nextcloud/vue'
import { generateUrl, imagePath } from '@nextcloud/router'
import { CnIndexSidebar, CnObjectSidebar } from '@conduction/nextcloud-vue'
import MainMenu from './navigation/MainMenu.vue'
import UserSettings from './views/settings/UserSettings.vue'
import { initializeStores } from './store/store.js'
import { useSettingsStore } from './store/modules/settings.js'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppContent,
		NcLoadingIcon,
		NcButton,
		NcNoteCard,
		CnIndexSidebar,
		CnObjectSidebar,
		MainMenu,
		UserSettings,
	},

	provide() {
		return {
			sidebarState: this.sidebarState,
			objectSidebarState: this.objectSidebarState,
		}
	},

	data() {
		return {
			storesReady: false,
			showSettingsDialog: false,
			sidebarState: Vue.observable({
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
			}),
		}
	},

	computed: {
		hasOpenRegisters() {
			const settingsStore = useSettingsStore()
			return settingsStore.hasOpenRegisters
		},
		isAdmin() {
			const settingsStore = useSettingsStore()
			return settingsStore.getIsAdmin
		},
		appIcon() {
			return imagePath('larpingapp', 'app-dark.svg')
		},
		appStoreUrl() {
			return generateUrl('/settings/apps/integration/openregister')
		},
	},

	async created() {
		await initializeStores()
		this.storesReady = true
	},

	methods: {
		onSidebarSearch(value) {
			this.sidebarState.searchValue = value
			if (typeof this.sidebarState.onSearch === 'function') {
				this.sidebarState.onSearch(value)
			}
		},
		onSidebarColumnsChange(columns) {
			this.sidebarState.visibleColumns = columns
			if (typeof this.sidebarState.onColumnsChange === 'function') {
				this.sidebarState.onColumnsChange(columns)
			}
		},
		onSidebarFilterChange(filter) {
			if (typeof this.sidebarState.onFilterChange === 'function') {
				this.sidebarState.onFilterChange(filter)
			}
		},
	},
}
</script>
