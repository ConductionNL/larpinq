<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<!-- app-content-wrapper is optional, only use if app-content-list  -->
			<div>
				<div class="head">
					<h1 class="h1">
						{{ objectStore.getActiveObject('player').name }}
					</h1>
					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editPlayer')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deletePlayer')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</div>
				<div class="grid">
					<div class="gridContent">
						<h4>Sammenvatting:</h4>
						<span>{{ objectStore.getActiveObject('player').summary }}</span>
					</div>
				</div>
			</div>
		</div>
		<div class="tabContainer">
			<BTabs content-class="mt-3" justified>
				<BTab>
					<template #title>
						Characters <NcCounterBubble>{{ objectStore.getRelations('player') ? objectStore.getRelations('player').length : 0 }}</NcCounterBubble>
					</template>
					<ObjectList :objects="objectStore.getRelations('player')" />							
				</BTab>
				<BTab>
					<template #title>
						Logging <NcCounterBubble>{{ objectStore.getAuditTrails('player') ? objectStore.getAuditTrails('player').length : 0 }}</NcCounterBubble>
					</template>
					<AuditTable :logs="objectStore.getAuditTrails('player')" />
				</BTab>
			</BTabs>
		</div>
	</div>
</template>

<script>
import {
	NcListItem,
	NcCounterBubble,
	NcActions,
	NcActionButton,
} from '@nextcloud/vue'
import { BTabs, BTab } from 'bootstrap-vue'

// Custom components
import AuditTable from '../auditTrail/AuditTable.vue'
import ObjectList from '../../components/ObjectList.vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'PlayerDetails',
	components: {
		NcListItem,
		NcCounterBubble,
		NcActions,
		NcActionButton,
		BTabs,
		BTab,
		// Custom components
		AuditTable,
		ObjectList,
		// Icons
		DotsHorizontal,
		Pencil,
		TrashCanOutline,
	},
}
</script>

<style>
h4 {
  font-weight: bold
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

.grid {
  display: grid;
  grid-gap: 24px;
  grid-template-columns: 1fr 1fr;
  margin-block-start: var(--zaa-margin-50);
  margin-block-end: var(--zaa-margin-50);
}

.gridContent {
  display: flex;
  gap: 25px;
}

.tabPanel {
  padding: 20px 10px;
  min-height: 100%;
  max-height: 100%;
  height: 100%;
  overflow: auto;
}

/* Add margin to counter bubble only when inside nav-item */
.nav-item .counter-bubble__counter {
    margin-left: 10px;
}

.head {
    display: flex;
    justify-content: space-between;
}
</style>
