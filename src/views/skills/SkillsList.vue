<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField class="searchField"
					:value="objectStore.getSearchTerm('skill')"
					label="Zoeken"
					trailing-button-icon="close"
					:show-trailing-button="objectStore.getSearchTerm('skill') !== ''"
					@update:value="(value) => objectStore.setSearchTerm('skill', value)"
					@trailing-button-click="objectStore.clearSearchTerm('skill')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton :disabled="objectStore.isLoading('skill')"
						@click="objectStore.fetchCollection('skill')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="openAddSkillModal">
						<template #icon>
							<Plus :size="20" />
						</template>
						Vaardigheid toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="!objectStore.isLoading('skill')">
				<div v-if="objectStore.hasPreviousPages('skill')" class="pagination-info">
					<NcButton
						:disabled="objectStore.isLoading('skill')"
						type="secondary"
						@click="objectStore.loadPrevious('skill')">
						Vorige pagina
					</NcButton>
				</div>

				<RecycleScroller
					v-if="objectStore.getCollection('skill').results?.length"
					v-slot="{ item: skill }"
					class="scroller"
					:items="objectStore.getCollection('skill').results"
					:item-size="60"
					key-field="id">
					<NcListItem
						:key="skill.id"
						:name="skill.name"
						:details="skill.description || ''"
						:active="objectStore.getActiveObject('skill')?.id === skill.id"
						:force-display-actions="true"
						@click="toggleActive(skill)">
						<template #icon>
							<SwordCross
								:class="objectStore.getActiveObject('skill')?.id === skill.id && 'selectedSkillIcon'"
								:size="44" />
						</template>
						<template #subname>
							{{ skill.type || 'Geen type' }}
						</template>
						<template #actions>
							<NcActionButton @click="onActionButtonClick(skill, 'edit')">
								<template #icon>
									<Pencil :size="20" />
								</template>
								Bewerken
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(skill, 'copyObject')">
								<template #icon>
									<ContentCopy :size="20" />
								</template>
								Kopiëren
							</NcActionButton>
							<NcActionButton @click="onActionButtonClick(skill, 'deleteObject')">
								<template #icon>
									<Delete :size="20" />
								</template>
								Verwijderen
							</NcActionButton>
						</template>
					</NcListItem>
				</RecycleScroller>

				<div v-if="objectStore.hasMorePages('skill')" class="pagination-info">
					<p>{{ objectStore.getCollection('skill').results?.length }} van {{ objectStore.getPagination('skill').total }} vaardigheden</p>
					<div class="pagination-buttons">
						<NcButton
							:disabled="objectStore.isLoading('skill')"
							type="secondary"
							@click="objectStore.loadMore('skill')">
							Meer laden
						</NcButton>
					</div>
				</div>
			</div>

			<NcLoadingIcon v-if="objectStore.isLoading('skill')"
				:size="64"
				class="loadingIcon"
				appearance="dark"
				name="Vaardigheden aan het laden" />

			<div v-if="!objectStore.getCollection('skill').results?.length && !objectStore.isLoading('skill')" class="emptyListHeader">
				Er zijn nog geen vaardigheden gemaakt.
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
import SwordCross from 'vue-material-design-icons/SwordCross.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

/**
 * SkillsList Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'SkillsList',
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
		SwordCross,
		Plus,
		Pencil,
		Delete,
		Refresh,
		ContentCopy,
	},
	methods: {
		toggleActive(skill) {
			objectStore.getActiveObject('skill')?.id === skill?.id 
				? objectStore.clearActiveObject('skill') 
				: objectStore.setActiveObject('skill', skill)
		},
		openAddSkillModal() {
			navigationStore.setModal('editSkill')
			objectStore.clearActiveObject('skill')
		},
		onActionButtonClick(skill, action) {
			objectStore.setActiveObject('skill', skill)
			switch (action) {
			case 'edit':
				navigationStore.setModal('editSkill')
				break
			case 'copyObject':
			case 'deleteObject':
				navigationStore.setDialog(action, { objectType: 'skill', dialogTitle: 'Vaardigheid' })
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

.selectedSkillIcon>svg {
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
