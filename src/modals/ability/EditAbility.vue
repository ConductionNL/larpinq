<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'editAbility'"
		:name="`${objectStore.getActiveObject('ability')?.id ? 'Bewerk' : 'Nieuwe'} vaardigheid`"
		size="normal"
		:can-close="false">
		<div class="content">
			<NcTextField
				:value="ability.name"
				label="Naam"
				@update:value="ability.name = $event" />

			<NcTextField
				:value="ability.description"
				label="Beschrijving"
				type="textarea"
				@update:value="ability.description = $event" />

			<NcTextField
				:value="ability.base"
				label="Basis"
				type="number"
				@update:value="ability.base = $event" />

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
				@click="saveAbility">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading && objectStore.getActiveObject('ability')?.id" :size="20" />
					<Plus v-if="!loading && !objectStore.getActiveObject('ability')?.id" :size="20" />
				</template>
				{{ objectStore.getActiveObject('ability')?.id ? 'Opslaan' : 'Aanmaken' }}
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
	name: 'EditAbility',
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
			ability: {
				name: '',
				description: '',
				base: 0,
			},
			effects: [],
		}
	},
	watch: {
		'navigationStore.modal'(newVal) {
			if (newVal === 'editAbility' && !this.hasUpdated) {
				this.updateForm()
			}
		},
	},
	methods: {
		updateForm() {
			if (objectStore.getActiveObject('ability')?.id && navigationStore.modal === 'editAbility' && !this.hasUpdated) {
				const ability = objectStore.getActiveObject('ability')
				this.ability = {
					...ability,
					name: ability.name || '',
					description: ability.description || '',
					base: ability.base || 0,
				}
				this.effects = ability.effects || []
				this.hasUpdated = true
			}
		},
		closeModal() {
			this.ability = {
				name: '',
				description: '',
				base: 0,
			}
			this.effects = []
			this.hasUpdated = false
			objectStore.clearActiveObject('ability')
			navigationStore.closeModal()
		},
		async saveAbility() {
			this.loading = true
			try {
				await objectStore.saveObject('ability', {
					...this.ability,
					effects: this.effects,
				})
				this.closeModal()
			} catch (error) {
				console.error('Error saving ability:', error)
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
