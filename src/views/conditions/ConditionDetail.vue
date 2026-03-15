<template>
	<CnDetailPage
		:title="conditionData.name || t('larpingapp', 'Condition')"
		:subtitle="t('larpingapp', 'Condition')"
		:back-route="{ name: 'Conditions' }"
		:back-label="t('larpingapp', 'Terug naar lijst')"
		:loading="loading"
		:sidebar="!isNew && !loading"
		object-type="larpingapp_condition"
		:object-id="conditionId || ''"
		:sidebar-props="sidebarProps">
		<template #header-actions>
			<NcActions :primary="true" :menu-name="t('larpingapp', 'Acties')">
				<template #icon>
					<DotsHorizontal :size="20" />
				</template>
				<NcActionButton @click="showDelete = true">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
					{{ t('larpingapp', 'Verwijderen') }}
				</NcActionButton>
			</NcActions>
		</template>

		<CnDetailCard :title="t('larpingapp', 'Details')">
			<div class="info-grid">
				<div class="info-field">
					<label>{{ t('larpingapp', 'Samenvatting') }}</label>
					<span>{{ conditionData.summary || '-' }}</span>
				</div>
			</div>
			<p v-if="conditionData.description">
				{{ conditionData.description }}
			</p>
		</CnDetailCard>

		<!-- Delete warning dialog -->
		<NcDialog
			v-if="showDelete"
			:name="t('larpingapp', 'Condition verwijderen')"
			@closing="showDelete = false">
			<p>
				{{ t('larpingapp', 'Weet je zeker dat je "{name}" wilt verwijderen?', { name: conditionData.name }) }}
			</p>
			<template #actions>
				<NcButton @click="showDelete = false">
					{{ t('larpingapp', 'Annuleren') }}
				</NcButton>
				<NcButton type="error" @click="confirmDelete">
					{{ t('larpingapp', 'Verwijderen') }}
				</NcButton>
			</template>
		</NcDialog>
	</CnDetailPage>
</template>

<script>
import { NcActions, NcActionButton, NcButton, NcDialog } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { CnDetailPage, CnDetailCard } from '@conduction/nextcloud-vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { useObjectStore } from '../../store/modules/object.js'

export default {
	name: 'ConditionDetail',
	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcDialog,
		CnDetailPage,
		CnDetailCard,
		DotsHorizontal,
		TrashCanOutline,
	},
	props: {
		conditionId: {
			type: String,
			default: null,
		},
	},
	data() {
		return {
			showDelete: false,
		}
	},
	computed: {
		objectStore() {
			return useObjectStore()
		},
		isNew() {
			return !this.conditionId || this.conditionId === 'new'
		},
		loading() {
			return this.objectStore.loading?.condition || false
		},
		conditionData() {
			if (this.isNew) return {}
			return this.objectStore.getObject('condition', this.conditionId) || {}
		},
		sidebarProps() {
			const config = this.objectStore.objectTypeRegistry?.condition || {}
			return {
				register: config.register || '',
				schema: config.schema || '',
			}
		},
	},
	async mounted() {
		if (!this.isNew) {
			await this.objectStore.fetchObject('condition', this.conditionId)
		}
	},
	methods: {
		async confirmDelete() {
			this.showDelete = false
			const success = await this.objectStore.deleteObject('condition', this.conditionId)
			if (success) {
				this.$router.push({ name: 'Conditions' })
			} else {
				const error = this.objectStore.getError('condition')
				showError(error?.message || t('larpingapp', 'Condition verwijderen mislukt.'))
			}
		},
	},
}
</script>

<style scoped>
.info-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

.info-field {
	margin-bottom: 8px;
}

.info-field label {
	display: block;
	font-weight: bold;
	margin-bottom: 2px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
</style>
