<script setup>
import { onMounted } from 'vue'
import { navigationStore, objectStore } from '../../store/store.js'
import { NcActions, NcActionButton, NcNoteCard } from '@nextcloud/vue'
import ObjectTabs from '../../components/ObjectTabs.vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import AccountPlus from 'vue-material-design-icons/AccountPlus.vue'
import CalendarPlus from 'vue-material-design-icons/CalendarPlus.vue'
import FileDocumentPlusOutline from 'vue-material-design-icons/FileDocumentPlusOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import Download from 'vue-material-design-icons/Download.vue'
import AccountCheck from 'vue-material-design-icons/AccountCheck.vue'

// Load all necessary collections when component mounts
onMounted(async () => {
	try {
		// Load all collections that might be needed for character details
		await Promise.all([
			objectStore.fetchCollection('skill'),
			objectStore.fetchCollection('item'),
			objectStore.fetchCollection('condition'),
			objectStore.fetchCollection('event'),
			objectStore.fetchCollection('effect'),
		])
	} catch (error) {
		console.error('Error loading collections:', error)
	}
})

const downloadCharacterPdf = () => {
	const characterId = objectStore.getActiveObject('character').id
	fetch(`characters/${characterId}/download`)
		.then(response => {
			if (!response.ok) {
				throw new Error('Network response was not ok')
			}
			return response.blob()
		})
		.then(blob => {
			const link = document.createElement('a')
			link.href = window.URL.createObjectURL(blob)
			link.download = `${objectStore.getActiveObject('character').name}_character_sheet.pdf`
			link.click()
			window.URL.revokeObjectURL(link.href)
		})
		.catch(error => {
			console.error('Error downloading PDF:', error)
			// Handle error (e.g., show error message to user)
		})
}
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<!-- app-content-wrapper is optional, only use if app-content-list  -->
			<div>
				<div class="head">
					<h1 class="h1">
						{{ objectStore.getActiveObject('character').name }}
					</h1>
					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editCharacter')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Character Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('addSkillToCharacter')">
							<template #icon>
								<FileDocumentPlusOutline :size="20" />
							</template>
							Skills bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('addItemToCharacter')">
							<template #icon>
								<AccountPlus :size="20" />
							</template>
							Items bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('addConditionToCharacter')">
							<template #icon>
								<EmoticonSickOutline :size="20" />
							</template>
							Condities bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('addEventToCharacter')">
							<template #icon>
								<CalendarPlus :size="20" />
							</template>
							Events bewerken
						</NcActionButton>
						<NcActionButton @click="downloadCharacterPdf">
							<template #icon>
								<Download :size="20" />
							</template>
							Als pdf downloaden
						</NcActionButton>
						<NcActionButton>
							<template #icon>
								<AccountCheck :size="20" />
							</template>
							Accoderen
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteCharacter')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</div>
				<NcNoteCard v-if="objectStore.getActiveObject('character').notice" type="info">
					{{ objectStore.getActiveObject('character').notice }}
				</NcNoteCard>
				<div class="detailGrid">
					<div>
						<b>Sammenvatting:</b>
						<span>{{ objectStore.getActiveObject('character').summary }}</span>
					</div>
				</div>
				<span>{{ objectStore.getActiveObject('character').description }}</span>
				<div class="tabContainer">
					<ObjectTabs
						type="character"
						:object="objectStore.getActiveObject('character')" />
				</div>
			</div>
		</div>
	</div>
</template>

<style>
h4 {
  font-weight: bold;
}

.head{
	display: flex;
	justify-content: space-between;
}

.button{
	max-height: 10px;
}

.h1 {
  display: block !important;
  font-size: 2em !important;
  margin-block-start: 0.67em !important;
  margin-block-end: 0.67em !important;
  margin-inline-start: 0px !important;
  margin-inline-end: 0px !important;
  font-weight: bold !important;
  unicode-bidi: isolate !important;
}

.dataContent {
  display: flex;
  flex-direction: column;
}

/* Add margin to counter bubble only when inside nav-item */
.nav-item .counter-bubble__counter {
    margin-left: 10px;
}

/* Style for stat effects to prevent truncation */
.stat-effects {
  white-space: normal;
  word-wrap: break-word;
  overflow-wrap: break-word;
  max-width: 100%;
  line-height: 1.4;
  padding: 4px 0;
}

/* Ensure list items expand properly with multi-line content */
:deep(.app-content-list-item) {
  height: auto !important;
  min-height: 44px;
}

:deep(.app-content-list-item-line-one),
:deep(.app-content-list-item-line-two) {
  white-space: normal;
  overflow: visible;
  text-overflow: clip;
}

/* Alternative solution with horizontal scrollbar */
/* Uncomment this and comment out the above .stat-effects if you prefer scrolling
.stat-effects {
  white-space: nowrap;
  overflow-x: auto;
  max-width: 100%;
  display: block;
  padding-bottom: 5px;
}
*/
</style>
