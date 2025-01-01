<script setup>
import { characterStore, eventStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					:value="eventStore.searchTerm"
					:show-trailing-button="eventStore.searchTerm !== ''"
					label="Search"
					class="searchField"
					trailing-button-icon="close"
					@input="eventStore.setSearchTerm($event.target.value)"
					@trailing-button-click="eventStore.clearSearch()">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="eventStore.refreshEventList()">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="eventStore.setEventItem(null); navigationStore.setModal('editEvent')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Evenement toevoegen
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="eventStore.eventList && eventStore.eventList.length > 0 && !eventStore.isLoadingEventList">
				<NcListItem v-for="(event, i) in eventStore.eventList"
					:key="`${event}${i}`"
					:name="event?.name"
					:force-display-actions="true"
					:active="eventStore.eventItem?.id === event?.id"
					:details="event.startDate"
					@click="handleEventSelect(event)">
					<template #icon>
						<CalendarMonthOutline :class="eventStore.eventItem?.id === event.id && 'selectedZaakIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ event?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="eventStore.setEventItem(event); navigationStore.setModal('editEvent')">
							<template #icon>
								<Pencil />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="eventStore.setEventItem(event), navigationStore.setDialog('deleteEvent')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="eventStore.isLoadingEventList"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Evenementen aan het laden" />

		<div v-if="eventStore.eventList.length === 0 && !eventStore.isLoadingEventList">
			Er zijn nog geen evenementen gedefinieerd.
		</div>
	</NcAppContentList>
</template>
<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'EventsList',
	components: {
		// Components
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		// Icons
		CalendarMonthOutline,
		Magnify,
		Plus,
		Pencil,
		TrashCanOutline,
	},
	mounted() {
		eventStore.refreshEventList()
	},
	methods: {
		/**
		 * Handle event selection and fetch related data
		 * @param {Object} event - The selected event object
		 */
		async handleEventSelect(event) {
			// Set the selected event in the store
			eventStore.setEventItem(event)
		},
	},
}
</script>

<style>
.listHeader {
    position: sticky;
    top: 0;
    z-index: 1000;
    background-color: var(--color-main-background);
    border-bottom: 1px solid var(--color-border);
}

.searchField {
    padding-inline-start: 65px;
    padding-inline-end: 20px;
    margin-block-end: 6px;
}

.selectedIcon>svg {
    fill: white;
}

.loadingIcon {
    margin-block-start: var(--OC-margin-20);
}
</style>
