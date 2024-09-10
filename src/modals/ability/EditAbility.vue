<script setup>
import { abilityStore, navigationStore, effectStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editAbility'"
		name="Vaardigheid"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Vaardigheid succesvol aangepast</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcTextField :disabled="loading"
				label="Name *"
				required
				:value.sync="abilityItem.name" />
			<NcTextArea :disabled="loading"
				label="Description"
				type="textarea"
				:value.sync="abilityItem.description" />
			<NcTextField :disabled="loading"
				label="Base"
				:value.sync="abilityItem.base" />
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
				@click="editAbility()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && abilityStore.abilityItem.id" :size="20" />
					<Plus v-if="!loading && !abilityStore.abilityItem.id" :size="20" />
				</template>
				{{ abilityStore.abilityItem.id ? 'Opslaan' : 'Aanmaken' }}
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
	name: 'EditAbility',
	components: {
		NcDialog,
		NcTextField,
		NcTextArea,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		ContentSaveOutline,
		Cancel,
		Plus,
		Help,
		NcSelect,
	},
	data() {
		return {
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
			effects: {},
			effectsLoading: false,
			abilityItem: {
				name: '',
				description: '',
				base: '',
			},
		}
	},
	updated() {
		if (navigationStore.modal === 'editAbility' && !this.hasUpdated) {
			if (abilityStore.abilityItem.id) {
				this.abilityItem = {
					...abilityStore.abilityItem,
					name: abilityStore.abilityItem.name || '',
					description: abilityStore.abilityItem.description || '',
					base: abilityStore.abilityItem.base || '',
				}
			}
			this.fetchEffects()
			this.hasUpdated = true
		}
	},

	methods: {
		async editAbility() {
			this.loading = true
			try {
				await abilityStore.saveAbility({
					...this.abilityItem,
					effects: this.effects.value.map((effect) => effect.id),
				},
				)
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
				this.error = error.message || 'An error occurred while saving the ability'
			}
		},

		fetchEffects() {
			this.effectsLoading = true

			effectStore.refreshEffectList()
				.then(() => {
					const activeEffects = abilityStore.abilityItem.id
						? effectStore.effectList.filter((effect) => {
							return abilityStore.abilityItem.effects
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
