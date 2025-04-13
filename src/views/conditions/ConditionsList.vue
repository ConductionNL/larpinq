<script setup>
import { objectStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<div class="conditionsList">
			<div class="conditionsHeader">
				<NcTextField
					:value="searchStore.getSearchTerm('condition')"
					:show-trailing-button="searchStore.getSearchTerm('condition') !== ''"
					type="search"
					label="Zoeken"
					@input="searchStore.setSearchTerm('condition', $event.target.value)"
					@trailing-button-click="searchStore.clearSearchTerm('condition')">
					<template #trailing-button-icon>
						<Close :size="20" />
					</template>
				</NcTextField>

				<div class="conditionsActions">
					<NcActions>
						<NcActionButton @click="objectStore.refreshObjectList('condition')">
							<template #icon>
								<Refresh :size="20" />
							</template>
							Vernieuwen
						</NcActionButton>
						<NcActionButton @click="objectStore.clearActiveObject('condition'); navigationStore.setModal('editCondition')">
							<template #icon>
								<Plus :size="20" />
							</template>
							Nieuwe conditie
						</NcActionButton>
					</NcActions>
				</div>
			</div>

			<div v-if="objectStore.getObjectList('condition')?.length > 0 && !objectStore.isLoading('condition')" class="conditionItems">
				<NcListItem v-for="condition in objectStore.getObjectList('condition')"
					:key="condition.id"
					:title="condition.name"
					:active="objectStore.getActiveObject('condition')?.id === condition.id"
					@click="selectCondition(condition)">
					<template #icon>
						<EmoticonSickOutline :class="objectStore.getActiveObject('condition')?.id === condition.id && 'selectedConditionIcon'" :size="20" />
					</template>
					<template #actions>
						<NcActions>
							<NcActionButton @click.stop="objectStore.setActiveObject('condition', condition); navigationStore.setModal('editCondition')">
								<template #icon>
									<Pencil :size="20" />
								</template>
								Bewerken
							</NcActionButton>
							<NcActionButton @click.stop="objectStore.setActiveObject('condition', condition); navigationStore.setDialog('deleteCondition')">
								<template #icon>
									<TrashCanOutline :size="20" />
								</template>
								Verwijderen
							</NcActionButton>
						</NcActions>
					</template>
				</NcListItem>
			</div>

			<div v-if="objectStore.isLoading('condition')" class="conditionsLoading">
				<NcLoadingIcon :size="50" />
			</div>

			<div v-if="objectStore.getObjectList('condition')?.length === 0 && !objectStore.isLoading('condition')" class="conditionsEmpty">
				<NcEmptyContent
					icon="icon-category-monitoring"
					title="Geen condities gevonden">
					<template #action>
						<NcButton type="primary" @click="objectStore.clearActiveObject('condition'); navigationStore.setModal('editCondition')">
							<template #icon>
								<Plus :size="20" />
							</template>
							Nieuwe conditie
						</NcButton>
					</template>
				</NcEmptyContent>
			</div>
		</div>
	</NcAppContentList>
</template>

<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon, NcEmptyContent, NcButton } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
	name: 'ConditionsList',
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
		EmoticonSickOutline,
		Magnify,
		Refresh,
		Plus,
		Pencil,
		TrashCanOutline,
		Close,
	},
	methods: {
		selectCondition(condition) {
			objectStore.setActiveObject('condition', condition)
			navigationStore.setSelected('conditions')
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
