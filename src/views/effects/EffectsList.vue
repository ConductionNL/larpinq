<script setup>
import { objectStore, searchStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					:value="objectStore.getSearchTerm('effect')"
					:show-trailing-button="objectStore.getSearchTerm('effect') !== ''"
					label="Search"
					class="searchField"
					trailing-button-icon="close"
					@input="objectStore.setSearchTerm('effect', $event.target.value)"
					@trailing-button-click="objectStore.clearSearch('effect')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="objectStore.fetchCollection('effect')">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="objectStore.clearActiveObject('effect'); navigationStore.setModal('editEffect')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Effect toevoegen
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="objectStore.getCollection('effect').results?.length > 0 && !objectStore.isLoading('effect')">
				<NcListItem v-for="(effect, i) in objectStore.getCollection('effect').results"
					:key="`${effect}${i}`"
					:name="effect.name"
					:active="objectStore.getActiveObject('effect')?.id === effect?.id"
					:force-display-actions="true"
					:details="effect?.modification || ''"
					:counter-number="effect?.modifier"
					@click="handleEffectSelect(effect)">
					<template #icon>
						<MagicStaff :class="objectStore.getActiveObject('effect')?.id === effect.id && 'selectedZaakIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ effect?.name }}
					</template>
					<template #actions>
						<NcActionButton @click="handleEffectSelect(effect); navigationStore.setModal('editEffect')">
							<template #icon>
								<Pencil />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="handleEffectSelect(effect); navigationStore.setDialog('deleteEffect')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="objectStore.isLoading('effect')"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Effecten aan het laden" />

		<div v-if="objectStore.getCollection('effect').results?.length === 0 && !objectStore.isLoading('effect')">
			Er zijn nog geen effecten gedefinieerd.
		</div>
	</NcAppContentList>
</template>
<script>
// Components
import { NcListItem, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon, NcActions } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
export default {
	name: 'EffectsList',
	components: {
		// Components
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		// Icons
		MagicStaff,
		Magnify,
		Pencil,
		TrashCanOutline,
	},
	methods: {
		/**
		 * Handle effect selection
		 * @param {object} effect - The selected effect object
		 * @return {Promise<void>}
		 */
		async handleEffectSelect(effect) {
			// Set the selected effect in the store
			objectStore.setActiveObject('effect', effect)
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
