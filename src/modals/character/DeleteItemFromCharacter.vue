<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'deleteItemFromCharacter'"
		name="Item van karakter verwijderen"
		size="normal"
		:can-close="false">
		<p>
			Wil je <b>{{ objectStore.getActiveObject('item').name }}</b> verwijderen van <b>{{ objectStore.getActiveObject('character').name }}</b>?
		</p>
		<NcNoteCard type="info" heading="Let op">
			Het verwijderen van een item op een karakter zal leiden tot een herberekening van de statistieken van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.
		</NcNoteCard>

		<template #actions>
			<NcButton @click="closeModal">
				<template #icon>
					<Cancel :size="20" />
				</template>
				Annuleren
			</NcButton>
			<NcButton type="error"
				:disabled="loading"
				@click="deleteItemFromCharacter">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<TrashCanOutline v-if="!loading" :size="20" />
				</template>
				Verwijderen
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'DeleteItemFromCharacter',
	components: {
		NcDialog,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		Cancel,
		TrashCanOutline,
	},
	data() {
		return {
			loading: false,
		}
	},
	methods: {
		closeModal() {
			objectStore.clearActiveObject('item')
			objectStore.clearActiveObject('character')
			navigationStore.closeModal()
		},
		async deleteItemFromCharacter() {
			this.loading = true
			try {
				const characterClone = { ...objectStore.getActiveObject('character') }
				const itemId = objectStore.getActiveObject('item').id

				if (!characterClone.id) {
					throw new Error('Geen karakter geselecteerd')
				}

				if (!itemId) {
					throw new Error('Geen item geselecteerd')
				}

				// Find the index of the item to delete
				const index = characterClone.items.findIndex(item => item === itemId)

				// Remove the item if it exists
				if (index !== -1) {
					characterClone.items.splice(index, 1)
				} else {
					throw new Error('Item kon niet worden gevonden op het karakter, mogelijk is deze al verwijderd.')
				}

				await objectStore.saveObject('character', characterClone)
				this.closeModal()
			} catch (error) {
				console.error('Error removing item from character:', error)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="css">
.notecard h2 {
	margin: 0;
}
</style>
