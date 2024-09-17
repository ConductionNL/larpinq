<script setup>
import { abilityStore, navigationStore, effectStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<!-- app-content-wrapper is optional, only use if app-content-list  -->
			<div>
				<div class="head">
					<h1 class="h1">
						{{ abilityStore.abilityItem.name }}
					</h1>

					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editAbility')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteAbility')">
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
						<span>{{ abilityStore.abilityItem.summary }}</span>
					</div>
				</div>
				<span>{{ abilityStore.abilityItem.description }}</span>

				<div class="tabContainer">
					<BTabs content-class="mt-3" justified>
						<BTab title="Effects">
							<div v-if="filterEffects.length > 0">
								<NcListItem v-for="(effect) in filterEffects"
									:key="effect.id"
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
							<div v-if="filterEffects.length === 0">
								Geen effects gevonden
							</div>
						</BTab>
					</BTabs>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { BTabs, BTab } from 'bootstrap-vue'
import { NcLoadingIcon, NcActions, NcActionButton } from '@nextcloud/vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
export default {
	name: 'AbilityDetails',
	components: {
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		BTabs,
		BTab,
		// Icons
		DotsHorizontal,
		Pencil,
		TrashCanOutline,
	},
	data() {
		return {
			effectsLoading: false,
		}
		
	},
	computed: {
		filterEffects() {
			return effectStore.effectList.filter((effect) => {
				return effect.stat === abilityStore.abilityItem.id.toString();
			});
		},
	},
	mounted() {
		this.fetchEffects()
	},
	methods: {
		fetchEffects() {
			this.effectsLoading = true

			effectStore.refreshEffectList()
				.then(() => {
					this.effectsLoading = false
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

</style>
