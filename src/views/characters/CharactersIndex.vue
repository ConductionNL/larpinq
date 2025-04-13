<script setup>
import { navigationStore, objectStore } from '../../store/store.js'
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
</script>

<template>
	<NcAppContent>
		<template #list>
			<CharactersList />
		</template>
		<template #default>
			<NcEmptyContent v-if="showEmptyContent"
				class="detailContainer"
				name="Geen Karakter"
				description="Nog geen karakter geselecteerd">
				<template #icon>
					<BriefcaseAccountOutline />
				</template>
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('character'); navigationStore.setModal('editCharacter')">
						Karakter aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<CharacterDetails v-if="!showEmptyContent" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import CharactersList from './CharactersList.vue'
import CharacterDetails from './CharacterDetails.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'

// Make the stores reactive
const { selected } = storeToRefs(navigationStore)
const activeCharacter = computed(() => objectStore.getActiveObject('character'))

const showEmptyContent = computed(() => {
	const hasActiveCharacter = activeCharacter.value
	const isCharactersSelected = selected.value === 'characters'
	return !hasActiveCharacter || !isCharactersSelected
})

export default {
	name: 'CharactersIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		CharactersList,
		CharacterDetails,
		BriefcaseAccountOutline,
	},
}
</script>
