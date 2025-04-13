<script setup>
import { objectStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<div class="eventsList">
		<div class="eventsHeader">
			<NcTextField
				:value="searchStore.getSearchTerm('event')"
				:show-trailing-button="searchStore.getSearchTerm('event') !== ''"
				type="search"
				label="Zoeken"
				@input="searchStore.setSearchTerm('event', $event.target.value)"
				@trailing-button-click="searchStore.clearSearchTerm('event')">
				<template #trailing-button-icon>
					<Close :size="20" />
				</template>
			</NcTextField>

			<div class="eventsActions">
				<NcActions>
					<NcActionButton @click="objectStore.refreshObjectList('event')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Vernieuwen
					</NcActionButton>
					<NcActionButton @click="objectStore.clearActiveObject('event'); navigationStore.setModal('editEvent')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Nieuwe gebeurtenis
					</NcActionButton>
				</NcActions>
			</div>
		</div>

		<div v-if="objectStore.getObjectList('event')?.length > 0 && !objectStore.isLoading('event')" class="eventItems">
			<NcListItem v-for="event in objectStore.getObjectList('event')"
				:key="event.id"
				:title="event.name"
				:active="objectStore.getActiveObject('event')?.id === event.id"
				@click="selectEvent(event)">
				<template #icon>
					<CalendarMonthOutline :class="objectStore.getActiveObject('event')?.id === event.id && 'selectedEventIcon'" :size="20" />
				</template>
				<template #actions>
					<NcActions>
						<NcActionButton @click.stop="objectStore.setActiveObject('event', event); navigationStore.setModal('editEvent')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click.stop="objectStore.setActiveObject('event', event); navigationStore.setDialog('deleteEvent')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</template>
			</NcListItem>
		</div>

		<div v-if="objectStore.isLoading('event')" class="eventsLoading">
			<NcLoadingIcon :size="50" />
		</div>

		<div v-if="objectStore.getObjectList('event')?.length === 0 && !objectStore.isLoading('event')" class="eventsEmpty">
			<NcEmptyContent
				icon="icon-calendar-dark"
				title="Geen gebeurtenissen gevonden">
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('event'); navigationStore.setModal('editEvent')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Nieuwe gebeurtenis
					</NcButton>
				</template>
			</NcEmptyContent>
		</div>
	</div>
</template>

<script>
import {
	NcTextField,
	NcActions,
	NcActionButton,
	NcListItem,
	NcLoadingIcon,
	NcEmptyContent,
	NcButton,
} from '@nextcloud/vue'

import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'EventsList',
	components: {
		NcTextField,
		NcActions,
		NcActionButton,
		NcListItem,
		NcLoadingIcon,
		NcEmptyContent,
		NcButton,
		CalendarMonthOutline,
		Close,
		Pencil,
		Plus,
		Refresh,
		TrashCanOutline,
	},
	methods: {
		selectEvent(event) {
			objectStore.setActiveObject('event', event)
			navigationStore.setSelected('events')
		},
	},
}
</script>

<style scoped>
.eventsList {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	padding: 1rem;
}

.eventsHeader {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.eventsActions {
	display: flex;
	gap: 1rem;
}

.eventItems {
	display: flex;
	flex-direction: column;
}

.eventsLoading {
	display: flex;
	justify-content: center;
	align-items: center;
	padding: 2rem;
}

.eventsEmpty {
	display: flex;
	justify-content: center;
	align-items: center;
	padding: 2rem;
}

.selectedEventIcon {
	color: var(--color-primary);
}
</style>
