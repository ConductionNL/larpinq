<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'deleteConditionFromCharacter'"
		name="Conditie van karakter verwijderen"
		size="normal"
		:can-close="false">
		<p>
			Wil je <b>{{ objectStore.getActiveObject('condition').name }}</b> verwijderen van <b>{{ objectStore.getActiveObject('character').name }}</b>?
		</p>
		<NcNoteCard type="info" heading="Let op">
			Het verwijderen van een conditie op een karakter zal leiden tot een herberekening van de statistieken van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.
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
				@click="deleteConditionFromCharacter">
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
	name: 'DeleteConditionFromCharacter',
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
			objectStore.clearActiveObject('condition')
			objectStore.clearActiveObject('character')
			navigationStore.closeModal()
		},
		async deleteConditionFromCharacter() {
			this.loading = true
			try {
				const characterClone = { ...objectStore.getActiveObject('character') }
				const conditionId = objectStore.getActiveObject('condition').id

				if (!characterClone.id) {
					throw new Error('Geen karakter geselecteerd')
				}

				if (!conditionId) {
					throw new Error('Geen conditie geselecteerd')
				}

				// Find the index of the condition to delete
				const index = characterClone.conditions.findIndex(item => item === conditionId)

				// Remove the condition if it exists
				if (index !== -1) {
					characterClone.conditions.splice(index, 1)
				} else {
					throw new Error('Conditie kon niet worden gevonden op het karakter, mogelijk is deze al verwijderd.')
				}

				await objectStore.saveObject('character', characterClone)
				this.closeModal()
			} catch (error) {
				console.error('Error removing condition from character:', error)
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
