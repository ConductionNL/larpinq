<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editItem'"
		:name="objectStore.getActiveObject('item')?.id ? 'Voorwerp bewerken' : 'Voorwerp toevoegen'"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>
				Voorwerp succesvol
				{{ objectStore.getActiveObject('item')?.id
					? 'aangepast'
					: 'toegevoegd'
				}}
			</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcTextField :disabled="loading"
				label="Name *"
				required
				:value="formData.name"
				@update:value="formData.name = $event" />
			<NcTextArea :disabled="loading"
				label="Description"
				type="textarea"
				:value="formData.description"
				@update:value="formData.description = $event" />
			<NcSelect
				:value="selectedEffects"
				:options="effectOptions"
				input-label="Effects"
				:loading="objectStore.isLoading('effect')"
				:disabled="objectStore.isLoading('effect') || loading"
				multiple
				:close-on-select="false"
				@update:value="selectedEffects = $event" />
			<NcCheckboxRadioSwitch :disabled="loading"
				label="Unique"
				type="switch"
				:checked="formData.unique"
				@update:checked="formData.unique = $event">
				Unique
			</NcCheckboxRadioSwitch>
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
			<NcButton
				v-if="!success"
				:disabled="loading"
				type="primary"
				@click="saveItem()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && objectStore.getActiveObject('item')?.id" :size="20" />
					<Plus v-if="!loading && !objectStore.getActiveObject('item')?.id" :size="20" />
				</template>
				{{ objectStore.getActiveObject('item')?.id ? 'Opslaan' : 'Aanmaken' }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
	NcTextField,
	NcTextArea,
	NcCheckboxRadioSwitch,
	NcLoadingIcon,
	NcNoteCard,
	NcSelect,
} from '@nextcloud/vue'

import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Help from 'vue-material-design-icons/Help.vue'

/**
 * EditItem Component
 * @module Modals
 * @package
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'EditItem',
	components: {
		NcDialog,
		NcTextField,
		NcTextArea,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcNoteCard,
		NcSelect,
		// Icons
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
			formData: {
				name: '',
				description: '',
				unique: false,
			},
			selectedEffects: [],
		}
	},
	computed: {
		effectOptions() {
			return (objectStore.getCollection('effect').results || []).map(effect => ({
				id: effect.id,
				label: effect.name,
			}))
		},
	},
	watch: {
		'navigationStore.modal'(newVal) {
			if (newVal === 'editItem') {
				this.initializeForm()
			}
		},
	},
	mounted() {
		// Load effects collection if not already loaded
		if (!objectStore.getCollection('effect').results?.length) {
			objectStore.fetchCollection('effect')
		}
		// Initialize form when mounted
		this.initializeForm()
	},
	methods: {
		initializeForm() {
			const activeItem = objectStore.getActiveObject('item')
			// Reset form data first
			this.formData = {
				name: '',
				description: '',
				unique: false,
			}
			this.selectedEffects = []

			// If we have an active item, populate the form
			if (activeItem) {
				this.formData = {
					name: activeItem.name || '',
					description: activeItem.description || '',
					unique: activeItem.unique || false,
				}

				// Set selected effects if they exist
				if (activeItem.effects?.length) {
					this.selectedEffects = activeItem.effects
						.map(effectId => {
							const effect = objectStore.getObject('effect', effectId)
							return effect
								? {
									id: effect.id,
									label: effect.name,
								}
								: null
						})
						.filter(Boolean)
				}
			}
		},
		closeModal() {
			navigationStore.setModal(false)
			this.success = false
			this.loading = false
			this.error = false
			this.initializeForm()
		},
		async saveItem() {
			if (!this.formData.name) {
				this.error = 'Name is required'
				return
			}

			this.loading = true
			try {
				const itemData = {
					...this.formData,
					effects: this.selectedEffects.map(effect => effect.id),
				}

				if (objectStore.getActiveObject('item')?.id) {
					await objectStore.updateObject('item', objectStore.getActiveObject('item').id, itemData)
				} else {
					await objectStore.createObject('item', itemData)
				}

				this.success = true
				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.error = error.message || 'An error occurred while saving the item'
			} finally {
				this.loading = false
			}
		},
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>

<style scoped>
.formContainer {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	padding: 1rem;
}
</style>
