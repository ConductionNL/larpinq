<script setup>
import { conditionStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<ConditionsList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!conditionStore.conditionItem || navigationStore.selected != 'conditions' "
				class="detailContainer"
				name="Geen Conditie"
				description="Nog geen conditie geselecteerd">
				<template #icon>
					<EmoticonSickOutline />
				</template>
				<template #action>
					<NcButton type="primary" @click="conditionStore.setConditionItem(null); navigationStore.setModal('editCondition')">
						Conditie aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<ConditionDetails v-if="conditionStore.conditionItem && navigationStore.selected === 'conditions'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import ConditionsList from './ConditionsList.vue'
import ConditionDetails from './ConditionsDetails.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'

export default {
	name: 'ConditionsIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		ConditionsList,
		ConditionDetails,
		EmoticonSickOutline,
	},
}
</script>
