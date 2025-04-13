<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="eventsIndex">
		<NcEmptyContent v-if="!objectStore.getActiveObject('event') || navigationStore.selected != 'events'"
			icon="icon-calendar-dark"
			title="Gebeurtenissen">
			<template #action>
				<NcButton type="primary" @click="objectStore.clearActiveObject('event'); navigationStore.setModal('editEvent')">
					<template #icon>
						<Plus :size="20" />
					</template>
					Nieuwe gebeurtenis
				</NcButton>
			</template>
		</NcEmptyContent>

		<EventDetails v-if="objectStore.getActiveObject('event') && navigationStore.selected === 'events'" />
	</div>
</template>

<script>
import {
	NcButton,
	NcEmptyContent,
} from '@nextcloud/vue'

import Plus from 'vue-material-design-icons/Plus.vue'
import EventDetails from './EventDetails.vue'

export default {
	name: 'EventsIndex',
	components: {
		NcButton,
		NcEmptyContent,
		Plus,
		EventDetails,
	},
}
</script>

<style scoped>
.eventsIndex {
	height: 100%;
}
</style>
