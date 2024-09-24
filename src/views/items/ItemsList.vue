<script setup>
import { characterStore, itemStore, navigationStore, searchStore } from '../../store/store.js'
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
					<NcActionButton @click="itemStore.refreshItemList()">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="itemStore.setItemItem(null); navigationStore.setModal('editItem')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Item toevoegen
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="itemStore.itemList && itemStore.itemList.length > 0">
				<NcListItem v-for="(item, i) in itemStore.itemList"
					:key="`${item}${i}`"
					:name="item?.name"
					:active="itemStore.itemItem?.id === item?.id"
					:details="item.unique ? 'unique' : 'non-unique'"
					:counter-number="filterCharacters(item.id)?.length || 0"
					:force-display-actions="true"
					@click="itemStore.setItemItem(item)">
					<template #icon>
						<Sword :class="itemStore.itemItem?.id === item?.id && 'selectedItemIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ item?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="itemStore.setItemItem(item); navigationStore.setModal('editItem')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="itemStore.setItemItem(item); navigationStore.setDialog('deleteItem')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="!itemStore.itemList"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="berichten aan het laden" />

		<div v-if="itemStore.itemList.length === 0">
			Er zijn nog geen voorwerpen gedefinieerd.
		</div>
	</NcAppContentList>
</template>
<script>
//  Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'ItemsList',
	components: {
		// Components
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		// Icons
		Magnify,
		Refresh,
		Plus,
		Sword,
		Pencil,
		TrashCanOutline,
	},
	data() {
		return {
			charactersLoading: false,
		}
	},
	mounted() {
		itemStore.refreshItemList()
		this.fetchCharacters()
	},
	methods: {
		filterCharacters(id) {
			return characterStore.characterList.filter((character) => {
				return character.items.map(String).includes(id.toString())
			})
		},
		fetchCharacters() {
			this.charactersLoading = true
			characterStore.refreshCharacterList()
				.then(() => {
					this.charactersLoading = false
				})
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
