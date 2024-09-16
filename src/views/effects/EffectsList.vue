<script setup>
import { effectStore, searchStore, navigationStore } from '../../store/store.js'
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
					@trailing-button-click="effectStore.refreshEffectList()">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="effectStore.refreshEffectList()">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="effectStore.setEffectItem(null); navigationStore.setModal('addEffect')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Effect toevoegen
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="effectStore.effectList && effectStore.effectList.length > 0">
				<NcListItem v-for="(effect, i) in effectStore.effectList"
					:key="`${effect}${i}`"
					:name="effect.name"
					:active="effectStore.effectItem?.id === effect?.id"
					:force-display-actions="true"
					:details="effect?.modification || ''"
					:counter-number="effect?.modifier"
					@click="effectStore.setEffectItem(effect)">
					<template #icon>
						<MagicStaff :class="effectStore.effectItem === effect.id && 'selectedZaakIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ effect?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="effectStore.setEffectItem(effect); navigationStore.setModal('editEffect')">
							<template #icon>
								<Pencil />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="effectStore.setEffectItem(effect), navigationStore.setDialog('deleteEffect')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="!effectStore.effectList"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Klanten aan het laden" />

		<div v-if="effectStore.effectList.length === 0">
			Er zijn nog geen vaardigheden gedefinieerd.
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
	mounted() {
		effectStore.refreshEffectList()
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
