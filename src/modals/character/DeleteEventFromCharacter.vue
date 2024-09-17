<script setup>
import { characterStore, eventStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.dialog === 'deleteEventFromCharacter'"
		name="Event van karakter verwijderen"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Event succesvol verwijderd van karakter</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<p>
				Wil je <b>{{ eventStore.eventItem?.name }}</b> verwijderen van <b>{{ characterStore.characterItem?.name }}</b>?
			</p>
			<NcNoteCard type="info" heading="Let op">
				Het verwijderen van een event op een karakter zal leiden tot een herberekening van de statistieken van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.
			</NcNoteCard>
		</div>

		<template #actions>
			<NcButton
				@click="navigationStore.setDialog(false)">
				<template #icon>
					<Cancel :size="20" />
				</template>
				{{ success ? 'Sluiten' : 'Annuleer' }}
			</NcButton>
			<NcButton
				v-if="!success"
				:disabled="loading"
				type="error"
				@click="deleteEventFromCharacter()">
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
		// Icons
		TrashCanOutline,
		Cancel,
	},
	data() {
		return {
			success: false,
			loading: false,
			error: false,
		}
	},
	methods: {
		async deleteEventFromCharacter() {
			this.loading = true
			try {
				const characterItemClone = { ...characterStore.characterItem }

				// Find the index of the item to delete
				const index = characterItemClone.events.findIndex(item => item === eventStore.eventItem.id)

				// Remove the item if it exists
				if (index !== -1) {
					characterItemClone.events.splice(index, 1)
				} else {
					throw Error('Event could not be found on character, this may be because it is already deleted.')
				}

				if (!characterItemClone.id) {
					throw Error('Error: character ID was not found')
				}

				await characterStore.saveCharacter({
					...characterItemClone,
				})

				// Close modal or show success message
				this.success = true
				this.loading = false
				this.error = false
				setTimeout(() => {
					this.success = false
					navigationStore.setDialog(false)
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

<style lang="css">
.notecard h2 {
    margin: 0;
}
</style>
