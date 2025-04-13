<script setup>
import { navigationStore, objectStore } from '../../store/store.js'
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
				label="Naam *"
				required
				:value.sync="characterItem.name" />
			<NcTextArea :disabled="loading"
				label="Beschrijving"
				:value.sync="characterItem.description" />
			<NcTextArea :disabled="loading"
				label="Achtergrond"
				:value.sync="characterItem.background" />
			<NcTextArea :disabled="loading"
				label="Opmerking"
				:value.sync="characterItem.notice" />
			<NcSelect v-bind="players"
				v-model="players.value"
				input-label="OC Naam *"
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
				background: '',
				notice: '',
			},
			players: {
				options: [],
				value: null,
			},
			playersLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'editCharacter' && !this.hasUpdated) {
			const activeCharacter = objectStore.getActiveObject('character')
			if (activeCharacter?.id) {
				this.characterItem = {
					...activeCharacter,
					name: activeCharacter.name || '',
					description: activeCharacter.description || '',
					background: activeCharacter.background || '',
					notice: activeCharacter.notice || '',
				}

				// Set player selection if character has one
				const activatedPlayer = activeCharacter.ocName?.id
					? objectStore.getCollection('player').results.find(player =>
						activeCharacter.ocName.id === player.id
						|| activeCharacter.ocName.toString() === player.id.toString(),
					)
					: null

				this.players = {
					options: objectStore.getCollection('player').results.map(player => ({
						id: player.id.toString(),
						label: player.name,
					})),
					value: activatedPlayer
						? {
							id: activatedPlayer.id.toString(),
							label: activatedPlayer.name,
						}
						: null,
				}
			} else {
				// For new character, just set player options
				this.players = {
					options: objectStore.getCollection('player').results.map(player => ({
						id: player.id.toString(),
						label: player.name,
					})),
					value: null,
				}
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
			this.characterItem = {
				name: '',
				ocName: '',
				description: '',
				background: '',
				notice: '',
			}
			this.players = {
				options: [],
				value: null,
			}
		},
		async editCharacter() {
			this.loading = true
			try {
				const characterData = {
					...this.characterItem,
					ocName: this.players?.value?.id || null,
				}

				if (characterData.id) {
					await objectStore.updateObject('character', characterData.id, characterData)
				} else {
					await objectStore.createObject('character', characterData)
				}

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
				this.error = error.message || 'Er is een fout opgetreden bij het opslaan van het karakter'
			}
		},
	},
}
</script>
