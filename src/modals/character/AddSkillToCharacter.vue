<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addSkillToCharacter'"
		name="Vaardigheden bewerken"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Vaardigheden succesvol bijgewerkt</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<p>Let op: Het bewerken van vaardigheden zal leiden tot een herberekening van de statistieken van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.</p>

			<NcSelect v-bind="skills"
				v-model="selectedSkills"
				input-label="Vaardigheden *"
				required />
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
				@click="saveSkills()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Save v-if="!loading" :size="20" />
				</template>
				Opslaan
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
	NcSelect,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

import Cancel from 'vue-material-design-icons/Cancel.vue'
import Save from 'vue-material-design-icons/ContentSave.vue'
import Help from 'vue-material-design-icons/Help.vue'

export default {
	name: 'AddSkillToCharacter',
	components: {
		NcDialog,
		NcButton,
		NcSelect,
		NcLoadingIcon,
		NcNoteCard,
		// Icons
		Cancel,
		Save,
		Help,
	},
	data() {
		return {
			skills: {
				multiple: true,
				closeOnSelect: false,
				options: [],
			},
			selectedSkills: [],
			skillsLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'addSkillToCharacter' && !this.hasUpdated) {
			const activeSkill = objectStore.getActiveObject('skill')
			if (activeSkill?.id) {
				this.skillItem = activeSkill
			}
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
			this.skillItem = null
			objectStore.setActiveObject('skill', null)
		},
		async saveSkills() {
			this.loading = true
			try {
				const character = objectStore.getActiveObject('character')
				if (!character?.id) {
					throw new Error('No character selected')
				}

				// Create updated character data
				const characterData = { ...character }

				// Replace skills array with selected skills
				characterData.skills = this.selectedSkills.map(selected => {
					const skillData = objectStore.skillList.find(s => s.id === selected.id)
					return {
						objectType: 'skill',
						id: skillData.id,
						name: skillData.name,
						description: skillData.description || '',
						requiredScore: skillData.requiredScore || '',
						effects: skillData.effects || [],
					}
				})

				await objectStore.updateObject('character', character.id, characterData)

				this.success = true
				this.loading = false
				this.error = false
				setTimeout(() => {
					this.closeModal()
				}, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'Er is een fout opgetreden bij het bewerken van de vaardigheden'
			}
		},
	},
}
</script>
