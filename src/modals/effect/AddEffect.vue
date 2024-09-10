<script setup>
import { effectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addEffect'"
		name="Effect"
		size="normal"
		:can-close="false">
		<NcNoteCard v-if="success" type="success">
			<p>Effect succesvol aangepast</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>

		<div v-if="!success" class="formContainer">
			<NcTextField :disabled="loading"
				label="Name *"
				required
				:value.sync="effect.name" />
			<NcTextArea :disabled="loading"
				label="Description"
				type="textarea"
				:value.sync="effect.description" />
			<NcTextField :disabled="loading"
				label="Stat ID"
				:value.sync="effect.statId" />
			<NcTextField :disabled="loading"
				label="Modifier"
				type="number"
				:value.sync="effect.modifier" />
			<NcSelect
				v-bind="modificationOptions"
				v-model="effect.modification"
				:disabled="loading" />
			<NcSelect
				v-bind="cumulativeOptions"
				v-model="effect.cumulative"
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
				@click="editEffect()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && effectStore.effectItem.id" :size="20" />
					<Plus v-if="!loading && !effectStore.effectItem.id" :size="20" />
				</template>
				{{ effectStore.effectItem.id ? 'Opslaan' : 'Aanmaken' }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton, NcDialog, NcTextField, NcTextArea, NcSelect, NcLoadingIcon, NcNoteCard,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Help from 'vue-material-design-icons/Help.vue'

export default {
	name: 'AddEffect',
	components: {
		NcDialog,
		NcTextField,
		NcTextArea,
		NcButton,
		NcSelect,
		NcLoadingIcon,
		NcNoteCard,
		ContentSaveOutline,
		Cancel,
		Plus,
		Help,
	},
	data() {
		return {
			success: false,
			loading: false,
			error: false,
			modificationOptions: {
				inputLabel: 'Modification',
				multiple: false,
				options: [{ id: 'positive', label: 'Positive' }, { id: 'negative', label: 'Negative' }],
			},
			cumulativeOptions: {
				inputLabel: 'Cumulative',
				multiple: false,
				options: [{ id: 'cumulative', label: 'Cumulative' }, { id: 'non-cumulative', label: 'Non-cumulative' }],
			},
			effect: {
				name: '',
				description: '',
				statId: '',
				modifier: '',
				modification: { id: 'positive', label: 'Positive' },
				cumulative: { id: 'cumulative', label: 'Cumulative' },
				 },
		}
	},
	methods: {
		async editEffect() {
			effectStore.setEffectItem(this.effect)
			this.loading = true
			try {
				await effectStore.saveEffect()
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
				this.error = error.message || 'An error occurred while saving the effect'
			}
		},
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>
