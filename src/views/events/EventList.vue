<template>
	<div>
		<CnIndexPage
			:title="t('larpingapp', 'Events')"
			:schema="eventSchema"
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
			:schema="eventSchema"
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
	name: 'EventList',
	components: {
		CnIndexPage,
		CnAdvancedFormDialog,
	},

	data() {
		return {
			showCreateDialog: false,
			sortKey: null,
			sortOrder: 'asc',
			eventSchema: null,
		}
	},

	computed: {
		objectStore() {
			return useObjectStore()
		},
		objects() {
			return this.objectStore.collections?.event || []
		},
		loading() {
			return this.objectStore.loading?.event || false
		},
		pagination() {
			return this.objectStore.pagination?.event || { total: 0, page: 1, pages: 1, limit: 20 }
		},
	},

	async mounted() {
		this.eventSchema = await this.objectStore.fetchSchema('event')
		await this.fetchCollection()
	},

	methods: {
		openItem(row) {
			this.$router.push({ name: 'EventDetail', params: { id: row.id } })
		},
		async fetchCollection(page = 1) {
			await this.objectStore.fetchCollection('event', {
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
			const result = await this.objectStore.saveObject('event', formData)
			if (result) {
				this.showCreateDialog = false
				this.fetchCollection()
				this.$router.push({ name: 'EventDetail', params: { id: result.id } })
			}
		},
	},
}
</script>
