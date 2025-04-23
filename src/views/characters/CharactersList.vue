<script setup>
import { navigationStore, objectStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField class="searchField"
					:value="objectStore.getSearchTerm('character')"
					label="Zoeken"
					trailing-button-icon="close"
					:show-trailing-button="objectStore.getSearchTerm('character') !== ''"
					@update:value="(value) => objectStore.setSearchTerm('character', value)"
					@trailing-button-click="objectStore.clearSearchTerm('character')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton :disabled="objectStore.isLoading('character')"
						@click="objectStore.fetchCollection('character')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="openAddCharacterModal">
						<template #icon>
							<Plus :size="20" />
						</template>
						Karakter toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="!objectStore.isLoading('character')">
				<div v-if="objectStore.hasPreviousPages('character')" class="pagination-info">
					<NcButton
						:disabled="objectStore.isLoading('character')"
						type="secondary"
						@click="objectStore.loadPrevious('character')">
						Vorige pagina
					</NcButton>
				</div>

				<RecycleScroller
					v-if="objectStore.getCollection('character').results?.length"
					v-slot="{ item: character }"
					class="scroller"
					:items="objectStore.getCollection('character').results"
					:item-size="60"
					key-field="id">
					<NcListItem
						:key="character.id"
						:name="character.name"
						:details="character.approved === 'approved' ? 'Goedgekeurd' : 'Niet goedgekeurd'"
						:active="objectStore.getActiveObject('character')?.id === character.id"
						:force-display-actions="true"
						:counter-number="character.skills?.length || 0"
						@click="toggleActive(character)">
						<template #icon>
							<BriefcaseAccountOutline 
								:class="objectStore.getActiveObject('character')?.id === character.id && 'selectedZaakIcon'"
								:size="44" />
						</template>
						<template #subname>
							{{ character.OCName || 'Geen speler geselecteerd' }}
						</template>
						<template #actions>
							<NcActionButton @click="onActionButtonClick(character, 'edit')">
								<template #icon>
									<Pencil :size="20" />
								</template>
								Bewerken
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(character, 'copyObject')">
								<template #icon>
									<ContentCopy :size="20" />
								</template>
								Kopiëren
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(character, 'deleteObject')">
								<template #icon>
									<Delete :size="20" />
								</template>
								Verwijderen
							</NcActionButton>
						</template>
					</NcListItem>
				</RecycleScroller>

				<div v-if="objectStore.hasMorePages('character')" class="pagination-info">
					<p>{{ objectStore.getCollection('character').results?.length }} van {{ objectStore.getPagination('character').total }} karakters</p>
					<div class="pagination-buttons">
						<NcButton
							:disabled="objectStore.isLoading('character')"
							type="secondary"
							@click="objectStore.loadMore('character')">
							Meer laden
						</NcButton>
					</div>
				</div>
			</div>

			<NcLoadingIcon v-if="objectStore.isLoading('character')"
				:size="64"
				class="loadingIcon"
				appearance="dark"
				name="Karakters aan het laden" />

			<div v-if="!objectStore.getCollection('character').results?.length && !objectStore.isLoading('character')" class="emptyListHeader">
				Er zijn nog geen karakters gemaakt.
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
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

/**
 * CharactersList Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'CharactersList',
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
		BriefcaseAccountOutline,
		Plus,
		Pencil,
		Delete,
		Refresh,
		ContentCopy,
	},
	methods: {
		toggleActive(character) {
			objectStore.getActiveObject('character')?.id === character?.id 
				? objectStore.clearActiveObject('character') 
				: objectStore.setActiveObject('character', character)
		},
		openAddCharacterModal() {
			navigationStore.setModal('editCharacter')
			objectStore.clearActiveObject('character')
		},
		onActionButtonClick(character, action) {
			objectStore.setActiveObject('character', character)
			switch (action) {
			case 'edit':
				navigationStore.setModal('editCharacter')
				break
			case 'copyObject':
			case 'deleteObject':
				navigationStore.setDialog(action, { objectType: 'character', dialogTitle: 'Karakter' })
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

.selectedZaakIcon>svg {
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
