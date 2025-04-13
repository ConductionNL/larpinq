<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'deleteSkill'"
		name="Vaardigheid verwijderen"
		size="normal"
		:can-close="false">
		<p>
			Wil je <b>{{ objectStore.getActiveObject('skill').name }}</b> definitief verwijderen? Deze actie kan niet ongedaan worden gemaakt.
		</p>
		<NcNoteCard type="info" heading="Let op">
			Het verwijderen van een vaardigheid zal leiden tot een herberekening van de statistieken van alle karakters die deze vaardigheid hebben. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.
		</NcNoteCard>

		<template #actions>
			<NcButton @click="closeModal">
				<template #icon>
					<Cancel :size="20" />
				</template>
				Annuleren
			</NcButton>
			<NcButton type="error"
				:disabled="loading"
				@click="deleteSkill">
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
	NcNoteCard,
} from '@nextcloud/vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'DeleteSkill',
	components: {
		NcDialog,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
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
			objectStore.clearActiveObject('skill')
			navigationStore.closeModal()
		},
		async deleteSkill() {
			this.loading = true
			try {
				await objectStore.deleteObject('skill', objectStore.getActiveObject('skill').id)
				this.closeModal()
			} catch (error) {
				console.error('Error deleting skill:', error)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="css">
.notecard h2 {
	margin: 0;
}
</style>
