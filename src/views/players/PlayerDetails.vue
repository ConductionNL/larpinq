<script setup>
import { playerStore } from '../../store/store.js'
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
				<BTab title="Characters">
					<div v-if="playerStore.relations?.length > 0">
						<NcListItem v-for="relation in playerStore.relations"
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
					<div v-if="playerStore.auditTrails?.length > 0">
						<NcListItem v-for="log in playerStore.auditTrails"
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
</template>

<script>
import {
	NcListItem,
} from '@nextcloud/vue'
import { BTabs, BTab } from 'bootstrap-vue'

import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'

export default {
	name: 'PlayerDetails',
	components: {
		NcListItem,
		BTabs,
		BTab,
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
