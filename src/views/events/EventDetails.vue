<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
import { NcActions, NcActionButton, NcNoteCard } from '@nextcloud/vue'
import ObjectTabs from '../../components/ObjectTabs.vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<div>
				<div class="head">
					<h1 class="h1">
						{{ objectStore.getActiveObject('event').name }}
					</h1>
					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editEvent')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Event Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteEvent')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</div>
				<NcNoteCard v-if="objectStore.getActiveObject('event').notice" type="info">
					{{ objectStore.getActiveObject('event').notice }}
				</NcNoteCard>
				<div class="detailGrid">
					<div>
						<b>Start datum:</b>
						<span>{{ new Date(objectStore.getActiveObject('event').startDate).toLocaleString() }}</span>
					</div>
					<div>
						<b>Eind datum:</b>
						<span>{{ new Date(objectStore.getActiveObject('event').endDate).toLocaleString() }}</span>
					</div>
					<div>
						<b>Locatie:</b>
						<span>{{ objectStore.getActiveObject('event').location }}</span>
					</div>
				</div>
				<span>{{ objectStore.getActiveObject('event').description }}</span>
				<div class="tabContainer">
					<ObjectTabs
						type="event"
						:object="objectStore.getActiveObject('event')" />
				</div>
			</div>
		</div>
	</div>
</template>

<style>
h4 {
  font-weight: bold;
}

.head {
	display: flex;
	justify-content: space-between;
}

.button {
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
</style>
