<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<EffectsList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!objectStore.getActiveObject('effect') || navigationStore.selected != 'effects'"
				class="detailContainer"
				name="Geen effect"
				description="Nog geen effect geselecteerd">
				<template #icon>
					<MagicStaff :size="20" />
				</template>
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('effect'); navigationStore.setModal('editEffect')">
						Effect aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<EffectDetails v-if="objectStore.getActiveObject('effect') && navigationStore.selected === 'effects'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import EffectsList from './EffectsList.vue'
import EffectDetails from './EffectDetails.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'

/**
 * EffectsIndex Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'EffectsIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		EffectsList,
		EffectDetails,
		MagicStaff,
	},
	data() {
		return {
			activeMetaData: false,
			klantId: undefined,
		}
	},
}
</script>
