<script setup>
	import { characterStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<CharactersList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!characterStore.characterItem || navigationStore.selected != 'characters' "
				class="detailContainer"
				name="Geen Karakter"
				description="Nog geen karakter geselecteerd">
				<template #icon>
					<BriefcaseAccountOutline />
				</template>
				<template #action>
					<NcButton type="primary" @click="characterStore.setCharacterItem([]); navigationStore.setModal('editCharacter')">
						Karakter aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<CharacterDetails v-if="characterStore.characterItem && navigationStore.selected === 'characters'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import CharactersList from './CharactersList.vue'
import CharacterDetails from './CharacterDetails.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'

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
