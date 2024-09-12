<script setup>
import { navigationStore } from '../store/store.js'
</script>

<template>
	<!-- Placeholder div for all of the vieuws-->
	<NcAppContent>
		<template #default>
			<DashboardIndex v-if="navigationStore.selected === 'dashboard'" />
			<CharactersIndex v-if="navigationStore.selected === 'characters'" />
			<PlayersIndex v-if="navigationStore.selected === 'players'" />
			<ItemsIndex v-if="navigationStore.selected === 'items'" />
			<SearchIndex v-if="navigationStore.selected === 'search'" />
			<EventsIndex v-if="navigationStore.selected === 'events'" />
			<AbilitiesIndex v-if="navigationStore.selected === 'abilities'" />
			<SkillsIndex v-if="navigationStore.selected === 'skills'" />
			<EffectsIndex v-if="navigationStore.selected === 'effects'" />
			<TemplatesIndex v-if="navigationStore.selected === 'templates'" />
			<ConditionsIndex v-if="navigationStore.selected === 'conditions'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent } from '@nextcloud/vue'
import DashboardIndex from './dashboard/DashboardIndex.vue'
import SearchIndex from './search/SearchIndex.vue'
import CharactersIndex from './characters/CharactersIndex.vue'
import PlayersIndex from './players/PlayersIndex.vue'
import ItemsIndex from './items/ItemsIndex.vue'
import EventsIndex from './events/EventsIndex.vue'
import AbilitiesIndex from './abilities/AbilitiesIndex.vue'
import SkillsIndex from './skills/SkillsIndex.vue'
import EffectsIndex from './effects/EffectsIndex.vue'
import TemplatesIndex from './templates/TemplatesIndex.vue'
import ConditionsIndex from './conditions/ConditionsIndex.vue'
export default {
	name: 'Views',
	components: {
		DashboardIndex,
		SearchIndex,
		CharactersIndex,
		PlayersIndex,
		ItemsIndex,
		EventsIndex,
		AbilitiesIndex,
		SkillsIndex,
		EffectsIndex,
		TemplatesIndex,
		ConditionsIndex,
	},
}
</script>
