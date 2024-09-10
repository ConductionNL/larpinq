<script setup>
import { templateStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.dialog === 'deleteTemplate'"
		name="Sjabloon verwijderen"
		size="normal"
		:can-close="false">

		<p v-if="!success">
			Wil je <b>{{ templateStore.templateItem.name }}</b> definitief verwijderen? Deze actie kan niet ongedaan worden gemaakt.
		</p>

		<NcNoteCard v-if="success" type="success">
			<p>Sjabloon succesvol verwijderd</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<template #actions>
			<NcButton
				@click="navigationStore.setDialog(false)">
				<template #icon>
					<Cancel :size="20" />
				</template>
				{{ success ? 'Sluiten' : 'Annuleer' }}
			</NcButton>
			<NcButton
				v-if="!success"
				:disabled="loading"
				type="error"
				@click="deleteTemplate()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<TrashCanOutline v-if="!loading" :size="20" />
				</template>
				Verwijderen
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
	NcSelect,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

import Cancel from 'vue-material-design-icons/Cancel.vue' 
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue' 

export default {
	name: 'DeleteTemplate',
	components: {
		NcDialog,
		NcTextField,
		NcTextArea,
		NcButton,
		NcSelect,
		NcLoadingIcon,
		NcNoteCard,
		// Icons
		TrashCanOutline,
		Cancel,
	},
	data() {
		return {
			success: false,
			loading: false,
			error: false,
		}
	},
	methods: {
		async deleteTemplate() {
			this.loading = true
			try {
				await templateStore.deleteTemplate()
				// Close modal or show success message
				this.success = true
				this.loading = false
				this.error = false
				setTimeout(() => {
					this.success = false
					navigationStore.setDialog(false)
				}, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'Er is een fout opgetreden bij het verwijderen van het sjabloon'
			}
		}
	},
}
</script>
