<script setup>
import { characterStore, skillStore, navigationStore } from '../../store/store.js'
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
				:loading="skillsLoading"
				:disabled="skillsLoading"
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
				:disabled="loading || skillsLoading"
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
			skills: {},
			selectedSkills: [],
			skillsLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	mounted() {
		this.fetchSkills()
	},
	updated() {
		if (navigationStore.modal === 'addSkillToCharacter' && !this.hasUpdated) {
			this.fetchSkills()
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
			this.selectedSkills = []
		},
		fetchSkills() {
			this.skillsLoading = true

			skillStore.refreshSkillList()
				.then(() => {
					// Create options from all available skills
					this.skills = {
						multiple: true,
						closeOnSelect: false,
						options: skillStore.skillList.map((skill) => ({
							id: skill.id,
							label: skill.name,
						})),
					}

					// Pre-select existing skills
					if (characterStore.characterItem?.skills?.length) {
						this.selectedSkills = characterStore.characterItem.skills.map(skill => {
							// Handle case where skill is just a UUID string
							if (typeof skill === 'string') {
								const skillData = skillStore.skillList.find(s => s.id === skill)
								return {
									id: skillData.id,
									label: skillData.name
								}
							}
							// Handle case where skill is an object
							return {
								id: skill.id,
								label: skill.name,
							}
						})
					}

					this.skillsLoading = false
				})
		},
		async saveSkills() {
			this.loading = true
			try {
				const characterItemClone = { ...characterStore.characterItem }
				
				// Replace skills array with selected skills
				characterItemClone.skills = this.selectedSkills.map(selected => {
					const skillData = skillStore.skillList.find(s => s.id === selected.id)
					return {
						objectType: 'skill',
						id: skillData.id,
						name: skillData.name,
						description: skillData.description || '',
						requiredScore: skillData.requiredScore || '',
						effects: skillData.effects || [],
					}
				})

				await characterStore.saveCharacter(characterItemClone)

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
