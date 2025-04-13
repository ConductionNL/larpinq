<script setup>
import { objectStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					:value="searchStore.getSearchTerm('skill')"
					:show-trailing-button="searchStore.getSearchTerm('skill') !== ''"
					label="Search"
					class="searchField"
					trailing-button-icon="close"
					@input="searchStore.setSearchTerm('skill', $event.target.value)"
					@trailing-button-click="searchStore.clearSearchTerm('skill')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="objectStore.refreshObjectList('skill')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="objectStore.clearActiveObject('skill'); navigationStore.setModal('editSkill')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Vaardigheid toevoegen
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="objectStore.getObjectList('skill')?.length > 0 && !objectStore.isLoading('skill')">
				<NcListItem v-for="skill in objectStore.getObjectList('skill')"
					:key="skill.id"
					:name="skill.name"
					:active="objectStore.getActiveObject('skill')?.id === skill.id"
					:details="(skill?.effects?.length || '0') + ' effect(s)'"
					:force-display-actions="true"
					@click="selectSkill(skill)">
					<template #icon>
						<SwordCross :class="objectStore.getActiveObject('skill')?.id === skill.id && 'selectedSkillIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ skill?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="selectSkill(skill); navigationStore.setModal('editSkill')">
							<template #icon>
								<Pencil />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="selectSkill(skill), navigationStore.setDialog('deleteSkill')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="objectStore.isLoading('skill')"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Vaardigheden aan het laden" />

		<div v-if="objectStore.getObjectList('skill')?.length === 0 && !objectStore.isLoading('skill')">
			Er zijn nog geen vaardigheden gedefinieerd.
		</div>
	</NcAppContentList>
</template>
<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'

export default {
	name: 'SkillsList',
	components: {
		// Components
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		// Icons
		SwordCross,
		Magnify,
		Plus,
		TrashCanOutline,
		Refresh,
	},
	methods: {
		/**
		 * Handle skill selection
		 * @param {object} skill - The selected skill object
		 * @return {Promise<void>}
		 */
		selectSkill(skill) {
			objectStore.setActiveObject('skill', skill)
			navigationStore.setSelected('skills')
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
</style>
