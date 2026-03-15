<template>
	<CnDetailPage
		:title="skillData.name || t('larpingapp', 'Skill')"
		:subtitle="t('larpingapp', 'Skill')"
		:back-route="{ name: 'Skills' }"
		:back-label="t('larpingapp', 'Terug naar lijst')"
		:loading="loading"
		:sidebar="!isNew && !loading"
		object-type="larpingapp_skill"
		:object-id="skillId || ''"
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
					<span>{{ skillData.summary || '-' }}</span>
				</div>
			</div>
			<p v-if="skillData.description">
				{{ skillData.description }}
			</p>
		</CnDetailCard>

		<CnDetailCard :title="t('larpingapp', 'Effects')">
			<div v-if="skillData.effects && skillData.effects.length > 0">
				<NcListItem
					v-for="effect in skillData.effects"
					:key="effect.id"
					:name="effect.name"
					:bold="false"
					:force-display-actions="true"
					:details="effect.modification || ''"
					:counter-number="effect.modifier">
					<template #icon>
						<MagicStaff disable-menu :size="44" />
					</template>
					<template #subname>
						{{ effect.name }}
					</template>
				</NcListItem>
			</div>
			<div v-else>
				{{ t('larpingapp', 'Geen effects gevonden') }}
			</div>
		</CnDetailCard>

		<CnDetailCard :title="t('larpingapp', 'Karakters')">
			<ObjectList :objects="relations" />
		</CnDetailCard>

		<!-- Delete warning dialog -->
		<NcDialog
			v-if="showDelete"
			:name="t('larpingapp', 'Skill verwijderen')"
			@closing="showDelete = false">
			<p>
				{{ t('larpingapp', 'Weet je zeker dat je "{name}" wilt verwijderen?', { name: skillData.name }) }}
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
import { NcActions, NcActionButton, NcListItem, NcButton, NcDialog } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { CnDetailPage, CnDetailCard } from '@conduction/nextcloud-vue'
import ObjectList from '../objects/ObjectList.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import { useObjectStore } from '../../store/modules/object.js'

export default {
	name: 'SkillDetail',
	components: {
		NcActions,
		NcActionButton,
		NcListItem,
		NcButton,
		NcDialog,
		CnDetailPage,
		CnDetailCard,
		ObjectList,
		DotsHorizontal,
		TrashCanOutline,
		MagicStaff,
	},
	props: {
		skillId: {
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
			return !this.skillId || this.skillId === 'new'
		},
		loading() {
			return this.objectStore.loading?.skill || false
		},
		skillData() {
			if (this.isNew) return {}
			return this.objectStore.getObject('skill', this.skillId) || {}
		},
		relations() {
			return this.objectStore.getRelations('skill') || []
		},
		sidebarProps() {
			const config = this.objectStore.objectTypeRegistry?.skill || {}
			return {
				register: config.register || '',
				schema: config.schema || '',
			}
		},
	},
	async mounted() {
		if (!this.isNew) {
			await this.objectStore.fetchObject('skill', this.skillId)
		}
	},
	methods: {
		async confirmDelete() {
			this.showDelete = false
			const success = await this.objectStore.deleteObject('skill', this.skillId)
			if (success) {
				this.$router.push({ name: 'Skills' })
			} else {
				const error = this.objectStore.getError('skill')
				showError(error?.message || t('larpingapp', 'Skill verwijderen mislukt.'))
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
