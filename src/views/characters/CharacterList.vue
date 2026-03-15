<template>
	<div>
		<CnIndexPage
			:title="t('larpingapp', 'Karakters')"
			:schema="characterSchema"
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
			:schema="characterSchema"
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
	name: 'CharacterList',
	components: {
		CnIndexPage,
		CnAdvancedFormDialog,
	},

	data() {
		return {
			showCreateDialog: false,
			sortKey: null,
			sortOrder: 'asc',
			characterSchema: null,
		}
	},

	computed: {
		objectStore() {
			return useObjectStore()
		},
		objects() {
			return this.objectStore.collections?.character || []
		},
		loading() {
			return this.objectStore.loading?.character || false
		},
		pagination() {
			return this.objectStore.pagination?.character || { total: 0, page: 1, pages: 1, limit: 20 }
		},
	},

	async mounted() {
		this.characterSchema = await this.objectStore.fetchSchema('character')
		await this.fetchCollection()
	},

	methods: {
		openItem(row) {
			this.$router.push({ name: 'CharacterDetail', params: { id: row.id } })
		},
		async fetchCollection(page = 1) {
			const params = {
				_page: page,
				_limit: 20,
				_order: this.sortKey ? { [this.sortKey]: this.sortOrder } : undefined,
			}
			await this.objectStore.fetchCollection('character', params)
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
			const result = await this.objectStore.saveObject('character', formData)
			if (result) {
				this.showCreateDialog = false
				this.fetchCollection()
				this.$router.push({ name: 'CharacterDetail', params: { id: result.id } })
			}
		},
	},
}
</script>
