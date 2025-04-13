<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addEventToCharacter'"
		name="Events bewerken"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Events succesvol bijgewerkt</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcSelect v-bind="events"
				v-model="selectedEvents"
				input-label="Events *"
				:loading="eventsLoading"
				:disabled="eventsLoading"
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
				:disabled="loading || eventsLoading"
				type="primary"
				@click="saveEvents()">
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
	name: 'AddEventToCharacter',
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
			events: {
				multiple: true,
				closeOnSelect: false,
				options: [],
				value: null,
			},
			selectedEvents: [],
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'addEventToCharacter' && !this.hasUpdated) {
			// Create options from all available events
			this.events.options = objectStore.getObjectList('event').map((event) => ({
				id: event.id,
				label: event.name,
			}))

			// Pre-select existing events
			const character = objectStore.getActiveObject('character')
			if (character?.events?.length) {
				this.selectedEvents = character.events.map(event => {
					// Handle case where event is just a UUID string
					if (typeof event === 'string') {
						const eventData = objectStore.getObjectList('event').find(s => s.id === event)
						return {
							id: eventData.id,
							label: eventData.name,
						}
					}
					// Handle case where event is an object
					return {
						id: event.id,
						label: event.name,
					}
				})
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
			this.selectedEvents = []
			this.events.options = []
		},
		async saveEvents() {
			this.loading = true
			try {
				const character = objectStore.getActiveObject('character')
				if (!character?.id) {
					throw new Error('No character selected')
				}

				// Create updated character data
				const characterData = { ...character }

				// Replace events array with selected events
				characterData.events = this.selectedEvents.map(selected => {
					const eventData = objectStore.getObjectList('event').find(s => s.id === selected.id)
					return {
						objectType: 'event',
						id: eventData.id,
						name: eventData.name,
						description: eventData.description || '',
						startDate: eventData.startDate || '',
						endDate: eventData.endDate || '',
						location: eventData.location || '',
						effects: eventData.effects || [],
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
				this.error = error.message || 'Er is een fout opgetreden bij het bewerken van de events'
			}
		},
	},
}
</script>
