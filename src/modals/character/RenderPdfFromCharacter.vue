<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'renderPdfFromCharacter'"
		name="PDF genereren"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>PDF succesvol gegenereerd</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcSelect v-bind="templates"
				v-model="templates.value"
				input-label="Template *"
				:loading="templatesLoading"
				:disabled="templatesLoading"
				required />
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
				:disabled="loading || templatesLoading"
				type="primary"
				@click="renderPdf()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Save v-if="!loading" :size="20" />
				</template>
				Genereren
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
	NcSelect,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

import Cancel from 'vue-material-design-icons/Cancel.vue'
import Save from 'vue-material-design-icons/ContentSave.vue'
import Help from 'vue-material-design-icons/Help.vue'

/**
 * Component for rendering a PDF from a character using a selected template
 * @component
 */
export default {
	name: 'RenderPdfFromCharacter',
	components: {
		NcDialog,
		NcButton,
		NcSelect,
		NcLoadingIcon,
		NcNoteCard,
		// Icons
		Cancel,
		Save,
		Help,
	},
	data() {
		return {
			templates: {
				options: [],
				value: null,
			},
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	updated() {
		if (navigationStore.modal === 'renderPdfFromCharacter' && !this.hasUpdated) {
			this.templates.options = objectStore.getCollection('template').results.map((template) => ({
				id: template.id,
				label: template.name,
			}))
			this.hasUpdated = true
		}
	},
	methods: {
		/**
		 * Closes the modal and resets the state
		 */
		closeModal() {
			navigationStore.setModal(false)
			this.success = false
			this.loading = false
			this.error = false
			this.hasUpdated = false
			this.templates.value = null
			this.templates.options = []
		},

		/**
		 * Downloads the PDF for the current character using the selected template
		 */
		async renderPdf() {
			this.loading = true
			try {
				const character = objectStore.getActiveObject('character')
				if (!character?.id || !this.templates.value?.id) {
					throw new Error('Character or template not selected')
				}

				const pdfUrl = `/index.php/apps/larpingapp/characters/${character.id}/download/${this.templates.value.id}`
				window.open(pdfUrl, '_blank')

				this.success = true
				this.loading = false
				this.error = false
				setTimeout(() => {
					this.closeModal()
				}, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'Er is een fout opgetreden bij het genereren van de PDF'
			}
		},

		/**
		 * Opens a link in a new tab
		 * @param {string} url - The URL to open
		 * @param {string} target - The target window or tab
		 */
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>
