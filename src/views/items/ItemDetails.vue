<script setup>
import { characterStore, effectStore, itemStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<!-- app-content-wrapper is optional, only use if app-content-list  -->
			<div>
				<div class="head">
					<h1 class="h1">
						{{ itemStore.itemItem.name }}
					</h1>

					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editItem')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteItem')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</div>
				<div class="detailGrid">
					<div>
						<b>Sammenvatting:</b>
						<span>{{ itemStore.itemItem.summary  }}</span>
					</div>
				</div>
				<span>{{ itemStore.itemItem.description }}</span>
			</div>
		</div>
		<div class="tabContainer">
			<BTabs content-class="mt-3" justified>
				<BTab title="Effecten" active>
					<div v-if="filterEffects?.length > 0 && !effectsLoading">
						<NcListItem v-for="(effect, i) in filterEffects"
							:key="effect.id + i"
							:name="effect.name"
							:bold="false"
							:force-display-actions="true">
							<template #icon>
								<MagicStaff disable-menu
									:size="44" />
							</template>
							<template #subname>
								{{ effect.description }}
							</template>
						</NcListItem>
					</div>
					<div v-if="!filterEffects?.length">
						Geen effecten gevonden
					</div>
				</BTab>
				<BTab title="Karakters">
					<div v-if="filterCharacters?.length > 0">
						<NcListItem v-for="(character) in filterCharacters"
							:key="character.id"
							:name="character.name"
							:bold="false"
							:force-display-actions="true">
							<template #icon>
								<BriefcaseAccountOutline disable-menu
									:size="44" />
							</template>
							<template #subname>
								{{ character.description }}
							</template>
						</NcListItem>
					</div>
					<div v-if="!filterCharacters?.length">
						Geen karakters gevonden
					</div>
				</BTab>
			</BTabs>
		</div>
	</div>
</template>

<script>
import {
	NcListItem,
} from '@nextcloud/vue'
import { BTabs, BTab } from 'bootstrap-vue'

import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'

export default {
	name: 'ItemDetails',
	components: {
		NcListItem,
		BTabs,
		BTab,
	},
	data() {
		return {
			effectsLoading: false,
			charactersLoading: false,
		}
	},
	computed: {
		filterEffects() {
			return effectStore.effectList.filter((effect) => {
				return itemStore.itemItem?.effects.map(String).includes(effect.id.toString())
			})
		},
		filterCharacters() {
			return characterStore.characterList.filter((character) => {
				return character.items.map(String).includes(itemStore.itemItem.id.toString())
			})
		},
	},
	mounted() {
		this.fetchEffects()
		this.fetchCharacters()
	},
	methods: {
		fetchEffects() {
			this.effectsLoading = true
			effectStore.refreshEffectList()
				.then(() => {
					this.effectsLoading = false
				})
		},
		fetchCharacters() {
			this.charactersLoading = true
			characterStore.refreshCharacterList()
				.then(() => {
					this.charactersLoading = false
				})
		},
	},
}
</script>

<style>
h4 {
  font-weight: bold
}

.h1 {
  display: block !important;
  font-size: 2em !important;
  margin-block-start: 0.67em !important;
  margin-block-end: 0.67em !important;
  margin-inline-start: 0px !important;
  margin-inline-end: 0px !important;
  font-weight: bold !important;
  unicode-bidi: isolate !important;
}

.grid {
  display: grid;
  grid-gap: 24px;
  grid-template-columns: 1fr 1fr;
  margin-block-start: var(--zaa-margin-50);
  margin-block-end: var(--zaa-margin-50);
}

.gridContent {
  display: flex;
  gap: 25px;
}

.tabPanel {
  padding: 20px 10px;
  min-height: 100%;
  max-height: 100%;
  height: 100%;
  overflow: auto;
}
</style>
