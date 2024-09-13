<script setup>
import { characterStore, conditionStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addConditionToCharacter'"
		name="Conditie toevoegen aan karakter"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Conditie succesvol toegevoegd aan karakter</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<p>Let op: Het toevoegen van een conditie aan een karakter kan invloed hebben op de eigenschappen en vaardigheden van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.</p>

			<NcSelect v-bind="conditions"
				v-model="conditions.value"
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
				:disabled="loading || conditionsLoading || !conditions.value?.length"
				type="primary"
				@click="addConditionToCharacter()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Plus v-if="!loading" :size="20" />
				</template>
				Toevoegen
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
import Plus from 'vue-material-design-icons/Plus.vue'
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
		Plus,
		Help,
	},
	data() {
		return {
			conditions: {},
			conditionsLoading: false,
			loading: false,
			success: false,
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
			this.conditionsLoading = false
			this.error = false
			this.hasUpdated = false
		},
		fetchConditions() {
			this.conditionsLoading = true

			conditionStore.refreshConditionList()
				.then(() => {
					// Get all the items NOT on the character
					const availableConditions = conditionStore.conditionList.filter((item) => {
						return (characterStore.characterItem?.conditions || [])
							.map(String)
							.includes(item.id.toString()) !== true
					})

					this.conditions = {
						multiple: true,
						closeOnSelect: false,
						options: availableConditions.map((item) => ({
							id: item.id,
							label: item.name,
						})),
					}

					this.conditionsLoading = false
				})
		},
		async addConditionToCharacter() {
			this.loading = true
			try {
				const characterItemClone = { ...characterStore.characterItem }

				if (!characterStore.characterItem.conditions) {
					characterStore.characterItem.conditions = []
				}

				for (const selectedItem of this.conditions.value) {
					if (!characterItemClone.conditions.includes(selectedItem.id)) {
						characterItemClone.conditions.push(selectedItem.id)
					}
				}

				await characterStore.saveCharacter({
					...characterItemClone,
				})

				this.success = true
				this.loading = false
				this.error = false
				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'Er is een fout opgetreden bij het toevoegen van de conditie aan het karakter'
			}
		},
	},
}
</script>
