<script setup>
import { characterStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					:value.sync="searchStore.search"
					:show-trailing-button="searchStore.search !== ''"
					label="Search"
					class="searchField"
					trailing-button-icon="close"
					@trailing-button-click="searchStore.setSearch('')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="characterStore.refreshCharacterList()">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="characterStore.setCharacterItem(null); navigationStore.setModal('editCharacter')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Karakter toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="characterStore.characterList && characterStore.characterList.length > 0">
				<NcListItem v-for="(character, i) in characterStore.characterList"
					:key="`${character}${i}`"
					:name="character?.name"
					:force-display-actions="true"
					:active="characterStore.characterItem?.id === character?.id"
					:details="character.approved === 'approved' ? 'Approved': 'Not approved'"
					:counter-number="character?.skills?.length || 0"
					@click="handleCharacterSelect(character)">
					<template #icon>
						<BriefcaseAccountOutline :class="characterStore.characterItem?.id === character?.id && 'selectedZaakIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ character?.ocName?.name || 'No player selected' }}
					</template>
					<template #actions>
						<NcActionButton @click="characterStore.setCharacterItem(character); navigationStore.setModal('editCharacter')">
							<template #icon>
								<Pencil />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="characterStore.setCharacterItem(character); navigationStore.setDialog('deleteCharacter')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="!characterStore.characterList"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Karakters aan het laden" />

		<div v-if="characterStore.characterList.length === 0">
			Er zijn nog geen karakters gedefinieerd.
		</div>
	</NcAppContentList>
</template>

<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'

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
		// Icons
		BriefcaseAccountOutline,
		Magnify,
		Refresh,
		Plus,
		Pencil,
		TrashCanOutline,
	},
	mounted() {
		characterStore.refreshCharacterList()
	},
	methods: {
		/**
		 * Handle character selection
		 * @param {object} character - The selected character object
		 */
		async handleCharacterSelect(character) {
			// Set the selected character in the store
			characterStore.setCharacterItem(character)

			try {
				// Fetch audit trails and relations for the selected character
				await Promise.all([
					characterStore.getRelations(character.id),
					characterStore.getAuditTrails(character.id),
				])
			} catch (error) {
				console.error('Error fetching character data:', error)
			}
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
