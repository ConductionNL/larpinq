<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="conditionDetails">
		<div class="conditionHeader">
			<h2>{{ objectStore.getActiveObject('condition')?.name }}</h2>
			<div class="conditionActions">
				<NcButton @click="objectStore.setActiveObject('condition', objectStore.getActiveObject('condition')); navigationStore.setModal('editCondition')">
					<template #icon>
						<Pencil :size="20" />
					</template>
					Bewerken
				</NcButton>
				<NcButton type="error" @click="objectStore.setActiveObject('condition', objectStore.getActiveObject('condition')); navigationStore.setDialog('deleteCondition')">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
					Verwijderen
				</NcButton>
			</div>
		</div>

		<div class="conditionContent">
			<div class="conditionInfo">
				<div class="conditionDescription">
					<h3>Beschrijving</h3>
					<span>{{ objectStore.getActiveObject('condition')?.description }}</span>
				</div>
			</div>

			<div class="conditionEffects">
				<h3>Effecten <NcCounterBubble>{{ objectStore.getActiveObject('condition')?.effects?.length || 0 }}</NcCounterBubble></h3>
				<EffectList :effects="objectStore.getActiveObject('condition')?.effects" />
			</div>

			<div class="conditionRelations">
				<h3>Karakters <NcCounterBubble>{{ objectStore.getRelations('condition')?.length || 0 }}</NcCounterBubble></h3>
				<ObjectList :objects="objectStore.getRelations('condition')" />
			</div>

			<div class="conditionAudit">
				<h3>Logging <NcCounterBubble>{{ objectStore.getAuditTrails('condition')?.length || 0 }}</NcCounterBubble></h3>
				<AuditTable :logs="objectStore.getAuditTrails('condition')" />
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
import FileDocumentPlusOutline from 'vue-material-design-icons/FileDocumentPlusOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'

export default {
	name: 'ConditionDetails',
	components: {
		AuditTable,
		ObjectList,
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		NcListItem,
		NcCounterBubble,
		BTabs,
		BTab,
		// Icons
		DotsHorizontal,
		Pencil,
		FileDocumentPlusOutline,
		TrashCanOutline,
		BriefcaseAccountOutline,
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

</style>
