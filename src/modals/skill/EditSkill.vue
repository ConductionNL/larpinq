<script setup>
import { skillStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editSkill'"
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
				:value.sync="skillStore.skillItem.name" />
			<NcTextArea :disabled="loading"
				label="Description"
				type="textarea"
				:value.sync="skillStore.skillItem.description" />
			<NcTextField :disabled="loading"
				label="Effect"
				:value.sync="skillStore.skillItem.effect" />
			<NcTextField :disabled="loading"
				label="Required Score"
				type="number"
				:value.sync="skillStore.skillItem.requiredScore" />
		</div>

		<template #actions>
			<NcButton @click="navigationStore.setModal(false)">
				<template #icon><Cancel :size="20" /></template>
				{{ success ? 'Sluiten' : 'Annuleer' }}
			</NcButton>
			<NcButton @click="openLink('https://conduction.gitbook.io/opencatalogi-nextcloud/gebruikers/publicaties', '_blank')">
				<template #icon><Help :size="20" /></template>
				Help
			</NcButton>
			<NcButton v-if="!success" :disabled="loading" type="primary" @click="editSkill()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && skillStore.skillItem.id" :size="20" />
					<Plus v-if="!loading && !skillStore.skillItem.id" :size="20" />
				</template>
				{{ skillStore.skillItem.id ? 'Opslaan' : 'Aanmaken' }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton, NcDialog, NcTextField, NcTextArea, NcLoadingIcon, NcNoteCard
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Help from 'vue-material-design-icons/Help.vue'

export default {
	name: 'EditSkill',
	components: {
		NcDialog, NcTextField, NcTextArea, NcButton, NcLoadingIcon, NcNoteCard,
		ContentSaveOutline, Cancel, Plus, Help,
	},
	data() {
		return {
			success: false,
			loading: false,
			error: false,
		}
	},
	methods: {
		async editSkill() {
			this.loading = true
			try {
				await skillStore.saveSkill()
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
				
				this.error = error.message || 'An error occurred while saving the skill'
			}
		},
		openLink(url, target) {
			window.open(url, target)
		}
	},
}
</script>
