<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Header actions for the larpingapp dashboard:
 - "New character" / "New item" / "New condition" buttons that open
   CnAdvancedFormDialog instances seeded with the relevant schema.
 - "Refresh" button that re-fetches the dashboard collections.

 Mounted via the manifest's `actionsComponent: "DashboardActions"`
 reference on the Dashboard page entry. CnPageRenderer resolves it
 against the customComponents registry and passes no props — the
 component fetches its own schemas + drives the shared object store.
-->
<template>
	<div class="dashboard-actions">
		<NcButton
			type="primary"
			:aria-label="t('larpingapp', 'New character')"
			@click="showCharacterDialog = true">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('larpingapp', 'New character') }}
		</NcButton>
		<NcButton
			:aria-label="t('larpingapp', 'New item')"
			@click="showItemDialog = true">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('larpingapp', 'New item') }}
		</NcButton>
		<NcButton
			:aria-label="t('larpingapp', 'New condition')"
			@click="showConditionDialog = true">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('larpingapp', 'New condition') }}
		</NcButton>
		<NcButton
			:disabled="loading"
			:aria-label="t('larpingapp', 'Refresh dashboard')"
			@click="refreshData">
			<template #icon>
				<NcLoadingIcon v-if="loading" :size="20" />
				<Refresh v-else :size="20" />
			</template>
		</NcButton>

		<CnAdvancedFormDialog
			v-if="showCharacterDialog && characterSchema"
			:schema="characterSchema"
			:cancel-label="t('larpingapp', 'Cancel')"
			:confirm-label="t('larpingapp', 'Create')"
			@confirm="onCreate('character', $event, 'CharacterDetail', () => { showCharacterDialog = false })"
			@close="showCharacterDialog = false" />

		<CnAdvancedFormDialog
			v-if="showItemDialog && itemSchema"
			:schema="itemSchema"
			:cancel-label="t('larpingapp', 'Cancel')"
			:confirm-label="t('larpingapp', 'Create')"
			@confirm="onCreate('item', $event, 'ItemDetail', () => { showItemDialog = false })"
			@close="showItemDialog = false" />

		<CnAdvancedFormDialog
			v-if="showConditionDialog && conditionSchema"
			:schema="conditionSchema"
			:cancel-label="t('larpingapp', 'Cancel')"
			:confirm-label="t('larpingapp', 'Create')"
			@confirm="onCreate('condition', $event, 'ConditionDetail', () => { showConditionDialog = false })"
			@close="showConditionDialog = false" />
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { CnAdvancedFormDialog } from '@conduction/nextcloud-vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import { useObjectStore } from '../../store/modules/object.js'

export default {
	name: 'DashboardActions',
	components: { NcButton, NcLoadingIcon, CnAdvancedFormDialog, Plus, Refresh },
	data() {
		return {
			loading: false,
			showCharacterDialog: false,
			showItemDialog: false,
			showConditionDialog: false,
			characterSchema: null,
			itemSchema: null,
			conditionSchema: null,
		}
	},
	computed: {
		/**
		 * @spec exclude Pinia store accessor passthrough — framework glue.
		 */
		objectStore() {
			return useObjectStore()
		},
	},
	async mounted() {
		await this.refreshData()
	},
	methods: {
		/**
		 * @param type
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-3
		 */
		async loadSchema(type) {
			const config = this.objectStore.objectTypeRegistry?.[type]
			if (!config) return null
			try {
				const resp = await fetch(`/index.php/apps/openregister/api/schemas/${config.schema}`, {
					headers: { 'Content-Type': 'application/json', 'OCS-APIREQUEST': 'true', requesttoken: OC.requestToken },
				})
				return resp.ok ? await resp.json() : null
			} catch {
				return null
			}
		},
		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-3
		 */
		async refreshData() {
			this.loading = true
			try {
				const [charSchema, itemSchema, condSchema] = await Promise.all([
					this.loadSchema('character'),
					this.loadSchema('item'),
					this.loadSchema('condition'),
				])
				this.characterSchema = charSchema
				this.itemSchema = itemSchema
				this.conditionSchema = condSchema
				await Promise.allSettled([
					this.objectStore.fetchCollection('character', { _limit: 5 }),
					this.objectStore.fetchCollection('event', { _limit: 5 }),
					this.objectStore.fetchCollection('item', { _limit: 1 }),
					this.objectStore.fetchCollection('player', { _limit: 1 }),
				])
			} catch (error) {
				console.error('Failed to load dashboard data:', error)
			} finally {
				this.loading = false
			}
		},
		/**
		 * @param type
		 * @param formData
		 * @param detailRouteName
		 * @param onSuccess
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-3
		 */
		async onCreate(type, formData, detailRouteName, onSuccess) {
			const result = await this.objectStore.saveObject(type, formData)
			if (result) {
				onSuccess()
				this.$router.push({ name: detailRouteName, params: { id: result.id } })
			}
		},
	},
}
</script>

<style scoped>
.dashboard-actions {
	display: flex;
	gap: 8px;
	align-items: center;
}
</style>
