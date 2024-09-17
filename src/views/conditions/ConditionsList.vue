<script setup>
import { conditionStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					:value.sync="searchStore.search"
					:show-trailing-button="searchStore.search !== ''"
					label="Search"
					class="searchField"
					trailing-button-icon="close"
					@trailing-button-click="searchStore.setSearch('')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="conditionStore.refreshConditionList()">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="conditionStore.setConditionItem(null); navigationStore.setModal('editCondition')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Conditie toevoegen
					</NcActionButton>
				</NcActions>
			</div>

			<div v-if="conditionStore.conditionList && conditionStore.conditionList.length > 0">
				<NcListItem v-for="(condition, i) in conditionStore.conditionList"
					:key="`${condition}${i}`"
					:name="condition?.name"
					:force-display-actions="true"
					:active="conditionStore.conditionItem?.id === condition?.id"
					:details="condition?.unique ? 'Uniek' : 'Niet uniek'"
					@click="conditionStore.setConditionItem(condition)">
					<template #icon>
						<EmoticonSickOutline :class="conditionStore.conditionItem?.id === condition?.id && 'selectedConditionIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ condition?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="conditionStore.setConditionItem(condition); navigationStore.setModal('editCondition')">
							<template #icon>
								<Pencil />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="conditionStore.setConditionItem(condition); navigationStore.setDialog('deleteCondition')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="!conditionStore.conditionList"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Condities aan het laden" />

		<div v-if="conditionStore.conditionList.length === 0">
			Er zijn nog geen condities gedefinieerd.
		</div>
	</NcAppContentList>
</template>
<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

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
		// Icons
		EmoticonSickOutline,
		Magnify,
		Refresh,
		Plus,
		Pencil,
		TrashCanOutline,
	},
	mounted() {
		conditionStore.refreshConditionList()
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
