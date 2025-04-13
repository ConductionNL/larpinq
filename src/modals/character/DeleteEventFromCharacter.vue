<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'deleteEventFromCharacter'"
		name="Event van karakter verwijderen"
		size="normal"
		:can-close="false">
		<p>
			Wil je <b>{{ objectStore.getActiveObject('event').name }}</b> verwijderen van <b>{{ objectStore.getActiveObject('character').name }}</b>?
		</p>
		<NcNoteCard type="info" heading="Let op">
			Het verwijderen van een event op een karakter zal leiden tot een herberekening van de statistieken van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.
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
				@click="deleteEventFromCharacter">
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
	name: 'DeleteEventFromCharacter',
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
			objectStore.clearActiveObject('event')
			objectStore.clearActiveObject('character')
			navigationStore.closeModal()
		},
		async deleteEventFromCharacter() {
			this.loading = true
			try {
				const characterClone = { ...objectStore.getActiveObject('character') }
				const eventId = objectStore.getActiveObject('event').id

				if (!characterClone.id) {
					throw new Error('Geen karakter geselecteerd')
				}

				if (!eventId) {
					throw new Error('Geen event geselecteerd')
				}

				// Find the index of the event to delete
				const index = characterClone.events.findIndex(item => item === eventId)

				// Remove the event if it exists
				if (index !== -1) {
					characterClone.events.splice(index, 1)
				} else {
					throw new Error('Event kon niet worden gevonden op het karakter, mogelijk is deze al verwijderd.')
				}

				await objectStore.saveObject('character', characterClone)
				this.closeModal()
			} catch (error) {
				console.error('Error removing event from character:', error)
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
