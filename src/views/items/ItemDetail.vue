<template>
	<CnDetailPage
		:title="itemData.name || t('larpingapp', 'Item')"
		:subtitle="t('larpingapp', 'Item')"
		:back-route="{ name: 'Items' }"
		:back-label="t('larpingapp', 'Terug naar lijst')"
		:loading="loading"
		:sidebar="!isNew && !loading"
		object-type="larpingapp_item"
		:object-id="itemId || ''"
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
					<span>{{ itemData.summary || '-' }}</span>
				</div>
			</div>
			<p v-if="itemData.description">
				{{ itemData.description }}
			</p>
		</CnDetailCard>

		<!-- Delete warning dialog -->
		<NcDialog
			v-if="showDelete"
			:name="t('larpingapp', 'Item verwijderen')"
			@closing="showDelete = false">
			<p>
				{{ t('larpingapp', 'Weet je zeker dat je "{name}" wilt verwijderen?', { name: itemData.name }) }}
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
	name: 'ItemDetail',
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
		itemId: {
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
			return !this.itemId || this.itemId === 'new'
		},
		loading() {
			return this.objectStore.loading?.item || false
		},
		itemData() {
			if (this.isNew) return {}
			return this.objectStore.getObject('item', this.itemId) || {}
		},
		sidebarProps() {
			const config = this.objectStore.objectTypeRegistry?.item || {}
			return {
				register: config.register || '',
				schema: config.schema || '',
			}
		},
	},
	async mounted() {
		if (!this.isNew) {
			await this.objectStore.fetchObject('item', this.itemId)
		}
	},
	methods: {
		async confirmDelete() {
			this.showDelete = false
			const success = await this.objectStore.deleteObject('item', this.itemId)
			if (success) {
				this.$router.push({ name: 'Items' })
			} else {
				const error = this.objectStore.getError('item')
				showError(error?.message || t('larpingapp', 'Item verwijderen mislukt.'))
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
