<script setup>
import { skillStore, navigationStore, effectStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editSkill'"
		name="Skill"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Skill succesvol aangepast</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcTextField :disabled="loading"
				label="Name *"
				required
				:value.sync="skillItem.name" />
			<NcTextArea :disabled="loading"
				label="Description"
				type="textarea"
				:value.sync="skillItem.description" />
			<NcTextField :disabled="loading"
				label="Required Score"
				type="number"
				:value.sync="skillItem.requiredScore" />
			<NcSelect v-bind="effects"
				v-model="effects.value"
				input-label="Effects"
				:loading="effectsLoading"
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
				@click="editSkill()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && skillStore.skillItem?.id" :size="20" />
					<Plus v-if="!loading && !skillStore.skillItem?.id" :size="20" />
				</template>
				{{ skillStore.skillItem?.id ? 'Opslaan' : 'Aanmaken' }}
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
	NcLoadingIcon,
	NcNoteCard,
	NcSelect,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Help from 'vue-material-design-icons/Help.vue'

export default {
	name: 'EditSkill',
	components: {
		NcDialog,
		NcTextField,
		NcTextArea,
		NcSelect,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		ContentSaveOutline,
		Cancel,
		Plus,
		Help,
	},
	data() {
		return {
			skillItem: {
				name: '',
				description: '',
				requiredScore: '',
			},
			effects: {},
			effectsLoading: false,
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'editSkill' && !this.hasUpdated) {
			if (skillStore.skillItem?.id) {
				this.skillItem = {
					...skillStore.skillItem,
					name: skillStore.skillItem.name || '',
					description: skillStore.skillItem.description || '',
					requiredScore: skillStore.skillItem.requiredScore || '',
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
			this.skillItem = {
				name: '',
				description: '',
				requiredScore: '',
			}
		},
		fetchEffects() {
			this.effectsLoading = true

			effectStore.refreshEffectList()
				.then(() => {
					const activatedEffects = skillStore.skillItem?.id // if modal is an edit modal
						? effectStore.effectList.filter((effect) => { // filter through the list of effects
							return (skillStore.skillItem.effects || [])
								.map(String) // ensure all the effect id's in the skill are a string (this does not change the resulting data type)
								.includes(effect.id.toString()) // check if the current effect in the filter exists on the skill's effects
						})
						: null

					this.effects = {
						multiple: true,
						closeOnSelect: false,
						options: effectStore.effectList.map((effect) => ({
							id: effect.id,
							label: effect.name,
						})),
						value: activatedEffects
							? activatedEffects.map((effect) => ({
								id: effect.id,
							    label: effect.name,
							}))
							: null,
					}

					this.effectsLoading = false
				})
		},
		async editSkill() {
			this.loading = true
			try {
				await skillStore.saveSkill({
					...this.skillItem,
					effects: this.effects.value.map((effect) => effect.id),
				})
				this.success = true
				this.loading = false
				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.success = false

				this.error = error.message || 'An error occurred while saving the skill'
			}
		},
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>
