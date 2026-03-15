<template>
	<CnDetailPage
		:title="effectData.name || t('larpingapp', 'Effect')"
		:subtitle="t('larpingapp', 'Effect')"
		:back-route="{ name: 'Effects' }"
		:back-label="t('larpingapp', 'Terug naar lijst')"
		:loading="loading"
		:sidebar="!isNew && !loading"
		object-type="larpingapp_effect"
		:object-id="effectId || ''"
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
					<span>{{ effectData.summary || '-' }}</span>
				</div>
				<div class="info-field">
					<label>{{ t('larpingapp', 'Modificatie') }}</label>
					<span>{{ effectData.modification || '-' }}</span>
				</div>
				<div class="info-field">
					<label>{{ t('larpingapp', 'Modifier') }}</label>
					<span>{{ effectData.modifier || '-' }}</span>
				</div>
			</div>
			<p v-if="effectData.description">
				{{ effectData.description }}
			</p>
		</CnDetailCard>

		<!-- Delete warning dialog -->
		<NcDialog
			v-if="showDelete"
			:name="t('larpingapp', 'Effect verwijderen')"
			@closing="showDelete = false">
			<p>
				{{ t('larpingapp', 'Weet je zeker dat je "{name}" wilt verwijderen?', { name: effectData.name }) }}
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
	name: 'EffectDetail',
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
		effectId: {
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
			return !this.effectId || this.effectId === 'new'
		},
		loading() {
			return this.objectStore.loading?.effect || false
		},
		effectData() {
			if (this.isNew) return {}
			return this.objectStore.getObject('effect', this.effectId) || {}
		},
		sidebarProps() {
			const config = this.objectStore.objectTypeRegistry?.effect || {}
			return {
				register: config.register || '',
				schema: config.schema || '',
			}
		},
	},
	async mounted() {
		if (!this.isNew) {
			await this.objectStore.fetchObject('effect', this.effectId)
		}
	},
	methods: {
		async confirmDelete() {
			this.showDelete = false
			const success = await this.objectStore.deleteObject('effect', this.effectId)
			if (success) {
				this.$router.push({ name: 'Effects' })
			} else {
				const error = this.objectStore.getError('effect')
				showError(error?.message || t('larpingapp', 'Effect verwijderen mislukt.'))
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
