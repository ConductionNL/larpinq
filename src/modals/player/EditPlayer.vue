<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editPlayer'"
		:name="`${objectStore.getActiveObject('player')?.id ? 'Bewerk' : 'Nieuwe'} speler`"
		size="normal"
		:can-close="false">
		<div class="content">
			<NcTextField
				:value="player.name"
				label="Naam"
				@update:value="player.name = $event" />

			<NcTextField
				:value="player.email"
				label="Email"
				@update:value="player.email = $event" />

			<NcTextField
				:value="player.description"
				label="Beschrijving"
				type="textarea"
				@update:value="player.description = $event" />
		</div>

		<template #actions>
			<NcButton @click="closeModal">
				<template #icon>
					<Cancel :size="20" />
				</template>
				Annuleren
			</NcButton>
			<NcButton type="primary"
				:disabled="loading"
				@click="savePlayer">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && objectStore.getActiveObject('player')?.id" :size="20" />
					<Plus v-if="!loading && !objectStore.getActiveObject('player')?.id" :size="20" />
				</template>
				{{ objectStore.getActiveObject('player')?.id ? 'Opslaan' : 'Aanmaken' }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
	NcTextField,
	NcLoadingIcon,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'EditPlayer',
	components: {
		NcDialog,
		NcTextField,
		NcLoadingIcon,
		// Icons
		ContentSaveOutline,
		Cancel,
		Plus,
	},
	data() {
		return {
			player: {
				name: '',
				description: '',
			},
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (objectStore.getActiveObject('player')?.id && navigationStore.modal === 'editPlayer' && !this.hasUpdated) {
			this.player = {
				...objectStore.getActiveObject('player'),
				name: objectStore.getActiveObject('player').name || '',
				description: objectStore.getActiveObject('player').description || '',
			}
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
			this.player = {
				name: '',
				description: '',
			}
		},
		async editPlayer() {
			this.loading = true
			try {
				await objectStore.saveObject('player', this.player)
				// Close modal or show success message
				this.success = true
				this.loading = false

				setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'Er is een fout opgetreden bij het opslaan van de speler'
			}
		},
	},
}
</script>
