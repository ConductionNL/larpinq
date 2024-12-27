<script setup>
import { conditionStore, navigationStore, effectStore, characterStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<!-- app-content-wrapper is optional, only use if app-content-list  -->
			<div>
				<div class="head">
					<h1 class="h1">
						{{ conditionStore.conditionItem.name }}
					</h1>
					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editCondition')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteCondition')">
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
						<span>{{ conditionStore.conditionItem.summary }}</span>
					</div>
				</div>
				<span>{{ conditionStore.conditionItem.description }}</span>
				<div class="tabContainer">
					<BTabs content-class="mt-3" justified>
						<BTab title="Effects" active>
							<div v-if="conditionStore.relations?.effects?.length > 0">
								<NcListItem v-for="(effect) in conditionStore.relations.effects"
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
							<div v-else>
								Geen effects gevonden
							</div>
						</BTab>
						<BTab title="Characters">
							<div v-if="conditionStore.relations?.characters?.length > 0">
								<NcListItem v-for="(character, i) in conditionStore.relations.characters"
									:key="character.id + i"
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
							<div v-else>
								Geen characters gevonden
							</div>
						</BTab>
					</BTabs>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
// Components
import { BTabs, BTab } from 'bootstrap-vue'
import { NcLoadingIcon, NcActions, NcActionButton } from '@nextcloud/vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import FileDocumentPlusOutline from 'vue-material-design-icons/FileDocumentPlusOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'ConditionDetails',
	components: {
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		BTabs,
		BTab,
		// Icons
		DotsHorizontal,
		Pencil,
		FileDocumentPlusOutline,
		TrashCanOutline,
	},
}
</script>

<style>

h4 {
  font-weight: bold;
}

.head{
	display: flex;
	justify-content: space-between;
}

.button{
	max-height: 10px;
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

.dataContent {
  display: flex;
  flex-direction: column;
}

</style>
