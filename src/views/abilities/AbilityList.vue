<template>
	<div>
		<CnIndexPage
			:title="t('larpingapp', 'Abilities')"
			:schema="abilitySchema"
			:objects="objects"
			:pagination="pagination"
			:loading="loading"
			:sort-key="sortKey"
			:sort-order="sortOrder"
			@add="showCreateDialog = true"
			@refresh="fetchCollection"
			@sort="onSort"
			@row-click="openItem"
			@page-changed="onPageChange" />

		<CnAdvancedFormDialog
			v-if="showCreateDialog"
			:schema="abilitySchema"
			:cancel-label="t('larpingapp', 'Annuleren')"
			:confirm-label="t('larpingapp', 'Aanmaken')"
			@confirm="onCreateConfirm"
			@close="showCreateDialog = false" />
	</div>
</template>

<script>
import { CnIndexPage, CnAdvancedFormDialog } from '@conduction/nextcloud-vue'
import { useObjectStore } from '../../store/modules/object.js'

export default {
	name: 'AbilityList',
	components: {
		CnIndexPage,
		CnAdvancedFormDialog,
	},

	data() {
		return {
			showCreateDialog: false,
			sortKey: null,
			sortOrder: 'asc',
			abilitySchema: null,
		}
	},

	computed: {
		objectStore() {
			return useObjectStore()
		},
		objects() {
			return this.objectStore.collections?.ability || []
		},
		loading() {
			return this.objectStore.loading?.ability || false
		},
		pagination() {
			return this.objectStore.pagination?.ability || { total: 0, page: 1, pages: 1, limit: 20 }
		},
	},

	async mounted() {
		this.abilitySchema = await this.objectStore.fetchSchema('ability')
		await this.fetchCollection()
	},

	methods: {
		openItem(row) {
			this.$router.push({ name: 'AbilityDetail', params: { id: row.id } })
		},
		async fetchCollection(page = 1) {
			await this.objectStore.fetchCollection('ability', {
				_page: page,
				_limit: 20,
				_order: this.sortKey ? { [this.sortKey]: this.sortOrder } : undefined,
			})
		},
		onSort({ key, order }) {
			this.sortKey = key
			this.sortOrder = order
			this.fetchCollection()
		},
		onPageChange(page) {
			this.fetchCollection(page)
		},
		async onCreateConfirm(formData) {
			const result = await this.objectStore.saveObject('ability', formData)
			if (result) {
				this.showCreateDialog = false
				this.fetchCollection()
				this.$router.push({ name: 'AbilityDetail', params: { id: result.id } })
			}
		},
	},
}
</script>
