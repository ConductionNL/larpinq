<script setup>
import { abilityStore, navigationStore, searchStore } from '../../store/store.js'
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
					<NcActionButton @click="abilityStore.refreshAbilityList()">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="abilityStore.setAbilityItem([]), navigationStore.setModal('editAbility')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Vaardigheid toevoegen
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="abilityStore.abilityList && abilityStore.abilityList.length > 0">
				<NcListItem v-for="(ability, i) in abilityStore.abilityList"
					:key="`${ability}${i}`"
					:name="ability?.name"
					:active="abilityStore.abilityItem.id === ability?.id"
					:details="'1h'"
					:counter-number="44"
					@click="abilityStore.setAbilityItem(ability)">
					<template #icon>
						<ShieldSwordOutline :class="abilityStore.abilityItem?.id === ability.id && 'selected'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ ability?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="abilityStore.setAbilityItem(ability), navigationStore.setModal('editAbility')">
							<template #icon>
								<Plus />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="abilityStore.setAbilityItem(ability), navigationStore.setDialog('deleteAbility')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="!abilityStore.abilityList"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Vaardigheden aan het laden" />

		<div v-if="abilityStore.abilityList.length === 0">
			Er zijn nog geen vaardigheden gedefinieerd.
		</div>
	</NcAppContentList>
</template>
<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'

export default {
	name: 'AbilitiesList',
	components: {
		// Components
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		// Icons
		ShieldSwordOutline,
		Magnify,
		Plus,
		Pencil,
		TrashCanOutline,
		Refresh,
	},
	mounted() {
		abilityStore.refreshAbilityList()
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
