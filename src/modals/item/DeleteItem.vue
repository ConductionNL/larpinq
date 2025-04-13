<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'deleteItem'"
		name="Item verwijderen"
		size="normal"
		:can-close="false">
		<p>
			Wil je <b>{{ objectStore.getActiveObject('item').name }}</b> definitief verwijderen? Deze actie kan niet ongedaan worden gemaakt.
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
				@click="deleteItem">
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
	name: 'DeleteItem',
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
			objectStore.clearActiveObject('item')
			navigationStore.closeModal()
		},
		async deleteItem() {
			this.loading = true
			try {
				await objectStore.deleteObject('item', objectStore.getActiveObject('item').id)
				this.closeModal()
			} catch (error) {
				console.error('Error deleting item:', error)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>
