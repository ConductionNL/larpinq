<script setup>
import { skillStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<SkillsList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!skillStore.skillItem || navigationStore.selected != 'skills' "
				class="detailContainer"
				name="Geen skil"
				description="Nog geen skill geselecteerd">
				<template #icon>
					<SwordCross />
				</template>
				<template #action>
					<NcButton type="primary" @click="skillStore.setSkillItem(null); navigationStore.setModal('editSkill')">
						Skill toevoegen
					</NcButton>
				</template>
			</NcEmptyContent>
			<SkillDetails v-if="skillStore.skillItem && navigationStore.selected === 'skills'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import SkillsList from './SkillsList.vue'
import SkillDetails from './SkillDetails.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'

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
