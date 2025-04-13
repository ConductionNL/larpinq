<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<PlayersList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!objectStore.getActiveObject('player') || navigationStore.selected != 'players'"
				class="detailContainer"
				name="Geen speler"
				description="Nog geen speler geselecteerd">
				<template #icon>
					<AccountGroupOutline />
				</template>
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('player'); navigationStore.setModal('editPlayer')">
						Speler aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<PlayerDetails v-if="objectStore.getActiveObject('player') && navigationStore.selected === 'players'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import PlayersList from './PlayersList.vue'
import PlayerDetails from './PlayerDetails.vue'
// eslint-disable-next-line n/no-missing-import
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline'

export default {
	name: 'PlayersIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		PlayersList,
		PlayerDetails,
		AccountGroupOutline,
	},
}
</script>
