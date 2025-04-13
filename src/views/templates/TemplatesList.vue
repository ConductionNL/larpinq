<script setup>
import { objectStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					type="search"
					:value="searchStore.getSearchTerm('template')"
					:show-trailing-button="searchStore.getSearchTerm('template') !== ''"
					label="Search templates"
					class="searchField"
					@input="searchStore.setSearchTerm('template', $event.target.value)"
					@trailing-button-click="searchStore.clearSearchTerm('template')">
					<template #trailing-button-icon>
						<Close :size="20" />
					</template>
				</NcTextField>
				<NcActions>
					<NcActionButton @click="objectStore.refreshObjectList('template')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Refresh
					</NcActionButton>
					<NcActionButton @click="objectStore.setActiveObject('template', null); navigationStore.setModal('editTemplate')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Add template
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="objectStore.getCollection('template').results.length > 0 && !objectStore.getCollection('template').loading">
				<NcListItem v-for="template in objectStore.getCollection('template').results"
					:key="template.id"
					:name="template.name"
					:active="objectStore.getActiveObject('template')?.id === template?.id"
					:details="template.description"
					:force-display-actions="true"
					@click="objectStore.setActiveObject('template', template)">
					<template #icon>
						<ChatOutline :class="objectStore.getActiveObject('template')?.id === template.id && 'selected'"
							:size="44" />
					</template>
					<template #actions>
						<NcActionButton @click="objectStore.setActiveObject('template', template); navigationStore.setModal('editTemplate')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Edit
						</NcActionButton>
						<NcActionButton @click="objectStore.setActiveObject('template', template); navigationStore.setDialog('deleteTemplate')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Delete
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="objectStore.getCollection('template').loading"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Templates aan het laden" />

		<div v-if="objectStore.getCollection('template').results.length === 0 && !objectStore.getCollection('template').loading">
			<NcEmptyContent
				icon="icon-template"
				title="No templates found">
				<template #action>
					<div class="buttons">
						<NcButton type="primary" @click="objectStore.setActiveObject('template', null); navigationStore.setModal('editTemplate')">
							<template #icon>
								<Plus :size="20" />
							</template>
							Add template
						</NcButton>
					</div>
				</template>
			</NcEmptyContent>
		</div>
	</NcAppContentList>
</template>
<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon, NcEmptyContent, NcButton } from '@nextcloud/vue'

// Icons
import Close from 'vue-material-design-icons/Close.vue'
import ChatOutline from 'vue-material-design-icons/ChatOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'

export default {
	name: 'TemplatesList',
	components: {
		// Components
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		NcEmptyContent,
		NcButton,
		// Icons
		Close,
		ChatOutline,
		Plus,
		Pencil,
		TrashCanOutline,
		Refresh,
	},
	mounted() {
		objectStore.refreshObjectList('template')
	},
	methods: {
		/**
		 * Handle template selection
		 * @param {object} template - The selected template object
		 * @returns {Promise<void>}
		 */
		async handleTemplateSelect(template) {
			// Set the selected template in the store
			objectStore.setActiveObject('template', template)
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

.selected>svg {
    fill: white;
}

.loadingIcon {
    margin-block-start: var(--OC-margin-20);
}
</style>
