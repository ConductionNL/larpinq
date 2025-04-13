<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
import DOMPurify from 'dompurify'
</script>

<template>
	<div class="template-details">
		<div class="template-header">
			<h2>
				{{ objectStore.getActiveObject('template').name }}
				<NcActions>
					<NcActionButton @click="objectStore.setActiveObject('template', null); navigationStore.setModal('editTemplate')">
						<template #icon>
							<Pencil :size="20" />
						</template>
						Edit
					</NcActionButton>
					<NcActionButton @click="objectStore.setActiveObject('template', null); navigationStore.setDialog('deleteTemplate')">
						<template #icon>
							<TrashCanOutline :size="20" />
						</template>
						Delete
					</NcActionButton>
				</NcActions>
			</h2>
		</div>

		<div class="template-content">
			<div class="template-description">
				<h3>Description</h3>
				<span>{{ objectStore.getActiveObject('template').description }}</span>
			</div>

			<BTabs>
				<BTab>
					<template #title>
						Content <NcCounterBubble>{{ objectStore.getActiveObject('template').template ? 1 : 0 }}</NcCounterBubble>
					</template>
					<div class="template-preview">
						<div v-html="DOMPurify.sanitize(objectStore.getActiveObject('template').template)" />
					</div>
				</BTab>

				<BTab>
					<template #title>
						Relations <NcCounterBubble>{{ objectStore.getRelatedObjects('template').length }}</NcCounterBubble>
					</template>
					<ObjectList :objects="objectStore.getRelatedObjects('template')" />
				</BTab>

				<BTab>
					<template #title>
						Logging <NcCounterBubble>{{ objectStore.getAuditTrails('template')?.length || 0 }}</NcCounterBubble>
					</template>
					<AuditTable :logs="objectStore.getAuditTrails('template')" />
				</BTab>
			</BTabs>
		</div>
	</div>
</template>

<script>
import { BTabs, BTab } from 'bootstrap-vue'
import { NcLoadingIcon, NcActions, NcActionButton, NcGuestContent, NcRichText, NcCounterBubble } from '@nextcloud/vue'

// Custom components
import AuditTable from '../auditTrail/AuditTable.vue'
import ObjectList from '../../components/ObjectList.vue'

import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'

export default {
	name: 'TemplateDetails',
	components: {
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		NcCounterBubble,
		BTabs,
		BTab,
		// Custom components
		AuditTable,
		ObjectList,
		// Icons
		Pencil,
		TrashCanOutline,
		DotsHorizontal,
		NcGuestContent,
		NcRichText,
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

.detailGrid {
	display: grid;
	grid-gap: 24px;
	grid-template-columns: 1fr;
	margin-block-start: var(--zaa-margin-50);
	margin-block-end: var(--zaa-margin-50);
}

.gridContent {
	display: flex;
	gap: 25px;
}

.tabContainer {
	margin-top: 20px;
	padding: 0 20px;
}

#guest-content-vue {
	margin: 20px 5px !important;
}

.tab-content {
	padding: 20px 0;
}

.tab-title {
    display: flex;
    align-items: center;
}

/* Fix for Bootstrap Vue tabs alignment */
.nav-tabs .nav-link {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Add margin to counter bubble only when inside nav-item */
.nav-item .counter-bubble__counter {
    margin-left: 10px;
}
</style>
