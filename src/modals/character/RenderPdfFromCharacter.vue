<script setup>
import { characterStore, navigationStore, templateStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'renderPdfFromCharacter'"
		name="PDF downloaden"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div class="formContainer">
			<p>Selecteer een template om een PDF te genereren van dit karakter.</p>

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
				Annuleer
			</NcButton>
			<NcButton @click="openLink('https://conduction.gitbook.io/opencatalogi-nextcloud/gebruikers/publicaties', '_blank')">
				<template #icon>
					<Help :size="20" />
				</template>
				Help
			</NcButton>
			<NcButton
				:disabled="loading || templatesLoading || !templates.value"
				type="primary"
				@click="downloadPdf()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Download v-if="!loading" :size="20" />
				</template>
				Download PDF
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
import Download from 'vue-material-design-icons/Download.vue'
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
		Download,
		Help,
	},
	data() {
		return {
			templates: {},
			templatesLoading: false,
			loading: false,
			error: false,
			hasUpdated: false,
		}
	},
	mounted() {
		this.fetchTemplates()
	},
	updated() {
		if (navigationStore.modal === 'renderPdfFromCharacter' && !this.hasUpdated) {
			this.fetchTemplates()
			this.hasUpdated = true
		}
	},
	methods: {
		/**
		 * Closes the modal and resets the state
		 */
		closeModal() {
			navigationStore.setModal(false)
			this.loading = false
			this.templatesLoading = false
			this.error = false
			this.hasUpdated = false
		},

		/**
		 * Downloads the PDF for the current character using the selected template
		 */
		async downloadPdf() {
			if (!characterStore.characterItem?.id || !this.templates.value?.id) {
				this.error = 'Selecteer eerst een template'
				return
			}

			this.loading = true
			try {
				// Generate PDF download URL using the correct format
				const pdfUrl = `/index.php/apps/larpingapp/characters/${characterStore.characterItem.id}/download/${this.templates.value.id}`
				
				// Open PDF in new tab
				window.open(pdfUrl, '_blank')
				
				this.closeModal()
			} catch (error) {
				this.loading = false
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

		/**
		 * Fetches the templates from the template store
		 */
		fetchTemplates() {
			this.templatesLoading = true

			templateStore.refreshTemplateList()
				.then(() => {
					this.templates = {
						options: templateStore.templateList.map((template) => ({
							id: template.id,
							label: template.name,
						})),
					}
					this.templatesLoading = false
				})
		},
	},
}
</script>
