<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<AbilitiesList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!objectStore.getActiveObject('ability') || navigationStore.selected != 'abilities' "
				class="detailContainer"
				name="Geen vaardigheid"
				description="Nog geen vaardigheid geselecteerd">
				<template #icon>
					<ShieldSwordOutline />
				</template>
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('ability'), navigationStore.setModal('editAbility')">
						Vaardigheid aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<AbilityDetails v-if="objectStore.getActiveObject('ability') && navigationStore.selected === 'abilities'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import AbilitiesList from './AbilitiesList.vue'
import AbilityDetails from './AbilityDetails.vue'
// eslint-disable-next-line n/no-missing-import
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline'

export default {
	name: 'AbilitiesIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		AbilitiesList,
		AbilityDetails,
		ShieldSwordOutline,
	},
	data() {
		return {
			RolId: undefined,
		}
	},
}
</script>
