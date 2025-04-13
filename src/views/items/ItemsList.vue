<script setup>
import { objectStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<div class="itemsList">
		<div class="itemsHeader">
			<NcTextField
				:value="searchStore.getSearchTerm('item')"
				:show-trailing-button="searchStore.getSearchTerm('item') !== ''"
				type="search"
				label="Zoeken"
				@input="searchStore.setSearchTerm('item', $event.target.value)"
				@trailing-button-click="searchStore.clearSearchTerm('item')">
				<template #trailing-button-icon>
					<Close :size="20" />
				</template>
			</NcTextField>

			<div class="itemsActions">
				<NcActions>
					<NcActionButton @click="objectStore.refreshObjectList('item')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Vernieuwen
					</NcActionButton>
					<NcActionButton @click="objectStore.clearActiveObject('item'); navigationStore.setModal('editItem')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Nieuw item
					</NcActionButton>
				</NcActions>
			</div>
		</div>

		<div v-if="objectStore.getObjectList('item')?.length > 0 && !objectStore.isLoading('item')" class="itemItems">
			<NcListItem v-for="item in objectStore.getObjectList('item')"
				:key="item.id"
				:title="item.name"
				:active="objectStore.getActiveObject('item')?.id === item.id"
				@click="selectItem(item)">
				<template #icon>
					<Sword :class="objectStore.getActiveObject('item')?.id === item.id && 'selectedItemIcon'" :size="20" />
				</template>
				<template #actions>
					<NcActions>
						<NcActionButton @click.stop="objectStore.setActiveObject('item', item); navigationStore.setModal('editItem')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click.stop="objectStore.setActiveObject('item', item); navigationStore.setDialog('deleteItem')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</template>
			</NcListItem>
		</div>

		<div v-if="objectStore.isLoading('item')" class="itemsLoading">
			<NcLoadingIcon :size="50" />
		</div>

		<div v-if="objectStore.getObjectList('item')?.length === 0 && !objectStore.isLoading('item')" class="itemsEmpty">
			<NcEmptyContent
				icon="icon-category-customization"
				title="Geen items gevonden">
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('item'); navigationStore.setModal('editItem')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Nieuw item
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

import Close from 'vue-material-design-icons/Close.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'ItemsList',
	components: {
		NcTextField,
		NcActions,
		NcActionButton,
		NcListItem,
		NcLoadingIcon,
		NcEmptyContent,
		NcButton,
		Close,
		Pencil,
		Plus,
		Refresh,
		Sword,
		TrashCanOutline,
	},
	methods: {
		selectItem(item) {
			objectStore.setActiveObject('item', item)
			navigationStore.setSelected('items')
		},
	},
}
</script>

<style scoped>
.itemsList {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	padding: 1rem;
}

.itemsHeader {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.itemsActions {
	display: flex;
	gap: 1rem;
}

.itemItems {
	display: flex;
	flex-direction: column;
}

.itemsLoading {
	display: flex;
	justify-content: center;
	align-items: center;
	padding: 2rem;
}

.itemsEmpty {
	display: flex;
	justify-content: center;
	align-items: center;
	padding: 2rem;
}

.selectedItemIcon {
	color: var(--color-primary);
}
</style>
