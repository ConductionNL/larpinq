<script setup>
import { itemStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<!-- app-content-wrapper is optional, only use if app-content-list  -->
			<div>
				<div class="head">
					<h1 class="h1">
						{{ itemStore.itemItem.name }}
					</h1>

					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editItem')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteItem')">
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
						<span>{{ itemStore.itemItem.summary }}</span>
					</div>
				</div>
				<span>{{ itemStore.itemItem.description }}</span>
			</div>
		</div>
		<div class="tabContainer">
			<BTabs content-class="mt-3" justified>
				<BTab>
					<template #title>
						Effects <NcCounterBubble>{{ itemStore.itemItem?.effects?.length || 0 }}</NcCounterBubble>
					</template>
					<div v-if="itemStore.itemItem?.effects?.length > 0">
						<NcListItem v-for="(effect) in itemStore.itemItem.effects"
							:key="effect.id"
							:name="effect.name"
							:bold="false"
							:force-display-actions="true"
							:details="effect?.modification || ''"
							:counter-number="effect?.modifier">
							<template #icon>
								<MagicStaff disable-menu
									:size="44" />
							</template>
							<template #subname>
								{{ effect.name }}
							</template>
						</NcListItem>
					</div>
					<div v-else>
						Geen effects gevonden
					</div>
				</BTab>
				<BTab>
					<template #title>
						Characters <NcCounterBubble>{{ itemStore.relations ? itemStore.relations.length : 0 }}</NcCounterBubble>
					</template>
					<ObjectList :objects="itemStore.relations" />							
				</BTab>
				<BTab>
					<template #title>
						Logging <NcCounterBubble>{{ itemStore.auditTrails ? itemStore.auditTrails.length : 0 }}</NcCounterBubble>
					</template>
					<AuditList :logs="itemStore.auditTrails" />
				</BTab>
			</BTabs>
		</div>
	</div>
</template>

<script>
import { BTabs, BTab } from 'bootstrap-vue'
import { NcLoadingIcon, NcListItem, NcActions, NcActionButton, NcCounterBubble } from '@nextcloud/vue'

// Custom components
import AuditList from '../auditTrail/AuditList.vue'
import ObjectList from '../objects/ObjectList.vue'

import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'

export default {
	name: 'ItemDetails',
	components: {
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		NcCounterBubble,
		BTabs,
		BTab,
		// Custom components
		AuditList,
		ObjectList,
		// Icons
		MagicStaff,
		BriefcaseAccountOutline,
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
</style>
