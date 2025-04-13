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
						{{ objectStore.getActiveObject('effect').name }}
					</h1>

					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editEffect')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('eleteEffect')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</div>
				<div class="detailGrid">
					<div>
						<b>Sammenvatting:</b>
						<span>{{ objectStore.getActiveObject('effect').summary }}</span>
					</div>
				</div>
				<span>{{ objectStore.getActiveObject('effect').description }}</span>

				<div class="tabContainer">
					<BTabs content-class="mt-3" justified>		
						<BTab>
							<template #title>
								Used by <NcCounterBubble>{{ objectStore.getRelations('effect') ? objectStore.getRelations('effect').length : 0 }}</NcCounterBubble>
							</template>
							<ObjectList :objects="objectStore.getRelations('effect')" />							
						</BTab>
						<BTab>
							<template #title>
								Logging <NcCounterBubble>{{ objectStore.getAuditTrails('effect') ? objectStore.getAuditTrails('effect').length : 0 }}</NcCounterBubble>
							</template>
							<AuditTable :logs="objectStore.getAuditTrails('effect')" />
						</BTab>
					</BTabs>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
// Components
import { BTabs, BTab } from 'bootstrap-vue'
import { NcLoadingIcon, NcActions, NcActionButton, NcListItem, NcCounterBubble } from '@nextcloud/vue'

// Custom components
import AuditTable from '../auditTrail/AuditTable.vue'
import ObjectList from '../../components/ObjectList.vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import ChatOutline from 'vue-material-design-icons/ChatOutline.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'EffectDetails',
	components: {
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		NcListItem,
		NcCounterBubble,
		BTabs,
		BTab,
		// Custom components
		AuditTable,
		ObjectList,
		// Icons
		DotsHorizontal,
		Pencil,
		ChatOutline,
		CalendarMonthOutline,
		BriefcaseAccountOutline,
		TrashCanOutline,
	},
}
</script>

<style>
.detailContainer {
    padding: 0.5rem;
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
  grid-gap: 1rem 24px !important;
  grid-template-columns: repeat( auto-fit, minmax(300px, 1fr) ) !important;
  margin-block-start: var(--zaa-margin-50);
  margin-block-end: var(--zaa-margin-50);
}

.gridContent {
  display: flex;
  flex-direction: column;
  gap: 2px !important;
}
.gridContent > h5 {
    margin-top: 12px !important;
}

.gridFullWidth {
    grid-column: 1 / -1;
}

/* Add margin to counter bubble only when inside nav-item */
.nav-item .counter-bubble__counter {
    margin-left: 10px;
}

</style>
