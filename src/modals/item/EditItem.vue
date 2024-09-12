<script setup>
import { itemStore, effectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editItem'"
		name="Voorwerp"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Voorwerp succesvol aangepast</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcTextField :disabled="loading"
				label="Name *"
				required
				:value.sync="itemItem.name" />
			<NcTextArea :disabled="loading"
				label="Description"
				type="textarea"
				:value.sync="itemItem.description" />
			<NcSelect v-bind="effects"
				v-model="effects.value"
				input-label="Effects"
				:loading="effectsLoading"
				:disabled="loading" />
			<NcCheckboxRadioSwitch :disabled="loading"
				label="Unique"
				type="switch"
				:checked.sync="itemItem.unique">
				Unique
			</NcCheckboxRadioSwitch>
		</div>

		<template #actions>
			<NcButton
				@click="closeModal">
				<template #icon>
					<Cancel :size="20" />
				</template>
				{{ success ? 'Sluiten' : 'Annuleer' }}
			</NcButton>
			<NcButton
				@click="openLink('https://conduction.gitbook.io/opencatalogi-nextcloud/gebruikers/publicaties', '_blank')">
				<template #icon>
					<Help :size="20" />
				</template>
				Help
			</NcButton>
			<NcButton
				v-if="!success"
				:disabled="loading"
				type="primary"
				@click="editItem()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && itemStore.itemItem.id" :size="20" />
					<Plus v-if="!loading && !itemStore.itemItem.id" :size="20" />
				</template>
				{{ itemStore.itemItem.id ? 'Opslaan' : 'Aanmaken' }}
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
			effects: {},
			effectsLoading: false,
			itemItem: {
				name: '',
				description: '',
				unique: '',
			},
			hasUpdated: false,
		}
	},

	updated() {
		if (navigationStore.modal === 'editItem' && !this.hasUpdated) {
			if (itemStore.itemItem.id) {
				this.itemItem = {
					...itemStore.itemItem,
					name: itemStore.itemItem.name || '',
					description: itemStore.itemItem.description || '',
					unique: itemStore.itemItem.unique || '',
				}
			}
			this.fetchEffects()
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
			this.itemItem = {
				name: '',
				description: '',
				unique: '',
			}
		},
		fetchEffects() {
			this.effectsLoading = true

			effectStore.refreshEffectList()
				.then(() => {
					const activeEffects = itemStore.itemItem.id
						? effectStore.effectList.filter((effect) => {
							return itemStore.itemItem.effects
								.map(String)
								.includes(effect.id)
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
		async editItem() {
			this.loading = true
			try {
				await itemStore.saveItem({
					...this.itemItem,
					effects: this.effects.value.map((effect) => effect.id),
				})
				this.success = true
				this.loading = false
				this.error = false
				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'An error occurred while saving the item'
			}
		},
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>
