<script setup>
import { characterStore, eventStore, navigationStore } from '../../store/store.js'
import { onMounted } from 'vue'

</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'addEventToCharacter'"
		name="Event toevoegen aan karakter"
		size="normal"
		:can-close="false">

		<NcNoteCard v-if="success" type="success">
			<p>Event succesvol toegevoegd aan karakter</p>
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>
	
		<div v-if="!success" class="formContainer">
			<p>Let op: Het toevoegen van een event aan een karakter kan invloed hebben op de eigenschappen en geschiedenis van het karakter. Dit is een asynchroon proces, dus het kan even duren voordat de wijzigingen zichtbaar worden.</p>
			
			<NcSelect
				:disabled="loading"
				label="Event *"
				inputLabel="Event *"
				:options="eventStore.events"
				:value.sync="selectedEvent"
				option-label="name"
				option-value="id"
				required
			/>
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
			<NcButton v-if="!success" :disabled="loading" type="primary" @click="addEventToCharacter()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Plus v-if="!loading" :size="20" />
				</template>
				Toevoegen
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
	NcSelect,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

import Cancel from 'vue-material-design-icons/Cancel.vue' 
import Plus from 'vue-material-design-icons/Plus.vue' 
import Help from 'vue-material-design-icons/Help.vue' 

export default {
	name: 'AddEventToCharacter',
	components: {
		NcDialog,
		NcButton,
		NcSelect,
		NcLoadingIcon,
		NcNoteCard,
		// Icons
		Cancel,
		Plus,
		Help,
	},
	data() {
		return {
			success: false,
			loading: false,
			error: false,
			selectedEvent: null,
		}
	},
	mounted(){
		eventStore.refreshEventList()
	},
	methods: {
		async addEventToCharacter() {
			this.loading = true
			try {
				if (!characterStore.characterItem.events) {
					characterStore.characterItem.events = []
				}
				characterStore.characterItem.events.push(this.selectedEvent)
				await characterStore.saveCharacter()
				this.success = true
				this.loading = false
				this.error = false
				setTimeout(() => {
					this.success = false
					navigationStore.setModal(false)
				}, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'Er is een fout opgetreden bij het toevoegen van het event aan het karakter'
			}
		}
	},
}
</script>
