<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editEffect'"
		name="Effect"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Effect succesvol aangepast</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcTextField :disabled="loading"
				label="Naam *"
				required
				:value.sync="effectItem.name" />
			<NcTextArea :disabled="loading"
				label="Beschrijving"
				type="textarea"
				:value.sync="effectItem.description" />
			<NcTextField :disabled="loading"
				label="Aanpassing"
				type="number"
				:value.sync="effectItem.modifier" />
			<NcSelect
				v-bind="modificationOptions"
				v-model="modificationOptions.value"
				:disabled="loading" />
			<NcSelect
				v-bind="cumulativeOptions"
				v-model="cumulativeOptions.value"
				:disabled="loading" />
			<NcSelect
				v-bind="abilities"
				v-model="abilities.value"
				input-label="Vaardigheden"
				:loading="abilitiesLoading"
				:disabled="loading" />
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
				:disabled="loading"
				type="primary"
				@click="editEffect()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading" :size="20" />
				</template>
				Opslaan
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton, NcDialog, NcTextField, NcTextArea, NcSelect, NcLoadingIcon, NcNoteCard,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Help from 'vue-material-design-icons/Help.vue'

export default {
	name: 'EditEffect',
	components: {
		NcDialog,
		NcTextField,
		NcTextArea,
		NcButton,
		NcSelect,
		NcLoadingIcon,
		NcNoteCard,
		ContentSaveOutline,
		Cancel,
		Help,
	},
	data() {
		return {
			effectItem: {
				name: '',
				description: '',
				modifier: '',
			},
			success: false,
			loading: false,
			error: false,
			modificationOptions: {
				inputLabel: 'Modification',
				multiple: false,
				options: [{ id: 'positive', label: 'Positive' }, { id: 'negative', label: 'Negative' }],
				value: [{ id: 'positive', label: 'Positive' }],
			},
			cumulativeOptions: {
				inputLabel: 'Cumulative',
				multiple: false,
				options: [{ id: 'cumulative', label: 'Cumulative' }, { id: 'non-cumulative', label: 'Non-cumulative' }],
				value: [{ id: 'cumulative', label: 'Cumulative' }],
			},
			abilities: {
				multiple: true,
				closeOnSelect: false,
				options: [],
				value: null,
			},
			hasUpdated: false,
		}
	},
	mounted() {
		if (navigationStore.modal === 'editEffect' && !this.hasUpdated) {
			// Get selected abilities
			const selectedAbilities = objectStore.getObjectList('ability').filter((ability) => {
				return this.effect.abilities.includes(ability.id)
			})

			// Set selected abilities
			this.abilities.value = selectedAbilities.map(ability => ({
				id: ability.id,
				label: ability.name,
			}))

			// Set ability options from preloaded abilities
			this.abilities.options = objectStore.getObjectList('ability').map((ability) => ({
				id: ability.id,
				label: ability.name,
			}))

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
			this.effectItem = {
				name: '',
				description: '',
				modifier: '',
			}
			this.abilities.value = null
			this.abilities.options = []
		},
		async editEffect() {
			this.loading = true
			try {
				await objectStore.saveObject('effect', {
					...this.effectItem,
					modification: this.modificationOptions.value.id,
					cumulative: this.cumulativeOptions.value.value.id,
					abilities: (this.abilities?.value || []).map((ability) => ability.id),
				})
				this.success = true
				this.loading = false
				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'Er is een fout opgetreden bij het opslaan van het effect'
			}
		},
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>
