<script setup>
import { conditionStore, navigationStore, effectStore } from '../../store/store.js'
</script>

<template>
	<NcModal v-if="navigationStore.modal === 'editCondition'" ref="modalRef" @close="closeModal">
		<div class="modalContent">
			<h2>Conditie {{ conditionItem?.id ? 'Aanpassen' : 'Aanmaken' }}</h2>
			<NcNoteCard v-if="succes" type="success">
				<p>Taak succesvol toegevoegd</p>
			</NcNoteCard>
			<NcNoteCard v-if="error" type="error">
				<p>{{ error }}</p>
			</NcNoteCard>

			<form v-if="!succes" @submit.prevent="handleSubmit">
				<div class="form-group">
					<NcTextField
						id="name"
						label="Name"
						:value.sync="conditionItem.name"
						required />
					<NcTextArea
						id="description"
						label="Description"
						:value.sync="conditionItem.description" />
					<NcSelect v-bind="effects"
						v-model="effects.value"
						input-label="Effects"
						:loading="effectsLoading"
						:disabled="effectsLoading || loading" />
				</div>
			</form>

			<NcButton
				v-if="!succes"
				:disabled="loading"
				type="primary"
				@click="editCondition()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading" :size="20" />
				</template>
				Opslaan
			</NcButton>
		</div>
	</NcModal>
</template>

<script>
import {
	NcButton,
	NcModal,
	NcTextField,
	NcTextArea,
	NcLoadingIcon,
	NcNoteCard,
	NcSelect,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'

export default {
	name: 'EditCondition',
	components: {
		NcModal,
		NcTextField,
		NcTextArea,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcSelect,
		// Icons
		ContentSaveOutline,
	},
	data() {
		return {
			conditionItem: {
				name: '',
				description: '',
			},
			effects: {},
			effectsLoading: false,
			succes: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	mounted() {
		this.fetchEffects()
	},
	updated() {
		if (conditionStore.conditionItem?.id && navigationStore.modal === 'editCondition' && !this.hasUpdated) {
			this.conditionItem = {
				...conditionStore.conditionItem,
				name: conditionStore.conditionItem.name || '',
				description: conditionStore.conditionItem.description || '',
			}
			this.fetchEffects()
			this.hasUpdated = true
		}
	},
	methods: {
		closeModal() {
			navigationStore.setModal(false)
			this.succes = false
			this.loading = false
			this.error = false
			this.hasUpdated = false
			this.conditionItem = {
				name: '',
				description: '',
			}
		},
		fetchEffects() {
			this.effectsLoading = true

			effectStore.refreshEffectList()
				.then(() => {
					const activeEffects = conditionStore.conditionItem?.id
						? effectStore.effectList.filter((effect) => {
							return conditionStore.conditionItem.effects
								?.map(String)
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
		async editCondition() {
			this.loading = true
			try {
				await conditionStore.saveCondition({
					...this.conditionItem,
					effects: (this.effects?.value || []).map((effect) => effect.id),
				})
				// Close modal or show success message
				this.succes = true
				this.loading = false
				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.succes = false
				this.error = error.message || 'An error occurred while saving the character'
			}
		},
	},
}
</script>
