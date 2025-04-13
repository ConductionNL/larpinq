<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editItem'"
		:name="`${objectStore.getActiveObject('item')?.id ? 'Bewerk' : 'Nieuw'} item`"
		size="normal"
		:can-close="false">
		<div class="content">
			<NcTextField
				:value="item.name"
				label="Naam"
				@update:value="item.name = $event" />

			<NcTextField
				:value="item.description"
				label="Beschrijving"
				type="textarea"
				@update:value="item.description = $event" />

			<NcTextField
				:value="item.unique"
				label="Uniek"
				type="number"
				@update:value="item.unique = $event" />

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
				@click="saveItem">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && objectStore.getActiveObject('item')?.id" :size="20" />
					<Plus v-if="!loading && !objectStore.getActiveObject('item')?.id" :size="20" />
				</template>
				{{ objectStore.getActiveObject('item')?.id ? 'Opslaan' : 'Aanmaken' }}
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
	name: 'EditItem',
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
			item: {
				name: '',
				description: '',
				unique: 0,
			},
			effects: [],
		}
	},
	watch: {
		'navigationStore.modal'(newVal) {
			if (newVal === 'editItem' && !this.hasUpdated) {
				this.updateForm()
			}
		},
	},
	methods: {
		updateForm() {
			if (objectStore.getActiveObject('item')?.id && navigationStore.modal === 'editItem' && !this.hasUpdated) {
				const item = objectStore.getActiveObject('item')
				this.item = {
					...item,
					name: item.name || '',
					description: item.description || '',
					unique: item.unique || 0,
				}
				this.effects = item.effects || []
				this.hasUpdated = true
			}
		},
		closeModal() {
			this.item = {
				name: '',
				description: '',
				unique: 0,
			}
			this.effects = []
			this.hasUpdated = false
			objectStore.clearActiveObject('item')
			navigationStore.closeModal()
		},
		async saveItem() {
			this.loading = true
			try {
				await objectStore.saveObject('item', {
					...this.item,
					effects: this.effects,
				})
				this.closeModal()
			} catch (error) {
				console.error('Error saving item:', error)
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
