<script setup>
import { abilityStore, effectStore, navigationStore } from '../../store/store.js'
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
				label="Name *"
				required
				:value.sync="effectItem.name" />
			<NcTextArea :disabled="loading"
				label="Description"
				type="textarea"
				:value.sync="effectItem.description" />
			<NcTextField :disabled="loading"
				label="Stat ID"
				:value.sync="effectItem.statId" />
			<NcTextField :disabled="loading"
				label="Modifier"
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
				input-label="Abilities"
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
				statId: '',
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
			abilities: {},
			abilitiesLoading: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'editEffect' && !this.hasUpdated) {
			if (effectStore.effectItem.id) {
				this.effectItem = {
					...effectStore.effectItem,
					name: effectStore.effectItem.name || '',
					description: effectStore.effectItem.description || '',
					statId: effectStore.effectItem.statId || '',
					modifier: effectStore.effectItem.modifier || '',
				}
			}
			this.fetchAbilities()
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
				statId: '',
				modifier: '',
			}
		},
		fetchAbilities() {
			this.abilitiesLoading = true

			abilityStore.refreshAbilityList()
				.then(() => {
					const selectedAbilities = effectStore.effectItem.id // if modal is an edit modal
						? abilityStore.abilityList.filter((ability) => { // filter through the list of abilities
							return (effectStore.effectItem.abilities || []) // ensure abilities exists or default to empty array
								.map(String) // ensure all the ability id's in the effect are a string (this does not change the resulting data type)
								.includes(ability.id.toString()) // check if the current ability in the filter exists on the effects's abilities
						})
						: null

					this.abilities = {
						multiple: true,
						closeOnSelect: false,
						options: abilityStore.abilityList.map((ability) => ({
							id: ability.id,
							label: ability.name,
						})),
						value: selectedAbilities
							? selectedAbilities.map((ability) => ({
								id: ability.id,
							    label: ability.name,
							}))
							: null,
					}

					this.abilitiesLoading = false
				})
		},
		async editEffect() {
			this.loading = true
			try {
				await effectStore.saveEffect({
					...this.effectItem,
					modification: this.modificationOptions.value.id,
					cumulative: this.cumulativeOptions.value.id,
					abilities: (this.abilities?.value || []).map((ability) => ability.id),
				})
				this.success = true
				this.loading = false
				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'An error occurred while saving the effect'
			}
		},
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>
