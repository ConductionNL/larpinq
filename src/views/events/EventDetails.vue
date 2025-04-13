<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="eventDetails">
		<div class="eventHeader">
			<h2>{{ objectStore.getActiveObject('event')?.name }}</h2>
			<div class="eventActions">
				<NcButton @click="objectStore.setActiveObject('event', objectStore.getActiveObject('event')); navigationStore.setModal('editEvent')">
					<template #icon>
						<Pencil :size="20" />
					</template>
					Bewerken
				</NcButton>
				<NcButton type="error" @click="objectStore.setActiveObject('event', objectStore.getActiveObject('event')); navigationStore.setDialog('deleteEvent')">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
					Verwijderen
				</NcButton>
			</div>
		</div>

		<div class="eventContent">
			<div class="eventInfo">
				<div class="eventDates">
					<div class="eventDate">
						<h3>Start datum</h3>
						<span>{{ new Date(objectStore.getActiveObject('event')?.startDate).toLocaleString() }}</span>
					</div>
					<div class="eventDate">
						<h3>Eind datum</h3>
						<span>{{ new Date(objectStore.getActiveObject('event')?.endDate).toLocaleString() }}</span>
					</div>
				</div>

				<div class="eventDescription">
					<h3>Beschrijving</h3>
					<span>{{ objectStore.getActiveObject('event')?.description }}</span>
				</div>

				<div class="eventLocation">
					<h3>Locatie</h3>
					<span>{{ objectStore.getActiveObject('event')?.location }}</span>
				</div>
			</div>

			<div class="eventRelations">
				<h3>Karakters <NcCounterBubble>{{ objectStore.getRelations('event')?.length || 0 }}</NcCounterBubble></h3>
				<ObjectList :objects="objectStore.getRelations('event')" />
			</div>

			<div class="eventAudit">
				<h3>Logging <NcCounterBubble>{{ objectStore.getAuditTrails('event')?.length || 0 }}</NcCounterBubble></h3>
				<AuditTable :logs="objectStore.getAuditTrails('event')" />
			</div>
		</div>
	</div>
</template>

<script>
import {
	NcButton,
	NcCounterBubble,
} from '@nextcloud/vue'

import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

import ObjectList from '../../components/ObjectList.vue'
import AuditTable from '../auditTrail/AuditTable.vue'

export default {
	name: 'EventDetails',
	components: {
		NcButton,
		NcCounterBubble,
		Pencil,
		TrashCanOutline,
		ObjectList,
		AuditTable,
	},
}
</script>

<style scoped>
.eventDetails {
	display: flex;
	flex-direction: column;
	gap: 2rem;
	padding: 2rem;
}

.eventHeader {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.eventActions {
	display: flex;
	gap: 1rem;
}

.eventContent {
	display: flex;
	flex-direction: column;
	gap: 2rem;
}

.eventInfo {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.eventDates {
	display: flex;
	gap: 2rem;
}

.eventDate {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.eventDescription,
.eventLocation {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.eventRelations,
.eventAudit {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}
</style>
