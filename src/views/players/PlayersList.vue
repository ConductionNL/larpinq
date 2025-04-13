<script setup>
import { objectStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					:value="objectStore.getSearchTerm('player')"
					:show-trailing-button="objectStore.getSearchTerm('player') !== ''"
					label="Search"
					class="searchField"
					trailing-button-icon="close"
					@input="objectStore.setSearchTerm('player', $event.target.value)"
					@trailing-button-click="objectStore.clearSearch('player')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="objectStore.fetchCollection('player')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="objectStore.clearActiveObject('player'); navigationStore.setModal('editPlayer')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Speler toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="objectStore.getCollection('player').results?.length > 0 && !objectStore.isLoading('player')">
				<NcListItem v-for="(player, i) in objectStore.getCollection('player').results"
					:key="`${player}${i}`"
					:name="player?.name"
					:active="objectStore.getActiveObject('player')?.id === player?.id"
					:force-display-actions="true"
					@click="handlePlayerSelect(player)">
					<template #icon>
						<BriefcaseAccountOutline :class="objectStore.getActiveObject('player')?.id === player.id && 'selectedZaakIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ player?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="objectStore.setActiveObject('player', player); navigationStore.setModal('editPlayer')">
							<template #icon>
								<Pencil />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="objectStore.setActiveObject('player', player); navigationStore.setDialog('deletePlayer')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="objectStore.isLoading('player')"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Spelers aan het laden" />

		<div v-if="objectStore.getCollection('player').results?.length === 0 && !objectStore.isLoading('player')">
			Er zijn nog geen spelers gedefinieerd.
		</div>
	</NcAppContentList>
</template>
<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'

export default {
	name: 'PlayersList',
	components: {
		// Components
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		// Icons
		BriefcaseAccountOutline,
		Magnify,
		Plus,
		Pencil,
		TrashCanOutline,
		Refresh,
	},
	methods: {
		/**
		 * Handle player selection and fetch related data
		 * @param {object} player - The selected player object
		 */
		async handlePlayerSelect(player) {
			// Set the selected player in the store
			objectStore.setActiveObject('player', player)
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

.selectedZaakIcon>svg {
    fill: white;
}

.loadingIcon {
    margin-block-start: var(--OC-margin-20);
}
</style>
