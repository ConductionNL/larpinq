<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField class="searchField"
					:value="objectStore.getSearchTerm('template')"
					label="Zoeken"
					trailing-button-icon="close"
					:show-trailing-button="objectStore.getSearchTerm('template') !== ''"
					@update:value="(value) => objectStore.setSearchTerm('template', value)"
					@trailing-button-click="objectStore.clearSearchTerm('template')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton :disabled="objectStore.isLoading('template')"
						@click="objectStore.fetchCollection('template')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="openAddTemplateModal">
						<template #icon>
							<Plus :size="20" />
						</template>
						Template toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="!objectStore.isLoading('template')">
				<div v-if="objectStore.hasPreviousPages('template')" class="pagination-info">
					<NcButton
						:disabled="objectStore.isLoading('template')"
						type="secondary"
						@click="objectStore.loadPrevious('template')">
						Vorige pagina
					</NcButton>
				</div>

				<RecycleScroller
					v-if="objectStore.getCollection('template').results?.length"
					v-slot="{ item: template }"
					class="scroller"
					:items="objectStore.getCollection('template').results"
					:item-size="60"
					key-field="id">
					<NcListItem
						:key="template.id"
						:name="template.name"
						:details="template.description || ''"
						:active="objectStore.getActiveObject('template')?.id === template.id"
						:force-display-actions="true"
						@click="toggleActive(template)">
						<template #icon>
							<FileDocumentOutline
								:class="objectStore.getActiveObject('template')?.id === template.id && 'selectedTemplateIcon'"
								:size="44" />
						</template>
						<template #subname>
							{{ template.type || 'Geen type' }}
						</template>
						<template #actions>
							<NcActionButton @click="onActionButtonClick(template, 'edit')">
								<template #icon>
									<Pencil :size="20" />
								</template>
								Bewerken
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(template, 'copyObject')">
								<template #icon>
									<ContentCopy :size="20" />
								</template>
								Kopiëren
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(template, 'deleteObject')">
								<template #icon>
									<Delete :size="20" />
								</template>
								Verwijderen
							</NcActionButton>
						</template>
					</NcListItem>
				</RecycleScroller>

				<div v-if="objectStore.hasMorePages('template')" class="pagination-info">
					<p>{{ objectStore.getCollection('template').results?.length }} van {{ objectStore.getPagination('template').total }} templates</p>
					<div class="pagination-buttons">
						<NcButton
							:disabled="objectStore.isLoading('template')"
							type="secondary"
							@click="objectStore.loadMore('template')">
							Meer laden
						</NcButton>
					</div>
				</div>
			</div>

			<NcLoadingIcon v-if="objectStore.isLoading('template')"
				:size="64"
				class="loadingIcon"
				appearance="dark"
				name="Templates aan het laden" />

			<div v-if="!objectStore.getCollection('template').results?.length && !objectStore.isLoading('template')" class="emptyListHeader">
				Er zijn nog geen templates gemaakt.
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
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

/**
 * TemplatesList Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'TemplatesList',
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
		FileDocumentOutline,
		Plus,
		Pencil,
		Delete,
		Refresh,
		ContentCopy,
	},
	methods: {
		toggleActive(template) {
			objectStore.getActiveObject('template')?.id === template?.id 
				? objectStore.clearActiveObject('template') 
				: objectStore.setActiveObject('template', template)
		},
		openAddTemplateModal() {
			navigationStore.setModal('editTemplate')
			objectStore.clearActiveObject('template')
		},
		onActionButtonClick(template, action) {
			objectStore.setActiveObject('template', template)
			switch (action) {
			case 'edit':
				navigationStore.setModal('editTemplate')
				break
			case 'copyObject':
			case 'deleteObject':
				navigationStore.setDialog(action, { objectType: 'template', dialogTitle: 'Template' })
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

.selectedTemplateIcon>svg {
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
