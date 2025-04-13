<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editEvent'"
		:name="`${objectStore.getActiveObject('event')?.id ? 'Bewerk' : 'Nieuwe'} gebeurtenis`"
		size="normal"
		:can-close="false">
		<div class="content">
			<NcTextField
				:value="event.name"
				label="Naam"
				@update:value="event.name = $event" />

			<NcTextField
				:value="event.description"
				label="Beschrijving"
				type="textarea"
				@update:value="event.description = $event" />

			<NcTextField
				:value="event.location"
				label="Locatie"
				@update:value="event.location = $event" />

			<div class="dates">
				<NcDateTimePicker
					:value="event.startDate"
					label="Start datum"
					type="datetime"
					@update:value="event.startDate = $event" />

				<NcDateTimePicker
					:value="event.endDate"
					label="Eind datum"
					type="datetime"
					@update:value="event.endDate = $event" />
			</div>

			<div class="effects">
				<h3>Effecten</h3>
				<ObjectList :objects="objectStore.getCollection('effect').results" />
			</div>
		</div>

		<template #actions>
			<NcButton @click="closeModal">
				<template #icon>
					<Cancel :size="20" />
				</template>
				Annuleren
			</NcButton>
			<NcButton type="primary"
				:disabled="loading"
				@click="saveEvent">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && objectStore.getActiveObject('event')?.id" :size="20" />
					<Plus v-if="!loading && !objectStore.getActiveObject('event')?.id" :size="20" />
				</template>
				{{ objectStore.getActiveObject('event')?.id ? 'Opslaan' : 'Aanmaken' }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
	NcTextField,
	NcLoadingIcon,
	NcDateTimePicker,
} from '@nextcloud/vue'

import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'

import ObjectList from '../../components/ObjectList.vue'

export default {
	name: 'EditEvent',
	components: {
		NcDialog,
		NcButton,
		NcTextField,
		NcLoadingIcon,
		NcDateTimePicker,
		ContentSaveOutline,
		Plus,
		ObjectList,
		Cancel,
	},
	data() {
		return {
			loading: false,
			hasUpdated: false,
			event: {
				name: '',
				description: '',
				location: '',
				startDate: new Date(),
				endDate: new Date(),
			},
			effects: [],
		}
	},
	watch: {
		'navigationStore.modal'(newVal) {
			if (newVal === 'editEvent' && !this.hasUpdated) {
				this.updateForm()
			}
		},
	},
	methods: {
		updateForm() {
			if (objectStore.getActiveObject('event')?.id && navigationStore.modal === 'editEvent' && !this.hasUpdated) {
				const event = objectStore.getActiveObject('event')
				this.event = {
					...event,
					name: event.name || '',
					description: event.description || '',
					location: event.location || '',
					startDate: !isNaN(new Date(event.startDate))
						? new Date(event.startDate)
						: new Date(),
					endDate: !isNaN(new Date(event.endDate))
						? new Date(event.endDate)
						: new Date(),
				}
				this.effects = event.effects || []
				this.hasUpdated = true
			}
		},
		closeModal() {
			this.event = {
				name: '',
				description: '',
				location: '',
				startDate: new Date(),
				endDate: new Date(),
			}
			this.effects = []
			this.hasUpdated = false
			objectStore.clearActiveObject('event')
			navigationStore.closeModal()
		},
		async saveEvent() {
			this.loading = true
			try {
				await objectStore.saveObject('event', {
					...this.event,
					effects: this.effects,
				})
				this.closeModal()
			} catch (error) {
				console.error('Error saving event:', error)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.content {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.dates {
	display: flex;
	gap: 1rem;
}

.effects {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}
</style>

