<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addConditionToCharacter'"
		name="Conditions bewerken"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Conditions succesvol bijgewerkt</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<p>Let op: Het bewerken van condities kan invloed hebben op de eigenschappen van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.</p>

			<NcSelect v-bind="conditions"
				v-model="selectedConditions"
				input-label="Conditions *"
				:loading="conditionsLoading"
				:disabled="conditionsLoading"
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
				:disabled="loading || conditionsLoading"
				type="primary"
				@click="saveConditions()">
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
	name: 'AddConditionToCharacter',
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
			conditions: {
				multiple: true,
				closeOnSelect: false,
				options: [],
				value: null,
			},
			selectedConditions: [],
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'addConditionToCharacter' && !this.hasUpdated) {
			// Create options from all available conditions
			this.conditions.options = objectStore.getObjectList('condition').map((condition) => ({
				id: condition.id,
				label: condition.name,
			}))

			// Pre-select existing conditions
			const character = objectStore.getActiveObject('character')
			if (character?.conditions?.length) {
				this.selectedConditions = character.conditions.map(condition => {
					// Handle case where condition is just a UUID string
					if (typeof condition === 'string') {
						const conditionData = objectStore.getObjectList('condition').find(s => s.id === condition)
						return {
							id: conditionData.id,
							label: conditionData.name
						}
					}
					// Handle case where condition is an object
					return {
						id: condition.id,
						label: condition.name,
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
			this.selectedConditions = []
			this.conditions.options = []
		},
		async saveConditions() {
			this.loading = true
			try {
				const character = objectStore.getActiveObject('character')
				if (!character?.id) {
					throw new Error('No character selected')
				}

				// Create updated character data
				const characterData = { ...character }
				
				// Replace conditions array with selected conditions
				characterData.conditions = this.selectedConditions.map(selected => {
					const conditionData = objectStore.getObjectList('condition').find(s => s.id === selected.id)
					return {
						objectType: 'condition',
						id: conditionData.id,
						name: conditionData.name,
						description: conditionData.description || '',
						effects: conditionData.effects || [],
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
				this.error = error.message || 'Er is een fout opgetreden bij het bewerken van de conditions'
			}
		},
	},
}
</script>
