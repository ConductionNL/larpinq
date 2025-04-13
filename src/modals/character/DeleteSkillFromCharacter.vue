<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'deleteSkillFromCharacter'"
		name="Vaardigheid van karakter verwijderen"
		size="normal"
		:can-close="false">
		<p>
			Wil je <b>{{ objectStore.getActiveObject('skill').name }}</b> verwijderen van <b>{{ objectStore.getActiveObject('character').name }}</b>?
		</p>
		<NcNoteCard type="info" heading="Let op">
			Het verwijderen van een vaardigheid op een karakter zal leiden tot een herberekening van de statistieken van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.
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
				@click="deleteSkillFromCharacter">
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
	name: 'DeleteSkillFromCharacter',
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
			objectStore.clearActiveObject('character')
			navigationStore.closeModal()
		},
		async deleteSkillFromCharacter() {
			this.loading = true
			try {
				const characterClone = { ...objectStore.getActiveObject('character') }
				const skillId = objectStore.getActiveObject('skill').id

				if (!characterClone.id) {
					throw new Error('Geen karakter geselecteerd')
				}

				if (!skillId) {
					throw new Error('Geen vaardigheid geselecteerd')
				}

				// Find the index of the skill to delete
				const index = characterClone.skills.findIndex(item => item === skillId)

				// Remove the skill if it exists
				if (index !== -1) {
					characterClone.skills.splice(index, 1)
				} else {
					throw new Error('Vaardigheid kon niet worden gevonden op het karakter, mogelijk is deze al verwijderd.')
				}

				await objectStore.saveObject('character', characterClone)
				this.closeModal()
			} catch (error) {
				console.error('Error removing skill from character:', error)
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
