<script setup>
import { abilityStore, navigationStore } from '../../store/store.js'
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
						<NcActionButton @click="handleAbilitySelect(abilityStore.abilityItem); navigationStore.setModal('editAbility')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="handleAbilitySelect(abilityStore.abilityItem); navigationStore.setDialog('deleteAbility')">
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
							<div v-if="abilityStore.relations?.length > 0">
								<NcListItem v-for="relation in abilityStore.relations"
									:key="relation.id"
									:name="relation.name || 'No name available'"
									:bold="false"
									:force-display-actions="true">
									<template #icon>
										<BriefcaseAccountOutline disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ relation.description || 'No description available' }}
									</template>
								</NcListItem>
							</div>
							<div v-else>
								Geen characters gevonden
							</div>
						</BTab>
						<BTab title="Logging">
							<div v-if="abilityStore.auditTrails?.length > 0">
								<NcListItem v-for="log in abilityStore.auditTrails"
									:key="log.id"
									:name="log.name || 'No name available'"
									:bold="false"
									:force-display-actions="true">
									<template #icon>
										<BriefcaseAccountOutline disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ log.description || 'No description available' }}
									</template>
								</NcListItem>
							</div>
							<div v-else>
								Geen logging gevonden
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
import { NcLoadingIcon, NcActions, NcActionButton, NcListItem } from '@nextcloud/vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import { storeToRefs } from 'pinia'
export default {
	name: 'AbilityDetails',
	components: {
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		NcListItem,
		BTabs,
		BTab,
		// Icons
		DotsHorizontal,
		Pencil,
		TrashCanOutline,
		BriefcaseAccountOutline,
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
