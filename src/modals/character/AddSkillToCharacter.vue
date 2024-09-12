<script setup>
import { characterStore, skillStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addSkillToCharacter'"
		name="Vaardigheid toevoegen aan karakter"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Vaardigheid succesvol toegevoegd aan karakter</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<p>Let op: Het toevoegen van een vaardigheid aan een karakter zal leiden tot een herberekening van de statistieken van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.</p>

			<NcSelect v-bind="skills"
				v-model="skills.value"
				input-label="Skill *"
				:loading="skillsLoading"
				:disabled="loading"
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
				@click="addSkillToCharacter()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Plus v-if="!loading" :size="20" />
				</template>
				Toevoegen
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
import Plus from 'vue-material-design-icons/Plus.vue'
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
		Plus,
		Help,
	},
	data() {
		return {
			success: false,
			loading: false,
			error: false,
			skills: {},
			skillsLoading: false,
			hasUpdated: false,
		}
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
		},
		fetchSkills() {
			this.skillsLoading = true

			skillStore.refreshSkillList()
				.then(() => {
					// Get all the skills NOT on the character
					const availableSkills = skillStore.skillList.filter((skill) => {
						return characterStore.characterItem.skills
							.map(String)
							.includes(skill.id.toString()) !== true
					})

					this.skills = {
						multiple: true,
						closeOnSelect: false,
						options: availableSkills.map((skill) => ({
							id: skill.id,
							label: skill.name,
						})),
					}

					this.skillsLoading = false
				})
		},
		async addSkillToCharacter() {
			this.loading = true
			try {
				const characterItemClone = { ...characterStore.characterItem }

				if (!characterItemClone.skills) {
					characterItemClone.skills = []
				}

				for (const selectedSkill of this.skills.value) {
					characterItemClone.skills.push(selectedSkill.id)
				}

				await characterStore.saveCharacter({
					...characterItemClone,
				})

				this.success = true
				this.loading = false
				this.error = false
				setTimeout(() => {
					this.closeModal()
				}, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'Er is een fout opgetreden bij het toevoegen van de vaardigheid aan het karakter'
			}
		},
	},
}
</script>
