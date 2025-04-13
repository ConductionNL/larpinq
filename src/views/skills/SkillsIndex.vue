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
				icon="icon-category-customization"
				title="Vaardigheden"
				description="Nog geen vaardigheid geselecteerd">
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('skill'); navigationStore.setModal('editSkill')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Nieuwe vaardigheid
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
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'SkillsIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		SkillsList,
		SkillDetails,
		Plus,
	},
}
</script>
