<script setup>
import { characterStore, navigationStore, playerStore, skillStore } from '../../store/store.js'
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
			<NcTextArea :disabled="loading"
				label="Description"
				:value.sync="characterItem.description" />
			<NcSelect v-bind="players"
				v-model="players.value"
				input-label="OC Name *"
				:loading="playersLoading"
				:disabled="playersLoading || loading" />
			<NcSelect v-bind="skills"
				v-model="skills.value"
				input-label="Skills"
				:loading="skillsLoading"
				:disabled="skillsLoading || loading" />
		</div>

		<template #actions>
			<NcButton
				@click="closeModal">
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
				ocName: '',
				description: '',
			},
			skills: {},
			skillsLoading: false,
			players: {},
			playersLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	mounted() {
		this.fetchSkills()
		this.fetchPlayers()
	},
	updated() {
		if (navigationStore.modal === 'editCharacter' && !this.hasUpdated) {
			if (characterStore.characterItem?.id) {
				this.characterItem = {
					...characterStore.characterItem,
					name: characterStore.characterItem.name || '',
					description: characterStore.characterItem.description || '',
				}
			}
			this.fetchSkills()
			this.fetchPlayers()
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
				ocName: '',
				description: '',
			}
		},
		fetchSkills() {
			this.skillsLoading = true

			skillStore.refreshSkillList()
				.then(() => {
					// full skills which are in the skills list on the character
					const activatedSkills = characterStore.characterItem?.id // if modal is an edit modal
						? skillStore.skillList.filter((skill) => { // filter through the list of skills
							return characterStore.characterItem.skills
								.map(String) // ensure all the skill id's in the character are a string (this does not change the resulting data type)
								.includes(skill.id.toString()) // check if the current skill in the filter exists on the character's skills
						})
						: null

					// full skills mapped to be in the structure of select options
					const mappedActivatedSkills = activatedSkills?.length > 0
						? activatedSkills.map((skill) => ({
							id: skill.id,
							label: skill.name,
						}))
						: null

					// skills select options
					const skillsOptions = {
						multiple: true,
						closeOnSelect: false,
						options: skillStore.skillList.map((skill) => ({
							id: skill.id,
							label: skill.name,
						})),
						value: mappedActivatedSkills,
					}

					this.skills = skillsOptions

					this.skillsLoading = false
				})
		},
		fetchPlayers() {
			this.playersLoading = true

			playerStore.refreshPlayerList()
				.then(() => {
					// full players which are in the players list on the character
					const activatedPlayers = characterStore.characterItem?.id // if modal is an edit modal
						? playerStore.playerList.find((player) => { // filter through the list of players
							// check if the current player in the player lest is selected on the character
							return characterStore.characterItem.ocName.toString() === player.id.toString()
						})
						: null

					// full players mapped to be in the structure of select options
					const mappedActivatedPlayer = activatedPlayers
						? {
							id: activatedPlayers.id.toString(),
							label: activatedPlayers.name,
						}
						: null

					// players select options
					const playersOptions = {
						options: playerStore.playerList.map((item) => ({
							id: item.id.toString(),
							label: item.name,
						})),
						value: mappedActivatedPlayer,
					}

					this.players = playersOptions

					this.playersLoading = false
				})
		},
		async editCharacter() {
			this.loading = true
			try {
				await characterStore.saveCharacter({
					...this.characterItem,
					skills: this.skills.value.map((skill) => skill.id),
					ocName: this.players.value?.id || null,
				})
				// Close modal or show success message
				this.success = true
				this.loading = false
				this.error = false
				setTimeout(() => {
					this.closeModal()
					navigationStore.setSelected('characters')
				}, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'An error occurred while saving the character'
			}
		},
	},
}
</script>
