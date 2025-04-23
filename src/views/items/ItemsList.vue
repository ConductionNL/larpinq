<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField class="searchField"
					:value="objectStore.getSearchTerm('item')"
					label="Zoeken"
					trailing-button-icon="close"
					:show-trailing-button="objectStore.getSearchTerm('item') !== ''"
					@update:value="(value) => objectStore.setSearchTerm('item', value)"
					@trailing-button-click="objectStore.clearSearchTerm('item')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton :disabled="objectStore.isLoading('item')"
						@click="objectStore.fetchCollection('item')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="openAddItemModal">
						<template #icon>
							<Plus :size="20" />
						</template>
						Item toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="!objectStore.isLoading('item')">
				<div v-if="objectStore.hasPreviousPages('item')" class="pagination-info">
					<NcButton
						:disabled="objectStore.isLoading('item')"
						type="secondary"
						@click="objectStore.loadPrevious('item')">
						Vorige pagina
					</NcButton>
				</div>

				<RecycleScroller
					v-if="objectStore.getCollection('item').results?.length"
					v-slot="{ item }"
					class="scroller"
					:items="objectStore.getCollection('item').results"
					:item-size="60"
					key-field="id">
					<NcListItem
						:key="item.id"
						:name="item.name"
						:details="item.description || ''"
						:active="objectStore.getActiveObject('item')?.id === item.id"
						:force-display-actions="true"
						@click="toggleActive(item)">
						<template #icon>
							<Sword 
								:class="objectStore.getActiveObject('item')?.id === item.id && 'selectedItemIcon'"
								:size="44" />
						</template>
						<template #actions>
							<NcActionButton @click="onActionButtonClick(item, 'edit')">
								<template #icon>
									<Pencil :size="20" />
								</template>
								Bewerken
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(item, 'copyObject')">
								<template #icon>
									<ContentCopy :size="20" />
								</template>
								Kopiëren
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(item, 'deleteObject')">
								<template #icon>
									<Delete :size="20" />
								</template>
								Verwijderen
							</NcActionButton>
						</template>
					</NcListItem>
				</RecycleScroller>

				<div v-if="objectStore.hasMorePages('item')" class="pagination-info">
					<p>{{ objectStore.getCollection('item').results?.length }} van {{ objectStore.getPagination('item').total }} items</p>
					<div class="pagination-buttons">
						<NcButton
							:disabled="objectStore.isLoading('item')"
							type="secondary"
							@click="objectStore.loadMore('item')">
							Meer laden
						</NcButton>
					</div>
				</div>
			</div>

			<NcLoadingIcon v-if="objectStore.isLoading('item')"
				:size="64"
				class="loadingIcon"
				appearance="dark"
				name="Items aan het laden" />

			<div v-if="!objectStore.getCollection('item').results?.length && !objectStore.isLoading('item')" class="emptyListHeader">
				Er zijn nog geen items gemaakt.
			</div>
		</ul>
	</NcAppContentList>
</template>

<script>
import { NcListItem, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon, NcActions, NcButton } from '@nextcloud/vue'
import { RecycleScroller } from 'vue-virtual-scroller'
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

/**
 * ItemsList Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'ItemsList',
	components: {
		NcListItem,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		NcActions,
		NcButton,
		RecycleScroller,
		// Icons
		Magnify,
		Sword,
		Plus,
		Pencil,
		Delete,
		Refresh,
		ContentCopy,
	},
	methods: {
		toggleActive(item) {
			objectStore.getActiveObject('item')?.id === item?.id 
				? objectStore.clearActiveObject('item') 
				: objectStore.setActiveObject('item', item)
		},
		openAddItemModal() {
			navigationStore.setModal('editItem')
			objectStore.clearActiveObject('item')
		},
		onActionButtonClick(item, action) {
			objectStore.setActiveObject('item', item)
			switch (action) {
			case 'edit':
				navigationStore.setModal('editItem')
				break
			case 'copyObject':
			case 'deleteObject':
				navigationStore.setDialog(action, { objectType: 'item', dialogTitle: 'Item' })
				break
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

.selectedItemIcon>svg {
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

.emptyListHeader {
	text-align: center;
	padding: 20px;
	color: var(--color-text-maxcontrast);
}
</style>
