<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addItemToCharacter'"
		name="Items bewerken"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Items succesvol bijgewerkt</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcSelect v-bind="items"
				v-model="selectedItems"
				input-label="Items *"
				:loading="itemsLoading"
				:disabled="itemsLoading"
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
				:disabled="loading || itemsLoading"
				type="primary"
				@click="saveItems()">
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
	name: 'AddItemToCharacter',
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
			items: {
				multiple: true,
				closeOnSelect: false,
				options: [],
				value: null,
			},
			selectedItems: [],
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'addItemToCharacter' && !this.hasUpdated) {
			// Create options from all available items
			this.items.options = objectStore.getObjectList('item').map((item) => ({
				id: item.id,
				label: item.name,
			}))

			// Pre-select existing items
			const character = objectStore.getActiveObject('character')
			if (character?.items?.length) {
				this.selectedItems = character.items.map(item => {
					// Handle case where item is just a UUID string
					if (typeof item === 'string') {
						const itemData = objectStore.getObjectList('item').find(s => s.id === item)
						return {
							id: itemData.id,
							label: itemData.name
						}
					}
					// Handle case where item is an object
					return {
						id: item.id,
						label: item.name,
					}
				})
			}

			this.hasUpdated = true
		}
	},
	methods: {
		closeModal() {
			navigationStore.closeModal()
			this.success = false
			this.loading = false
			this.error = false
			this.hasUpdated = false
			this.selectedItems = []
			this.items.options = []
		},
		async saveItems() {
			this.loading = true
			try {
				const character = objectStore.getActiveObject('character')
				if (!character?.id) {
					throw new Error('No character selected')
				}

				// Create updated character data
				const characterData = { ...character }
				
				// Replace items array with selected items
				characterData.items = this.selectedItems.map(selected => {
					const itemData = objectStore.getObjectList('item').find(s => s.id === selected.id)
					return {
						objectType: 'item',
						id: itemData.id,
						name: itemData.name,
						description: itemData.description || '',
						effects: itemData.effects || [],
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
				this.error = error.message || 'Er is een fout opgetreden bij het bewerken van de items'
			}
		},
	},
}
</script>
