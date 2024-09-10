<script setup>
import { playerStore, characterStore, skillStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<!-- app-content-wrapper is optional, only use if app-content-list  -->
			<div>
				<h1 class="h1">
					{{ playerStore.playerItem.name }}
				</h1>
				<div class="grid">
					<div class="gridContent">
						<h4>Sammenvatting:</h4>
						<span>{{ playerStore.playerItem.summary }}</span>
					</div>
				</div>
			</div>
		</div>
		<div class="tabContainer">
			<BTabs content-class="mt-3" justified>
				<BTab title="Characters" active>
					<div v-if="filterCharacters.length > 0 && !charactersLoading">
						<NcListItem v-for="(character, i) in filterCharacters"
							:key="character.id + i"
							:name="character.name"
							:bold="false"
							:force-display-actions="true">
							<template #icon>
								<MagicStaff disable-menu
									:size="44" />
							</template>
							<template #subname>
								{{ character.description }}
							</template>
						</NcListItem>
					</div>
					<div v-if="filterCharacters.length === 0">
						Geen characters gevonden
					</div>
				</BTab>
				<BTab title="Events">
					<div v-if="playerStore.playerItem?.events?.length > 0">
						<NcListItem v-for="(event) in playerStore.playerItem?.events"
							:key="event.id"
							:name="event.name"
							:bold="false"
							:force-display-actions="true">
							<template #icon>
								<MagicStaff disable-menu
									:size="44" />
							</template>
							<template #subname>
								{{ event.description }}
							</template>
						</NcListItem>
					</div>
					<div v-if="!playerStore.playerItem?.events?.length">
						Geen events gevonden
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

export default {
	name: 'PlayerDetails',
	components: {
		NcListItem,
		BTabs,
		BTab,
	},
	data() {
		return {
			characters: [],
			charactersLoading: false,
		}
	},
	computed: {
		filterCharacters() {
			return characterStore.characterList.filter((character) => {
				return character.OCName === playerStore.playerItem.name
			})
		},
	},
	mounted() {
		this.fetchCharacters()
	},
	methods: {
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
