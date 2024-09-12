<script setup>
import { playerStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField class="searchField"
					disabled
					:value.sync="searchStore.search"
					label="Search"
					trailing-button-icon="close"
					:show-trailing-button="searchStore.search !== ''"
					@trailing-button-click="searchStore.setSearch('')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="playerStore.refreshPlayerList()">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="playerStore.setPlayerItem(null); navigationStore.setModal('editPlayer')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Speler toevoegen
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="playerStore.playerList && playerStore.playerList.length > 0">
				<NcListItem v-for="(player, i) in playerStore.playerList"
					:key="`${player}${i}`"
					:name="player?.name"
					:active="playerStore.playerItem?.id === player?.id"
					:details="'1h'"
					:counter-number="44"
					:force-display-actions="true"
					@click="playerStore.setPlayerItem(player)">
					<template #icon>
						<BriefcaseAccountOutline :class="playerStore.playerItem?.id === player.id && 'selectedZaakIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ player?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="playerStore.setPlayerItem(player); navigationStore.setModal('editPlayer')">
							<template #icon>
								<Plus />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="playerStore.setPlayerItem(player), navigationStore.setDialog('deletePlayer')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="!playerStore.playerList "
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Besluiten aan het laden" />

		<div v-if="playerStore.playerList.length === 0">
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
	mounted() {
		playerStore.refreshPlayerList()
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
    margin-block-start: var(--zaa-margin-20);
}
</style>
