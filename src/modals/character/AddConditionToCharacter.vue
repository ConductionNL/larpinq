<script setup>
import { characterStore, conditionStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addConditionToCharacter'"
		name="Condities bewerken"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Condities succesvol bijgewerkt</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<p>Let op: Het bewerken van condities kan invloed hebben op de eigenschappen van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.</p>

			<NcSelect v-bind="conditions"
				v-model="selectedConditions"
				input-label="Condities *"
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
			conditions: {},
			selectedConditions: [],
			conditionsLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	mounted() {
		this.fetchConditions()
	},
	updated() {
		if (navigationStore.modal === 'addConditionToCharacter' && !this.hasUpdated) {
			this.fetchConditions()
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
		},
		fetchConditions() {
			this.conditionsLoading = true

			conditionStore.refreshConditionList()
				.then(() => {
					// Create options from all available conditions
					this.conditions = {
						multiple: true,
						closeOnSelect: false,
						options: conditionStore.conditionList.map((condition) => ({
							id: condition.id,
							label: condition.name,
						})),
					}

					// Pre-select existing conditions
					if (characterStore.characterItem?.conditions?.length) {
						this.selectedConditions = characterStore.characterItem.conditions.map(condition => ({
							id: condition.id || condition,
							label: conditionStore.conditionList.find(c => c.id === (condition.id || condition))?.name || '',
						}))
					}

					this.conditionsLoading = false
				})
		},
		async saveConditions() {
			this.loading = true
			try {
				const characterItemClone = { ...characterStore.characterItem }
				
				// Replace conditions array with selected conditions, ensuring uniqueness
				const uniqueConditions = [...new Map(this.selectedConditions.map(condition => [condition.id, condition])).values()]
				characterItemClone.conditions = uniqueConditions.map(selected => {
					const conditionData = conditionStore.conditionList.find(c => c.id === selected.id)
					return {
						objectType: 'condition',
						id: conditionData.id,
						name: conditionData.name,
						description: conditionData.description || '',
						effects: conditionData.effects || [],
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
				this.error = error.message || 'Er is een fout opgetreden bij het bewerken van de condities'
			}
		},
	},
}
</script>
