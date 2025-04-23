<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<AbilitiesList />
		</template>
		<template #default>
			<NcEmptyContent
				v-if="!objectStore.getActiveObject('ability') || navigationStore.selected !== 'abilities'"
				class="detailContainer"
				name="Geen Vaardigheid">
				<template #icon>
					<ShieldSwordOutline :size="20" />
				</template>
				<template #action>
					<NcButton @click="openModal">
						<template #icon>
							<Plus :size="20" />
						</template>
						Vaardigheid toevoegen
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
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'AbilitiesIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		AbilitiesList,
		AbilityDetails,
		ShieldSwordOutline,
		Plus,
	},
	data() {
		return {
			RolId: undefined,
		}
	},
	methods: {
		openModal() {
			navigationStore.setModal('editAbility')
			objectStore.clearActiveObject('ability')
		},
	},
}
</script>
