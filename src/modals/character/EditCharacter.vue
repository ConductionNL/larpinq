<script setup>
import { characterStore, navigationStore, playerStore } from '../../store/store.js'
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
			players: {},
			playersLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	mounted() {
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
		fetchPlayers() {
			this.playersLoading = true

			playerStore.refreshPlayerList()
				.then(() => {
					const activatedPlayer = characterStore.characterItem?.id 
						? playerStore.playerList.find((player) => 
							characterStore.characterItem.ocName?.id === player.id ||
							characterStore.characterItem.ocName?.toString() === player.id.toString()
						)
						: null

					const mappedActivatedPlayer = activatedPlayer
						? {
							id: activatedPlayer.id.toString(),
							label: activatedPlayer.name,
						}
						: null

					this.players = {
						options: playerStore.playerList.map((player) => ({
							id: player.id.toString(),
							label: player.name,
						})),
						value: mappedActivatedPlayer,
					}

					this.playersLoading = false
				})
				.catch((error) => {
					console.error('Error fetching players:', error)
					this.playersLoading = false
				})
		},
		async editCharacter() {
			this.loading = true
			try {
				await characterStore.saveCharacter({
					...this.characterItem,
					ocName: this.players?.value?.id || null,
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
