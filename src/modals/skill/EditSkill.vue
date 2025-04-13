<script setup>
import { navigationStore, objectStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editSkill'"
		name="Skill"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Skill succesvol aangepast</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcTextField :disabled="loading"
				label="Name *"
				required
				:value.sync="skillItem.name" />
			<NcTextArea :disabled="loading"
				label="Description"
				type="textarea"
				:value.sync="skillItem.description" />
			<NcTextField :disabled="loading"
				label="Required Score"
				type="number"
				:value.sync="skillItem.requiredScore" />
			<NcSelect v-bind="effects"
				v-model="effects.value"
				input-label="Effects"
				:loading="effectsLoading"
				:disabled="loading" />
		</div>

		<template #actions>
			<NcButton @click="closeModal">
				<template #icon>
					<Cancel :size="20" />
				</template>
				{{ success ? 'Sluiten' : 'Annuleer' }}
			</NcButton>
			<NcButton @click="openLink('https://conduction.gitbook.io/opencatalogi-nextcloud/gebruikers/publicaties', '_blank')">
				<template #icon>
					<Help :size="20" />
				</template>
				Help
			</NcButton>
			<NcButton v-if="!success"
				:disabled="loading"
				type="primary"
				@click="editSkill()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && objectStore.getActiveObject('skill')?.id" :size="20" />
					<Plus v-if="!loading && !objectStore.getActiveObject('skill')?.id" :size="20" />
				</template>
				{{ objectStore.getActiveObject('skill')?.id ? 'Opslaan' : 'Aanmaken' }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
	NcTextField,
	NcTextArea,
	NcLoadingIcon,
	NcNoteCard,
	NcSelect,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Help from 'vue-material-design-icons/Help.vue'

export default {
	name: 'EditSkill',
	components: {
		NcDialog,
		NcTextField,
		NcTextArea,
		NcSelect,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		ContentSaveOutline,
		Cancel,
		Plus,
		Help,
	},
	data() {
		return {
			skillItem: {
				name: '',
				description: '',
				requiredScore: '',
			},
			effects: {
				multiple: true,
				closeOnSelect: false,
				options: [],
				value: null,
			},
			effectsLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'editSkill' && !this.hasUpdated) {
			const activeSkill = objectStore.getActiveObject('skill')
			if (activeSkill?.id) {
				this.skillItem = {
					...activeSkill,
					name: activeSkill.name || '',
					description: activeSkill.description || '',
					requiredScore: activeSkill.requiredScore || '',
				}

				const activeEffects = objectStore.getCollection('effect').results.filter((effect) => {
					return (activeSkill.effects || [])
						.map(String)
						.includes(effect.id.toString())
				})

				this.effects.value = activeEffects.map((effect) => ({
					id: effect.id,
					label: effect.name,
				}))
			}

			this.effects.options = objectStore.getCollection('effect').results.map((effect) => ({
				id: effect.id,
				label: effect.name,
			}))

			this.hasUpdated = true
		}
	},
	methods: {
		closeModal() {
			navigationStore.setModal(false)
			this.success = false
			this.loading = false
			this.error = false
			this.hasUpdated = false
			this.skillItem = {
				name: '',
				description: '',
				requiredScore: '',
			}
			this.effects.value = null
			this.effects.options = []
			objectStore.setActiveObject('skill', null)
		},
		async editSkill() {
			this.loading = true
			try {
				await objectStore.saveObject('skill', {
					...this.skillItem,
					effects: (this.effects?.value || []).map((effect) => effect.id),
				})
				this.success = true
				this.loading = false
				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'An error occurred while saving the skill'
			}
		},
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>
