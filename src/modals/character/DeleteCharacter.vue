<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'deleteCharacter'"
		name="Karakter verwijderen"
		size="normal"
		:can-close="false">
		<p>
			Wil je <b>{{ objectStore.getActiveObject('character').name }}</b> definitief verwijderen? Deze actie kan niet ongedaan worden gemaakt.
		</p>

		<template #actions>
			<NcButton @click="closeModal">
				<template #icon>
					<Cancel :size="20" />
				</template>
				Annuleren
			</NcButton>
			<NcButton type="error"
				:disabled="loading"
				@click="deleteCharacter">
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
	NcLoadingIcon,
} from '@nextcloud/vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'DeleteCharacter',
	components: {
		NcDialog,
		NcButton,
		NcLoadingIcon,
		Cancel,
		TrashCanOutline,
	},
	data() {
		return {
			loading: false,
		}
	},
	methods: {
		closeModal() {
			objectStore.clearActiveObject('character')
			navigationStore.closeModal()
		},
		async deleteCharacter() {
			this.loading = true
			try {
				const character = objectStore.getActiveObject('character')
				if (!character?.id) {
					throw new Error('Geen karakter geselecteerd om te verwijderen')
				}
				await objectStore.deleteObject('character', character.id)
				this.closeModal()
			} catch (error) {
				console.error('Error deleting character:', error)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>
