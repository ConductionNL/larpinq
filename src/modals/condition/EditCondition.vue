<script setup>
import { conditionStore, navigationStore } from '../../store/store.js'
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
		// Icons
		ContentSaveOutline,
	},
	data() {
		return {
			conditionItem: {
				name: '',
				description: '',
			},
			succes: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (conditionStore.conditionItem?.id && navigationStore.modal === 'editCondition' && !this.hasUpdated) {
			this.conditionItem = {
				...conditionStore.conditionItem,
				name: conditionStore.conditionItem.name || '',
				description: conditionStore.conditionItem.description || '',
			}
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
		async editCondition() {
			this.loading = true
			try {
				await conditionStore.saveCondition(this.conditionItem)
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
