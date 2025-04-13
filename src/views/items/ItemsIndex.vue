<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="itemsIndex">
		<NcEmptyContent v-if="!objectStore.getActiveObject('item') || navigationStore.selected != 'items'"
			icon="icon-category-customization"
			title="Items">
			<template #action>
				<NcButton type="primary" @click="objectStore.clearActiveObject('item'); navigationStore.setModal('editItem')">
					<template #icon>
						<Plus :size="20" />
					</template>
					Nieuw item
				</NcButton>
			</template>
		</NcEmptyContent>

		<ItemDetails v-if="objectStore.getActiveObject('item') && navigationStore.selected === 'items'" />
	</div>
</template>

<script>
import {
	NcButton,
	NcEmptyContent,
} from '@nextcloud/vue'

import Plus from 'vue-material-design-icons/Plus.vue'
import ItemDetails from './ItemDetails.vue'

export default {
	name: 'ItemsIndex',
	components: {
		NcButton,
		NcEmptyContent,
		Plus,
		ItemDetails,
	},
}
</script>

<style scoped>
.itemsIndex {
	height: 100%;
}
</style>
