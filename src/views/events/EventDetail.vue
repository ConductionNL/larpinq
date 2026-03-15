<template>
	<CnDetailPage
		:title="eventData.name || t('larpingapp', 'Event')"
		:subtitle="t('larpingapp', 'Event')"
		:back-route="{ name: 'Events' }"
		:back-label="t('larpingapp', 'Terug naar lijst')"
		:loading="loading"
		:sidebar="!isNew && !loading"
		object-type="larpingapp_event"
		:object-id="eventId || ''"
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
					<span>{{ eventData.summary || '-' }}</span>
				</div>
			</div>
			<p v-if="eventData.description">
				{{ eventData.description }}
			</p>
		</CnDetailCard>

		<CnDetailCard :title="t('larpingapp', 'Karakters')">
			<ObjectList :objects="relations" />
		</CnDetailCard>

		<!-- Delete warning dialog -->
		<NcDialog
			v-if="showDelete"
			:name="t('larpingapp', 'Event verwijderen')"
			@closing="showDelete = false">
			<p>
				{{ t('larpingapp', 'Weet je zeker dat je "{name}" wilt verwijderen?', { name: eventData.name }) }}
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
import ObjectList from '../objects/ObjectList.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { useObjectStore } from '../../store/modules/object.js'

export default {
	name: 'EventDetail',
	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcDialog,
		CnDetailPage,
		CnDetailCard,
		ObjectList,
		DotsHorizontal,
		TrashCanOutline,
	},
	props: {
		eventId: {
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
			return !this.eventId || this.eventId === 'new'
		},
		loading() {
			return this.objectStore.loading?.event || false
		},
		eventData() {
			if (this.isNew) return {}
			return this.objectStore.getObject('event', this.eventId) || {}
		},
		relations() {
			return this.objectStore.getRelations('event') || []
		},
		sidebarProps() {
			const config = this.objectStore.objectTypeRegistry?.event || {}
			return {
				register: config.register || '',
				schema: config.schema || '',
			}
		},
	},
	async mounted() {
		if (!this.isNew) {
			await this.objectStore.fetchObject('event', this.eventId)
		}
	},
	methods: {
		async confirmDelete() {
			this.showDelete = false
			const success = await this.objectStore.deleteObject('event', this.eventId)
			if (success) {
				this.$router.push({ name: 'Events' })
			} else {
				const error = this.objectStore.getError('event')
				showError(error?.message || t('larpingapp', 'Event verwijderen mislukt.'))
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
