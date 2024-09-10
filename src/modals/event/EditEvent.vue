<script setup>
import { eventStore, effectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editEvent'"
		name="Event"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Event succesvol aangepast</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcTextField :disabled="loading"
				label="Name *"
				required
				:value.sync="eventItem.name" />
			<NcTextArea :disabled="loading"
				label="Description"
				type="textarea"
				:value.sync="eventItem.description" />
			<NcTextField :disabled="loading"
				label="Start Date"
				type="date"
				:value.sync="eventItem.startDate" />
			<NcTextField :disabled="loading"
				label="End Date"
				type="date"
				:value.sync="eventItem.endDate" />
			<NcTextField :disabled="loading"
				label="Location"
				:value.sync="eventItem.location" />
			<NcSelect v-bind="effects"
				v-model="effects.value"
				input-label="Effects"
				:loading="effectsLoading"
				:disabled="loading" />
		</div>

		<template #actions>
			<NcButton @click="navigationStore.setModal(false)">
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
				@click="editEvent()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && eventStore.eventItem.id" :size="20" />
					<Plus v-if="!loading && !eventStore.eventItem.id" :size="20" />
				</template>
				{{ eventStore.eventItem.id ? 'Opslaan' : 'Aanmaken' }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton, NcDialog, NcTextField, NcTextArea, NcLoadingIcon, NcNoteCard, NcSelect,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Help from 'vue-material-design-icons/Help.vue'

export default {
	name: 'EditEvent',
	components: {
		NcDialog,
		NcTextField,
		NcTextArea,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcSelect,
		ContentSaveOutline,
		Cancel,
		Plus,
		Help,
	},
	data() {
		return {
			success: false,
			loading: false,
			error: false,
			effects: {},
			effectsLoading: false,
			eventItem: {
				name: '',
				description: '',
				startDate: '',
				endDate: '',
				location: '',
			},
		}
	},
	updated() {
		if (navigationStore.modal === 'editEvent' && !this.hasUpdated) {
			if (eventStore.eventItem.id) {
				this.eventItem = {
					...eventStore.eventItem,
					name: eventStore.eventItem.name || '',
					description: eventStore.eventItem.description || '',
					startDate: eventStore.eventItem.startDate || '',
					endDate: eventStore.eventItem.endDate || '',
					location: eventStore.eventItem.location || '',
				}
			}
			this.fetchEffects()
			this.hasUpdated = true
		}
	},
	methods: {
		async editEvent() {
			this.loading = true
			try {
				await eventStore.saveEvent({
					...this.eventItem,
					effects: this.effects.value.map((effect) => effect.id),
				})
				this.success = true
				this.loading = false
				setTimeout(() => {
					this.success = false
					this.loading = false
					this.error = false
					navigationStore.setModal(false)
				}, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'An error occurred while saving the event'
			}
		},
		fetchEffects() {
			this.effectsLoading = true

			effectStore.refreshEffectList()
				.then(() => {
					const activeEffects = eventStore.eventItem.id
						? effectStore.skillList.filter((effect) => {
							return eventStore.eventItem.effects
								.map(String)
								.includes(effect.id.toString())
						})
						: null

					this.effects = {
						multiple: true,
						closeOnSelect: false,
						options: effectStore.effectList.map((effect) => ({
							id: effect.id,
							label: effect.name,
						})),
						value: activeEffects
							? activeEffects.map((effect) => ({
								id: effect.id,
							    label: effect.name,
							}))
							: null,
					}

					this.effectsLoading = false
				})
		},
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>
