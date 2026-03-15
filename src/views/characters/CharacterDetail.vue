<template>
	<CnDetailPage
		:title="characterData.name || t('larpingapp', 'Karakter')"
		:subtitle="t('larpingapp', 'Karakter')"
		:back-route="{ name: 'Characters' }"
		:back-label="t('larpingapp', 'Terug naar lijst')"
		:loading="loading"
		:sidebar="!isNew && !loading"
		object-type="larpingapp_character"
		:object-id="characterId || ''"
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

		<NcNoteCard v-if="characterData.notice" type="info">
			{{ characterData.notice }}
		</NcNoteCard>

		<CnDetailCard :title="t('larpingapp', 'Details')">
			<div class="info-grid">
				<div class="info-field">
					<label>{{ t('larpingapp', 'Samenvatting') }}</label>
					<span>{{ characterData.summary || '-' }}</span>
				</div>
			</div>
			<p v-if="characterData.description">
				{{ characterData.description }}
			</p>
		</CnDetailCard>

		<CnDetailCard :title="t('larpingapp', 'Eigenschappen')">
			<div v-if="characterData.stats">
				<NcListItem
					v-for="(stat, id) in characterData.stats"
					:key="id"
					:name="stat.name"
					:bold="false">
					<template #icon>
						<ShieldSwordOutline :size="44" />
					</template>
					<template #subname>
						Score: {{ stat.value }}
					</template>
				</NcListItem>
			</div>
			<div v-else>
				{{ t('larpingapp', 'Geen eigenschappen gevonden') }}
			</div>
		</CnDetailCard>

		<CnDetailCard :title="t('larpingapp', 'Skills')">
			<ObjectList :objects="characterData.skills || []" />
		</CnDetailCard>

		<CnDetailCard :title="t('larpingapp', 'Items')">
			<ObjectList :objects="characterData.items || []" />
		</CnDetailCard>

		<CnDetailCard :title="t('larpingapp', 'Conditions')">
			<ObjectList :objects="characterData.conditions || []" />
		</CnDetailCard>

		<CnDetailCard :title="t('larpingapp', 'Events')">
			<ObjectList :objects="characterData.events || []" />
		</CnDetailCard>

		<CnDetailCard :title="t('larpingapp', 'Achtergrond')">
			<div v-if="characterData.background">
				{{ characterData.background }}
			</div>
			<div v-else>
				{{ t('larpingapp', 'Geen achtergrond gevonden') }}
			</div>
		</CnDetailCard>

		<!-- Delete warning dialog -->
		<NcDialog
			v-if="showDelete"
			:name="t('larpingapp', 'Karakter verwijderen')"
			@closing="showDelete = false">
			<p>
				{{ t('larpingapp', 'Weet je zeker dat je "{name}" wilt verwijderen?', { name: characterData.name }) }}
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
import { NcActions, NcActionButton, NcListItem, NcNoteCard, NcButton, NcDialog } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { CnDetailPage, CnDetailCard } from '@conduction/nextcloud-vue'
import ObjectList from '../objects/ObjectList.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'
import { useObjectStore } from '../../store/modules/object.js'

export default {
	name: 'CharacterDetail',
	components: {
		NcActions,
		NcActionButton,
		NcListItem,
		NcNoteCard,
		NcButton,
		NcDialog,
		CnDetailPage,
		CnDetailCard,
		ObjectList,
		DotsHorizontal,
		TrashCanOutline,
		ShieldSwordOutline,
	},
	props: {
		characterId: {
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
			return !this.characterId || this.characterId === 'new'
		},
		loading() {
			return this.objectStore.loading?.character || false
		},
		characterData() {
			if (this.isNew) return {}
			return this.objectStore.getObject('character', this.characterId) || {}
		},
		sidebarProps() {
			const config = this.objectStore.objectTypeRegistry?.character || {}
			return {
				register: config.register || '',
				schema: config.schema || '',
			}
		},
	},
	async mounted() {
		if (!this.isNew) {
			await this.objectStore.fetchObject('character', this.characterId)
		}
	},
	methods: {
		async confirmDelete() {
			this.showDelete = false
			const success = await this.objectStore.deleteObject('character', this.characterId)
			if (success) {
				this.$router.push({ name: 'Characters' })
			} else {
				const error = this.objectStore.getError('character')
				showError(error?.message || t('larpingapp', 'Karakter verwijderen mislukt.'))
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
