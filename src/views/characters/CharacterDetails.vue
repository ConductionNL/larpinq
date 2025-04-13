<script setup>
import { navigationStore, objectStore } from '../../store/store.js'
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
						<NcActionButton @click="navigationStore.setModal('renderPdfFromCharacter')">
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
					<BTabs content-class="mt-3" justified>
						<BTab active>
							<template #title>
								Eigenschappen <NcCounterBubble>{{ objectStore.getActiveObject('character').stats ? Object.keys(objectStore.getActiveObject('character').stats).length : 0 }}</NcCounterBubble>
							</template>
							<div v-if="objectStore.getActiveObject('character').stats">
								<NcListItem v-for="(stat, id) in objectStore.getActiveObject('character').stats"
									:key="id"
									:name="stat.value + ' ' + stat.name"
									:bold="false">
									<template #icon>
										<ShieldSwordOutline :size="44" />
									</template>
									<template #subname>
										<div class="stat-effects">
											Base: {{ stat.base }}{{ stat.audit ? ', Effects: ' + stat.audit.map(a => `(${a.type.charAt(0).toUpperCase() + a.type.slice(1)}:${a.name} ${a.effect.modification > 0 ? '+' : '-'}${a.effect.modifier})`).join(', ') : '' }}
										</div>
									</template>
								</NcListItem>
							</div>
							<div v-else>
								Geen eigenschappen gevonden
							</div>
						</BTab>

						<BTab>
							<template #title>
								Skills <NcCounterBubble>{{ objectStore.getActiveObject('character').skills ? objectStore.getActiveObject('character').skills.length : 0 }}</NcCounterBubble>
							</template>
							<ObjectList :objects="objectStore.getActiveObject('character').skills" />
						</BTab>

						<BTab>
							<template #title>
								Items <NcCounterBubble>{{ objectStore.getActiveObject('character').items ? objectStore.getActiveObject('character').items.length : 0 }}</NcCounterBubble>
							</template>
							<ObjectList :objects="objectStore.getActiveObject('character').items" />
						</BTab>

						<BTab>
							<template #title>
								Conditions <NcCounterBubble>{{ objectStore.getActiveObject('character').conditions ? objectStore.getActiveObject('character').conditions.length : 0 }}</NcCounterBubble>
							</template>
							<ObjectList :objects="objectStore.getActiveObject('character').conditions" />
						</BTab>

						<BTab>
							<template #title>
								Events <NcCounterBubble>{{ objectStore.getActiveObject('character').events ? objectStore.getActiveObject('character').events.length : 0 }}</NcCounterBubble>
							</template>
							<ObjectList :objects="objectStore.getActiveObject('character').events" />
						</BTab>

						<BTab>
							<template #title>
								Background <NcCounterBubble>{{ objectStore.getActiveObject('character').background ? 1 : 0 }}</NcCounterBubble>
							</template>
							<div v-if="objectStore.getActiveObject('character').background">
								{{ objectStore.getActiveObject('character').background }}
							</div>
							<div v-else>
								Geen achtergrond gevonden
							</div>
						</BTab>

						<BTab>
							<template #title>
								Logging <NcCounterBubble>{{ objectStore.getAuditTrails('character')?.length || 0 }}</NcCounterBubble>
							</template>
							<AuditTable :logs="objectStore.getAuditTrails('character')" />
						</BTab>

						<!-- <BTab>
							<template #title>
								Audit
							</template>
							<AuditTable :audit-data="objectStore.getActiveObject('character').stats" />
						</BTab> -->
					</BTabs>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
// Components
import { BTabs, BTab } from 'bootstrap-vue'
import { NcActions, NcActionButton, NcListItem, NcNoteCard, NcCounterBubble } from '@nextcloud/vue'

// Custom components
import ObjectList from '../../components/ObjectList.vue'
import AuditTable from '../auditTrail/AuditTable.vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import AccountPlus from 'vue-material-design-icons/AccountPlus.vue'
import CalendarPlus from 'vue-material-design-icons/CalendarPlus.vue'
import FileDocumentPlusOutline from 'vue-material-design-icons/FileDocumentPlusOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'
import Download from 'vue-material-design-icons/Download.vue'
import AccountCheck from 'vue-material-design-icons/AccountCheck.vue'

export default {
	name: 'CharacterDetails',
	components: {
		// Components
		NcActions,
		NcActionButton,
		NcListItem,
		NcNoteCard,
		NcCounterBubble,
		BTabs,
		BTab,
		// Custom components
		ObjectList,
		AuditTable,
		// Icons
		DotsHorizontal,
		Pencil,
		AccountPlus,
		CalendarPlus,
		FileDocumentPlusOutline,
		TrashCanOutline,
		EmoticonSickOutline,
		ShieldSwordOutline,
		Download,
		AccountCheck,
	},
	methods: {
		/**
		 * Download character as PDF
		 *
		 * @return {void}
		 */
		downloadCharacterPdf() {
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
		},
	},
}
</script>

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
