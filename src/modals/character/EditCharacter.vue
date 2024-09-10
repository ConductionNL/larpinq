<script setup>
import { characterStore, navigationStore, skillStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editCharacter'"
		name="Karakter"
		size="normal"
		:can-close="false"
		@close="closeModal">
		<NcNoteCard v-if="success" type="success">
			<p>Karakter succesvol aangepast</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcTextField :disabled="loading"
				label="Name *"
				required
				:value.sync="characterItem.name" />
			<NcTextField :disabled="loading"
				label="OC Name *"
				required
				:value.sync="characterItem.OCName" />
			<NcTextArea :disabled="loading"
				label="Description"
				:value.sync="characterItem.description" />
			<NcSelect v-bind="skills"
				v-model="skills.value"
				input-label="Skills"
				:loading="skillsLoading"
				:disabled="loading" />
		</div>

		<template #actions>
			<NcButton
				@click="navigationStore.setModal(false)">
				<template #icon>
					<Cancel :size="20" />
				</template>
				{{ success ? 'Sluiten' : 'Annuleer' }}
			</NcButton>
			<NcButton
				@click="openLink('https://conduction.gitbook.io/opencatalogi-nextcloud/gebruikers/publicaties', '_blank')">
				<template #icon>
					<Help :size="20" />
				</template>
				Help
			</NcButton>
			<NcButton
				v-if="!success"
				:disabled="loading"
				type="primary"
				@click="editCharacter()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && characterItem.id" :size="20" />
					<Plus v-if="!loading && !characterItem.id" :size="20" />
				</template>
				{{ characterItem.id ? 'Opslaan' : 'Aanmaken' }}
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
	NcSelect,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Help from 'vue-material-design-icons/Help.vue'

export default {
	name: 'EditCharacter',
	components: {
		NcDialog,
		NcTextField,
		NcTextArea,
		NcButton,
		NcSelect,
		NcLoadingIcon,
		NcNoteCard,
		// Icons
		ContentSaveOutline,
		Cancel,
		Plus,
		Help,
	},
	data() {
		return {
			characterItem: {
				name: '',
				OCName: '',
				description: '',
			},
			skills: {},
			skillsLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'editCharacter' && !this.hasUpdated) {
			if (characterStore.characterItem.id) {
				this.characterItem = {
					...characterStore.characterItem,
					name: characterStore.characterItem.name || '',
					description: characterStore.characterItem.description || '',
				}
			}
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
			this.characterItem = {
				name: '',
				OCName: '',
				description: '',
			}
		},
		fetchSkills() {
			this.skillsLoading = true

			skillStore.refreshSkillList()
				.then(() => {
					const activatedSkills = characterStore.characterItem.id // if modal is an edit modal
						? skillStore.skillList.filter((skill) => { // filter through the list of skills
							return characterStore.characterItem.skills
								.map(String) // ensure all the skill id's in the character are a string (this does not change the resulting data type)
								.includes(skill.id.toString()) // check if the current skill in the filter exists on the character's skills
						})
						: null

					this.skills = {
						multiple: true,
						closeOnSelect: false,
						options: skillStore.skillList.map((skill) => ({
							id: skill.id,
							label: skill.name,
						})),
						value: activatedSkills
							? activatedSkills.map((skill) => ({
								id: skill.id,
							    label: skill.name,
							}))
							: null,
					}

					this.skillsLoading = false
				})
		},
		async editCharacter() {
			this.loading = true
			try {
				await characterStore.saveCharacter({
					...this.characterItem,
					skills: this.skills.value.map((skill) => skill.id),
				})
				// Close modal or show success message
				this.success = true
				this.loading = false
				this.error = false
				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'An error occurred while saving the character'
			}
		},
	},
}
</script>
