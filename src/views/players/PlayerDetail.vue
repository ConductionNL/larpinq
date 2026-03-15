<template>
	<CnDetailPage
		:title="playerData.name || t('larpingapp', 'Speler')"
		:subtitle="t('larpingapp', 'Speler')"
		:back-route="{ name: 'Players' }"
		:back-label="t('larpingapp', 'Terug naar lijst')"
		:loading="loading"
		:sidebar="!isNew && !loading"
		object-type="larpingapp_player"
		:object-id="playerId || ''"
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
					<span>{{ playerData.summary || '-' }}</span>
				</div>
			</div>
			<p v-if="playerData.description">
				{{ playerData.description }}
			</p>
		</CnDetailCard>

		<!-- Delete warning dialog -->
		<NcDialog
			v-if="showDelete"
			:name="t('larpingapp', 'Speler verwijderen')"
			@closing="showDelete = false">
			<p>
				{{ t('larpingapp', 'Weet je zeker dat je "{name}" wilt verwijderen?', { name: playerData.name }) }}
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
	name: 'PlayerDetail',
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
		playerId: {
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
			return !this.playerId || this.playerId === 'new'
		},
		loading() {
			return this.objectStore.loading?.player || false
		},
		playerData() {
			if (this.isNew) return {}
			return this.objectStore.getObject('player', this.playerId) || {}
		},
		sidebarProps() {
			const config = this.objectStore.objectTypeRegistry?.player || {}
			return {
				register: config.register || '',
				schema: config.schema || '',
			}
		},
	},
	async mounted() {
		if (!this.isNew) {
			await this.objectStore.fetchObject('player', this.playerId)
		}
	},
	methods: {
		async confirmDelete() {
			this.showDelete = false
			const success = await this.objectStore.deleteObject('player', this.playerId)
			if (success) {
				this.$router.push({ name: 'Players' })
			} else {
				const error = this.objectStore.getError('player')
				showError(error?.message || t('larpingapp', 'Speler verwijderen mislukt.'))
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
