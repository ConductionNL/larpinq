<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<ItemsList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!objectStore.getActiveObject('item') || navigationStore.selected != 'items'"
				class="detailContainer"
				name="Geen Item"
				description="Nog geen item geselecteerd">
				<template #icon>
					<Sword :size="20" />
				</template>
				<template #action>
					<NcButton type="primary" @click="objectStore.clearActiveObject('item'); navigationStore.setModal('editItem')">
						Item aanmaken
					</NcButton>
				</template>
			</NcEmptyContent>
			<ItemDetails v-if="objectStore.getActiveObject('item') && navigationStore.selected === 'items'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import ItemsList from './ItemsList.vue'
import ItemDetails from './ItemDetails.vue'
import Sword from 'vue-material-design-icons/Sword.vue'

/**
 * ItemsIndex Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'ItemsIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		ItemsList,
		ItemDetails,
		Sword,
	},
}
</script>

<style scoped>
.itemsIndex {
	height: 100%;
}
</style>
