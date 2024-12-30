<script setup>
import { characterStore, itemStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addItemToCharacter'"
		name="Voorwerpen bewerken"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Voorwerpen succesvol bijgewerkt</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<p>Let op: Het bewerken van voorwerpen kan invloed hebben op de eigenschappen van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.</p>

			<NcSelect v-bind="items"
				v-model="selectedItems"
				input-label="Voorwerpen *"
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
			items: {},
			selectedItems: [],
			itemsLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	mounted() {
		this.fetchItems()
	},
	updated() {
		if (navigationStore.modal === 'addItemToCharacter' && !this.hasUpdated) {
			this.fetchItems()
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
			this.selectedItems = []
		},
		fetchItems() {
			this.itemsLoading = true

			itemStore.refreshItemList()
				.then(() => {
					// Create options from all available items
					this.items = {
						multiple: true,
						closeOnSelect: false,
						options: itemStore.itemList.map((item) => ({
							id: item.id,
							label: item.name,
						})),
					}

					// Pre-select existing items
					if (characterStore.characterItem?.items?.length) {
						this.selectedItems = characterStore.characterItem.items.map(item => ({
							id: item.id || item,
							label: itemStore.itemList.find(i => i.id === (item.id || item))?.name || '',
						}))
					}

					this.itemsLoading = false
				})
		},
		async saveItems() {
			this.loading = true
			try {
				const characterItemClone = { ...characterStore.characterItem }
				
				// Replace items array with selected items, ensuring uniqueness
				const uniqueItems = [...new Map(this.selectedItems.map(item => [item.id, item])).values()]
				characterItemClone.items = uniqueItems.map(selected => {
					const itemData = itemStore.itemList.find(i => i.id === selected.id)
					return {
						objectType: 'item',
						id: itemData.id,
						name: itemData.name,
						description: itemData.description || '',
						effects: itemData.effects || [],
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
				this.error = error.message || 'Er is een fout opgetreden bij het bewerken van de voorwerpen'
			}
		},
	},
}
</script>
