<script setup>
import { abilityStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<AbilitiesList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!abilityStore.abilityItem || navigationStore.selected != 'abilities' "
				class="detailContainer"
				name="Geen vaardigheid"
				description="Nog geen vaardigheid geselecteerd">
				<template #icon>
					<ShieldSwordOutline />
				</template>
				<template #action>
					<NcButton type="primary" @click="abilityStore.setAbilityItem([]), navigationStore.setModal('editAbility')">
						Vaardigheid aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<AbilityDetails v-if="abilityStore.abilityItem && navigationStore.selected === 'abilities'" />
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
