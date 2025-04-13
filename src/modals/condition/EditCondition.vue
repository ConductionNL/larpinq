<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editCondition'"
		:name="`${objectStore.getActiveObject('condition')?.id ? 'Bewerk' : 'Nieuwe'} conditie`"
		size="normal"
		:can-close="false">
		<div class="content">
			<NcTextField
				:value="condition.name"
				label="Naam"
				@update:value="condition.name = $event" />

			<NcTextField
				:value="condition.description"
				label="Beschrijving"
				type="textarea"
				@update:value="condition.description = $event" />

			<div class="effects">
				<h3>Effecten</h3>
				<ObjectList :objects="objectStore.getCollection('effect').results" />
			</div>
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
				@click="saveCondition">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && objectStore.getActiveObject('condition')?.id" :size="20" />
					<Plus v-if="!loading && !objectStore.getActiveObject('condition')?.id" :size="20" />
				</template>
				{{ objectStore.getActiveObject('condition')?.id ? 'Opslaan' : 'Aanmaken' }}
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
import Plus from 'vue-material-design-icons/Plus.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import ObjectList from '../../components/ObjectList.vue'

export default {
	name: 'EditCondition',
	components: {
		NcDialog,
		NcButton,
		NcTextField,
		NcLoadingIcon,
		ContentSaveOutline,
		Plus,
		Cancel,
		ObjectList,
	},
	data() {
		return {
			loading: false,
			hasUpdated: false,
			condition: {
				name: '',
				description: '',
			},
			effects: [],
		}
	},
	watch: {
		'navigationStore.modal'(newVal) {
			if (newVal === 'editCondition' && !this.hasUpdated) {
				this.updateForm()
			}
		},
	},
	methods: {
		updateForm() {
			if (objectStore.getActiveObject('condition')?.id && navigationStore.modal === 'editCondition' && !this.hasUpdated) {
				const condition = objectStore.getActiveObject('condition')
				this.condition = {
					...condition,
					name: condition.name || '',
					description: condition.description || '',
				}
				this.effects = condition.effects || []
				this.hasUpdated = true
			}
		},
		closeModal() {
			this.condition = {
				name: '',
				description: '',
			}
			this.effects = []
			this.hasUpdated = false
			objectStore.clearActiveObject('condition')
			navigationStore.closeModal()
		},
		async saveCondition() {
			this.loading = true
			try {
				await objectStore.saveObject('condition', {
					...this.condition,
					effects: this.effects,
				})
				this.closeModal()
			} catch (error) {
				console.error('Error saving condition:', error)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.content {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.effects {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}
</style>
