<template>
	<div>
		<CnIndexPage
			:title="t('larpingapp', 'Conditions')"
			:schema="schema"
			:objects="objects"
			:pagination="pagination"
			:loading="loading"
			:sort-key="sortKey"
			:sort-order="sortOrder"
			:include-columns="visibleColumns"
			@add="showCreateDialog = true"
			@refresh="refresh"
			@sort="onSort"
			@row-click="openItem"
			@page-changed="onPageChange" />

		<CnAdvancedFormDialog
			v-if="showCreateDialog"
			:schema="schema"
			:cancel-label="t('larpingapp', 'Annuleren')"
			:confirm-label="t('larpingapp', 'Aanmaken')"
			@confirm="onCreateConfirm"
			@close="showCreateDialog = false" />
	</div>
</template>

<script>
import { inject } from 'vue'
import { CnIndexPage, CnAdvancedFormDialog, useListView } from '@conduction/nextcloud-vue'
import { useObjectStore } from '../../store/modules/object.js'

export default {
	name: 'ConditionList',
	components: {
		CnIndexPage,
		CnAdvancedFormDialog,
	},

	setup() {
		const sidebarState = inject('sidebarState', null)
		return useListView('condition', { sidebarState })
	},

	data() {
		return {
			showCreateDialog: false,
		}
	},

	computed: {
		objectStore() {
			return useObjectStore()
		},
	},

	methods: {
		openItem(row) {
			this.$router.push({ name: 'ConditionDetail', params: { id: row.id } })
		},
		async onCreateConfirm(formData) {
			const result = await this.objectStore.saveObject('condition', formData)
			if (result) {
				this.showCreateDialog = false
				this.refresh()
				this.$router.push({ name: 'ConditionDetail', params: { id: result.id } })
			}
		},
	},
}
</script>
