<script setup>
import { playerStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcModal
		v-if="navigationStore.modal === 'editPlayer'"
		ref="modalRef"
		@close="closeModal">
		<div class="modalContent">
			<h2>Speler {{ player.id ? "Aanpassen" : "Aanmaken" }}</h2>
			<NcNoteCard v-if="succes" type="success">
				<p>Speler succesvol toegevoegd</p>
			</NcNoteCard>
			<NcNoteCard v-if="error" type="error">
				<p>{{ error }}</p>
			</NcNoteCard>

			<form v-if="!succes" @submit.prevent="handleSubmit">
				<div class="form-group">
					<NcTextField
						id="name"
						label="Name"
						:value.sync="player.name"
						required />
					<NcTextArea
						id="description"
						label="Description"
						:value.sync="player.description" />
				</div>
			</form>

			<NcButton
				v-if="!succes"
				:disabled="loading"
				type="primary"
				@click="editPlayer()">
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
	name: 'EditPlayer',
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
			player: {
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
		if (playerStore.playerItem?.id && navigationStore.modal === 'editPlayer' && !this.hasUpdated) {
			this.player = {
				...playerStore.playerItem,
				name: playerStore.playerItem.name || '',
				description: playerStore.playerItem.description || '',
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
			this.player = {
				name: '',
				description: '',
			}
		},
		async editPlayer() {
			this.loading = true
			try {
				await playerStore.savePlayer(this.player)
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
