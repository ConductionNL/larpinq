<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField class="searchField"
					:value="objectStore.getSearchTerm('condition')"
					label="Zoeken"
					trailing-button-icon="close"
					:show-trailing-button="objectStore.getSearchTerm('condition') !== ''"
					@update:value="(value) => objectStore.setSearchTerm('condition', value)"
					@trailing-button-click="objectStore.clearSearchTerm('condition')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton :disabled="objectStore.isLoading('condition')"
						@click="objectStore.fetchCollection('condition')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="openAddConditionModal">
						<template #icon>
							<Plus :size="20" />
						</template>
						Conditie toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="!objectStore.isLoading('condition')">
				<div v-if="objectStore.hasPreviousPages('condition')" class="pagination-info">
					<NcButton
						:disabled="objectStore.isLoading('condition')"
						type="secondary"
						@click="objectStore.loadPrevious('condition')">
						Vorige pagina
					</NcButton>
				</div>

				<RecycleScroller
					v-if="objectStore.getCollection('condition').results?.length"
					v-slot="{ item: condition }"
					class="scroller"
					:items="objectStore.getCollection('condition').results"
					:item-size="60"
					key-field="id">
					<NcListItem
						:key="condition.id"
						:name="condition.name"
						:details="condition.description || ''"
						:active="objectStore.getActiveObject('condition')?.id === condition.id"
						:force-display-actions="true"
						@click="toggleActive(condition)">
						<template #icon>
							<EmoticonSickOutline
								:class="objectStore.getActiveObject('condition')?.id === condition.id && 'selectedConditionIcon'"
								:size="44" />
						</template>
						<template #subname>
							{{ condition.type || 'Geen type' }}
						</template>
						<template #actions>
							<NcActionButton @click="onActionButtonClick(condition, 'edit')">
								<template #icon>
									<Pencil :size="20" />
								</template>
								Bewerken
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(condition, 'copyObject')">
								<template #icon>
									<ContentCopy :size="20" />
								</template>
								Kopiëren
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(condition, 'deleteObject')">
								<template #icon>
									<Delete :size="20" />
								</template>
								Verwijderen
							</NcActionButton>
						</template>
					</NcListItem>
				</RecycleScroller>

				<div v-if="objectStore.hasMorePages('condition')" class="pagination-info">
					<p>{{ objectStore.getCollection('condition').results?.length }} van {{ objectStore.getPagination('condition').total }} condities</p>
					<div class="pagination-buttons">
						<NcButton
							:disabled="objectStore.isLoading('condition')"
							type="secondary"
							@click="objectStore.loadMore('condition')">
							Meer laden
						</NcButton>
					</div>
				</div>
			</div>

			<NcLoadingIcon v-if="objectStore.isLoading('condition')"
				:size="64"
				class="loadingIcon"
				appearance="dark"
				name="Condities aan het laden" />

			<div v-if="!objectStore.getCollection('condition').results?.length && !objectStore.isLoading('condition')" class="emptyListHeader">
				Er zijn nog geen condities gemaakt.
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
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

/**
 * ConditionsList Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'ConditionsList',
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
		EmoticonSickOutline,
		Plus,
		Pencil,
		Delete,
		Refresh,
		ContentCopy,
	},
	methods: {
		toggleActive(condition) {
			objectStore.getActiveObject('condition')?.id === condition?.id 
				? objectStore.clearActiveObject('condition') 
				: objectStore.setActiveObject('condition', condition)
		},
		openAddConditionModal() {
			navigationStore.setModal('editCondition')
			objectStore.clearActiveObject('condition')
		},
		onActionButtonClick(condition, action) {
			objectStore.setActiveObject('condition', condition)
			switch (action) {
			case 'edit':
				navigationStore.setModal('editCondition')
				break
			case 'copyObject':
			case 'deleteObject':
				navigationStore.setDialog(action, { objectType: 'condition', dialogTitle: 'Conditie' })
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

.selectedConditionIcon>svg {
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
