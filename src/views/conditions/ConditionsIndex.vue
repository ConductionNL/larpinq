<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<ConditionsList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!objectStore.getActiveObject('condition') || navigationStore.selected != 'conditions'"
				class="detailContainer"
				name="Geen Conditie"
				description="Nog geen conditie geselecteerd">
				<template #icon>
					<EmoticonSickOutline />
				</template>
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('condition'); navigationStore.setModal('editCondition')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Nieuwe conditie
					</NcButton>
				</template>
			</NcEmptyContent>
			<ConditionDetails v-if="objectStore.getActiveObject('condition') && navigationStore.selected === 'conditions'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import ConditionsList from './ConditionsList.vue'
import ConditionDetails from './ConditionDetails.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'ConditionsIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		ConditionsList,
		ConditionDetails,
		EmoticonSickOutline,
		Plus,
	},
}
</script>
