<script setup>
import { navigationStore, objectStore } from '../../store/store.js'
import { RecycleScroller } from 'vue-virtual-scroller'
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					:value="objectStore.searchTerm"
					:show-trailing-button="objectStore.searchTerm !== ''"
					label="Search"
					class="searchField"
					trailing-button-icon="close"
					@input="objectStore.setSearchTerm($event.target.value)"
					@trailing-button-click="objectStore.clearSearch()">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="objectStore.fetchCollection('character')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="objectStore.clearActiveObject('character'); navigationStore.setModal('editCharacter')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Karakter toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="objectStore.getCollection('character').results?.length > 0 && !objectStore.isLoading('character')">
				<!-- Add Previous Page Button if available -->
				<div v-if="objectStore.hasPreviousPages('character')" class="pagination-info">
					<NcButton
						:disabled="objectStore.isLoading('character')"
						type="secondary"
						@click="objectStore.loadPrevious('character')">
						Load Previous
					</NcButton>
				</div>

				<RecycleScroller
					v-slot="{ item: character }"
					class="scroller"
					:items="objectStore.getCollection('character').results"
					:item-size="60"
					key-field="id">
					<NcListItem
						:key="character.id"
						:name="character?.name"
						:force-display-actions="true"
						:active="objectStore.getActiveObject('character')?.id === character?.id"
						:details="character.approved === 'approved' ? 'Approved': 'Not approved'"
						:counter-number="character?.skills?.length || 0"
						@click="handleCharacterSelect(character)">
						<template #icon>
							<BriefcaseAccountOutline :class="objectStore.getActiveObject('character')?.id === character?.id && 'selectedZaakIcon'"
								disable-menu
								:size="44" />
						</template>
						<template #subname>
							{{ character?.OCName || 'No player selected' }}
						</template>
						<template #actions>
							<NcActionButton @click="objectStore.setActiveObject('character', character); navigationStore.setModal('editCharacter')">
								<template #icon>
									<Pencil />
								</template>
								Bewerken
							</NcActionButton>
							<NcActionButton @click="objectStore.setActiveObject('character', character); navigationStore.setDialog('deleteCharacter')">
								<template #icon>
									<TrashCanOutline />
								</template>
								Verwijderen
							</NcActionButton>
						</template>
					</NcListItem>
				</RecycleScroller>

				<!-- Pagination info and load more button -->
				<div v-if="objectStore.hasMorePages('character')" class="pagination-info">
					<p>Showing {{ objectStore.getCollection('character').results?.length }} of {{ objectStore.getPagination('character').total }} characters</p>
					<div class="pagination-buttons">
						<NcButton
							:disabled="objectStore.isLoading('character')"
							type="secondary"
							@click="objectStore.loadMore('character')">
							Load More
						</NcButton>
					</div>
				</div>
			</div>

			<NcLoadingIcon v-if="objectStore.isLoading('character')"
				class="loadingIcon"
				:size="64"
				appearance="dark"
				name="Karakters aan het laden" />

			<div v-if="!objectStore.getCollection('character').results?.length && !objectStore.isLoading('character')">
				Er zijn nog geen karakters gemaakt.
			</div>
		</ul>
	</NcAppContentList>
</template>

<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon, NcButton } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'CharactersList',
	components: {
		// Components
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		NcButton,
		RecycleScroller,
		// Icons
		BriefcaseAccountOutline,
		Magnify,
		Refresh,
		Plus,
		Pencil,
		TrashCanOutline,
	},
	methods: {
		/**
		 * Handle character selection
		 * @param {object} character - The selected character object
		 */
		async handleCharacterSelect(character) {
			await objectStore.setActiveObject('character', character)
			navigationStore.setSelected('characters')
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

.pagination-info {
    text-align: center;
    padding: 20px;
    border-top: 1px solid var(--color-border);
}

.pagination-info p {
    margin-bottom: 10px;
    color: var(--color-text-maxcontrast);
}

.pagination-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.scroller {
    height: calc(100vh - 200px);
    overflow-y: auto;
}
</style>
