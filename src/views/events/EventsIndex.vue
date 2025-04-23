<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<EventsList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!objectStore.getActiveObject('event') || navigationStore.selected != 'events'"
				class="detailContainer"
				name="Geen Gebeurtenis"
				description="Nog geen gebeurtenis geselecteerd">
				<template #icon>
					<CalendarMonthOutline :size="20" />
				</template>
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('event'); navigationStore.setModal('editEvent')">
						Gebeurtenis aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<EventDetails v-if="objectStore.getActiveObject('event') && navigationStore.selected === 'events'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import EventsList from './EventsList.vue'
import EventDetails from './EventDetails.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'

/**
 * EventsIndex Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'EventsIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		EventsList,
		EventDetails,
		CalendarMonthOutline,
	},
}
</script>

<style scoped>
.eventsIndex {
	height: 100%;
}
</style>
