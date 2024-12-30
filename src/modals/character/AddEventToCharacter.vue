<script setup>
import { characterStore, eventStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addEventToCharacter'"
		name="Evenementen bewerken"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Evenementen succesvol bijgewerkt</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<p>Let op: Het bewerken van evenementen kan invloed hebben op de eigenschappen en geschiedenis van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.</p>

			<NcSelect v-bind="events"
				v-model="selectedEvents"
				input-label="Evenementen *"
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
			events: {},
			selectedEvents: [],
			eventsLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	mounted() {
		this.fetchEvents()
	},
	updated() {
		if (navigationStore.modal === 'addEventToCharacter' && !this.hasUpdated) {
			this.fetchEvents()
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
		},
		fetchEvents() {
			this.eventsLoading = true

			eventStore.refreshEventList()
				.then(() => {
					// Create options from all available events
					this.events = {
						multiple: true,
						closeOnSelect: false,
						options: eventStore.eventList.map((event) => ({
							id: event.id,
							label: event.name,
						})),
					}

					// Pre-select existing events
					if (characterStore.characterItem?.events?.length) {
						this.selectedEvents = characterStore.characterItem.events.map(event => ({
							id: event.id || event,
							label: eventStore.eventList.find(e => e.id === (event.id || event))?.name || '',
						}))
					}

					this.eventsLoading = false
				})
		},
		async saveEvents() {
			this.loading = true
			try {
				const characterItemClone = { ...characterStore.characterItem }
				
				// Replace events array with selected events, ensuring uniqueness
				const uniqueEvents = [...new Map(this.selectedEvents.map(event => [event.id, event])).values()]
				characterItemClone.events = uniqueEvents.map(selected => {
					const eventData = eventStore.eventList.find(e => e.id === selected.id)
					return {
						objectType: 'event',
						id: eventData.id,
						name: eventData.name,
						description: eventData.description || '',
						effects: eventData.effects || [],
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
				this.error = error.message || 'Er is een fout opgetreden bij het bewerken van de evenementen'
			}
		},
	},
}
</script>
