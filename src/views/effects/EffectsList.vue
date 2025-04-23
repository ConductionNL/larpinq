<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField class="searchField"
					:value="objectStore.getSearchTerm('effect')"
					label="Zoeken"
					trailing-button-icon="close"
					:show-trailing-button="objectStore.getSearchTerm('effect') !== ''"
					@update:value="(value) => objectStore.setSearchTerm('effect', value)"
					@trailing-button-click="objectStore.clearSearchTerm('effect')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton :disabled="objectStore.isLoading('effect')"
						@click="objectStore.fetchCollection('effect')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="openAddEffectModal">
						<template #icon>
							<Plus :size="20" />
						</template>
						Effect toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="!objectStore.isLoading('effect')">
				<div v-if="objectStore.hasPreviousPages('effect')" class="pagination-info">
					<NcButton
						:disabled="objectStore.isLoading('effect')"
						type="secondary"
						@click="objectStore.loadPrevious('effect')">
						Vorige pagina
					</NcButton>
				</div>

				<RecycleScroller
					v-if="objectStore.getCollection('effect').results?.length"
					v-slot="{ item: effect }"
					class="scroller"
					:items="objectStore.getCollection('effect').results"
					:item-size="60"
					key-field="id">
					<NcListItem
						:key="effect.id"
						:name="effect.name"
						:details="effect.description || ''"
						:active="objectStore.getActiveObject('effect')?.id === effect.id"
						:force-display-actions="true"
						@click="toggleActive(effect)">
						<template #icon>
							<MagicStaff 
								:class="objectStore.getActiveObject('effect')?.id === effect.id && 'selectedEffectIcon'"
								:size="44" />
						</template>
						<template #subname>
							{{ effect.type || 'Geen type' }}
						</template>
						<template #actions>
							<NcActionButton @click="onActionButtonClick(effect, 'edit')">
								<template #icon>
									<Pencil :size="20" />
								</template>
								Bewerken
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(effect, 'copyObject')">
								<template #icon>
									<ContentCopy :size="20" />
								</template>
								Kopiëren
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(effect, 'deleteObject')">
								<template #icon>
									<Delete :size="20" />
								</template>
								Verwijderen
							</NcActionButton>
						</template>
					</NcListItem>
				</RecycleScroller>

				<div v-if="objectStore.hasMorePages('effect')" class="pagination-info">
					<p>{{ objectStore.getCollection('effect').results?.length }} van {{ objectStore.getPagination('effect').total }} effecten</p>
					<div class="pagination-buttons">
						<NcButton
							:disabled="objectStore.isLoading('effect')"
							type="secondary"
							@click="objectStore.loadMore('effect')">
							Meer laden
						</NcButton>
					</div>
				</div>
			</div>

			<NcLoadingIcon v-if="objectStore.isLoading('effect')"
				:size="64"
				class="loadingIcon"
				appearance="dark"
				name="Effecten aan het laden" />

			<div v-if="!objectStore.getCollection('effect').results?.length && !objectStore.isLoading('effect')" class="emptyListHeader">
				Er zijn nog geen effecten gemaakt.
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
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

/**
 * EffectsList Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'EffectsList',
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
		MagicStaff,
		Plus,
		Pencil,
		Delete,
		Refresh,
		ContentCopy,
	},
	methods: {
		toggleActive(effect) {
			objectStore.getActiveObject('effect')?.id === effect?.id 
				? objectStore.clearActiveObject('effect') 
				: objectStore.setActiveObject('effect', effect)
		},
		openAddEffectModal() {
			navigationStore.setModal('editEffect')
			objectStore.clearActiveObject('effect')
		},
		onActionButtonClick(effect, action) {
			objectStore.setActiveObject('effect', effect)
			switch (action) {
			case 'edit':
				navigationStore.setModal('editEffect')
				break
			case 'copyObject':
			case 'deleteObject':
				navigationStore.setDialog(action, { objectType: 'effect', dialogTitle: 'Effect' })
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

.selectedEffectIcon>svg {
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
