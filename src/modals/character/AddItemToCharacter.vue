<script setup>
import { characterStore, itemStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addItemToCharacter'"
		name="Item toevoegen aan karakter"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Item succesvol toegevoegd aan karakter</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<p>Let op: Het toevoegen van een item aan een karakter kan invloed hebben op de eigenschappen van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.</p>

			<NcSelect v-bind="items"
				v-model="items.value"
				input-label="Items *"
				:loading="itemsLoading"
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
				@click="addItemToCharacter()">
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
	name: 'AddItemToCharacter',
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
			items: {},
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
		},
		fetchItems() {
			this.itemsLoading = true

			itemStore.refreshItemList()
				.then(() => {
					// Get all the items NOT on the character
					const availableItems = itemStore.itemList.filter((item) => {
						return characterStore.characterItem.items
							.map(String)
							.includes(item.id.toString()) !== true
					})

					this.items = {
						multiple: true,
						closeOnSelect: false,
						options: availableItems.map((item) => ({
							id: item.id,
							label: item.name,
						})),
					}

					this.itemsLoading = false
				})
		},
		async addItemToCharacter() {
			this.loading = true
			try {
				const characterItemClone = { ...characterStore.characterItem }

				if (!characterItemClone.items) {
					characterItemClone.items = []
				}

				for (const selectedItem of this.items.value) {
					characterItemClone.items.push(selectedItem.id)
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
				this.error = error.message || 'Er is een fout opgetreden bij het toevoegen van het item aan het karakter'
			}
		},
	},
}
</script>
