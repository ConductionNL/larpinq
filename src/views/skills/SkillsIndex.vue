<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<SkillsList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!objectStore.getActiveObject('skill') || navigationStore.selected != 'skills'"
				class="detailContainer"
				name="Geen Vaardigheid"
				description="Nog geen vaardigheid geselecteerd">
				<template #icon>
					<SwordCross :size="20" />
				</template>
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('skill'); navigationStore.setModal('editSkill')">
						Vaardigheid aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<SkillDetails v-if="objectStore.getActiveObject('skill') && navigationStore.selected === 'skills'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import SkillsList from './SkillsList.vue'
import SkillDetails from './SkillDetails.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'

/**
 * SkillsIndex Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'SkillsIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		SkillsList,
		SkillDetails,
		SwordCross,
	},
}
</script>
