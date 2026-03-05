<template>
	<div>
		<CnIndexPage
			:title="t('larpingapp', titleKey)"
			:schema="schema"
			:objects="objects"
			:pagination="pagination"
			:loading="loading"
			:sort-key="sortKey"
			:sort-order="sortOrder"
			:selectable="true"
			:include-columns="visibleColumns"
			@add="createNew"
			@refresh="fetchObjects"
			@sort="onSort"
			@row-click="openObject"
			@page-changed="loadPage" />
	</div>
</template>

<script>
import { CnIndexPage } from '@conduction/nextcloud-vue'
import { useObjectStore } from '../store/modules/object.js'

const TITLE_MAP = {
	character: 'Characters',
	player: 'Players',
	ability: 'Abilities',
	skill: 'Skills',
	item: 'Items',
	condition: 'Conditions',
	effect: 'Effects',
	event: 'Events',
	setting: 'Settings',
}

const DETAIL_ROUTE_MAP = {
	character: 'CharacterDetail',
	player: 'PlayerDetail',
	ability: 'AbilityDetail',
	skill: 'SkillDetail',
	item: 'ItemDetail',
	condition: 'ConditionDetail',
	effect: 'EffectDetail',
	event: 'EventDetail',
	setting: 'SettingDetail',
}

export default {
	name: 'ObjectList',
	components: {
		CnIndexPage,
	},

	inject: {
		sidebarState: { default: null },
	},

	props: {
		objectType: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			searchTerm: '',
			searchTimeout: null,
			sortKey: null,
			sortOrder: 'asc',
			schema: null,
			visibleColumns: null,
		}
	},

	computed: {
		objectStore() {
			return useObjectStore()
		},
		titleKey() {
			return TITLE_MAP[this.objectType] || this.objectType
		},
		detailRoute() {
			return DETAIL_ROUTE_MAP[this.objectType] || 'Dashboard'
		},
		objects() {
			return this.objectStore.collections[this.objectType] || []
		},
		loading() {
			return this.objectStore.loading[this.objectType] || false
		},
		pagination() {
			return this.objectStore.pagination[this.objectType] || { total: 0, page: 1, pages: 1, limit: 20 }
		},
	},

	watch: {
		objectType() {
			this.schema = null
			this.searchTerm = ''
			this.sortKey = null
			this.sortOrder = 'asc'
			this.visibleColumns = null
			this.init()
		},
	},

	async mounted() {
		await this.init()
	},

	beforeDestroy() {
		this.teardownSidebar()
	},

	methods: {
		async init() {
			this.schema = await this.objectStore.fetchSchema(this.objectType)
			this.setupSidebar()
			this.fetchObjects()
		},
		setupSidebar() {
			if (!this.sidebarState) return
			this.sidebarState.active = true
			this.sidebarState.schema = this.schema
			this.sidebarState.searchValue = this.searchTerm
			this.sidebarState.activeFilters = {}
			this.sidebarState.onSearch = (value) => {
				this.onSearch(value)
			}
			this.sidebarState.onColumnsChange = (columns) => {
				this.visibleColumns = columns
			}
			this.sidebarState.onFilterChange = ({ key, values }) => {
				this.onFilterChange(key, values)
			}
		},
		teardownSidebar() {
			if (!this.sidebarState) return
			this.sidebarState.active = false
			this.sidebarState.schema = null
			this.sidebarState.activeFilters = {}
			this.sidebarState.facetData = {}
			this.sidebarState.onSearch = null
			this.sidebarState.onColumnsChange = null
			this.sidebarState.onFilterChange = null
		},
		async fetchObjects(page = 1) {
			const params = {
				_limit: 20,
				_page: page,
			}
			if (this.searchTerm) {
				params._search = this.searchTerm
			}
			if (this.sortKey) {
				params._order = { [this.sortKey]: this.sortOrder }
			}
			if (this.sidebarState?.activeFilters) {
				for (const [key, values] of Object.entries(this.sidebarState.activeFilters)) {
					if (values && values.length > 0) {
						params[key] = values.length === 1 ? values[0] : values
					}
				}
			}
			await this.objectStore.fetchCollection(this.objectType, params)
			if (this.sidebarState) {
				this.sidebarState.facetData = this.objectStore.facets[this.objectType] || {}
			}
		},
		onFilterChange(key, values) {
			if (!this.sidebarState) return
			this.sidebarState.activeFilters = {
				...this.sidebarState.activeFilters,
				[key]: values && values.length > 0 ? values : undefined,
			}
			this.fetchObjects()
		},
		openObject(row) {
			this.$router.push({ name: this.detailRoute, params: { id: row.id } })
		},
		createNew() {
			this.$router.push({ name: this.detailRoute, params: { id: 'new' } })
		},
		onSearch(value) {
			this.searchTerm = value
			if (this.sidebarState) {
				this.sidebarState.searchValue = value
			}
			clearTimeout(this.searchTimeout)
			this.searchTimeout = setTimeout(() => {
				this.fetchObjects()
			}, 300)
		},
		onSort({ key, order }) {
			this.sortKey = key
			this.sortOrder = order
			this.fetchObjects()
		},
		loadPage(page) {
			this.fetchObjects(page)
		},
	},
}
</script>
