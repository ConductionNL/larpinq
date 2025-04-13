<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
import { NcButton, NcCounterBubble } from '@nextcloud/vue'
import ObjectList from '../../components/ObjectList.vue'

// Icons
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
</script>

<template>
	<div class="itemDetails">
		<div class="itemHeader">
			<h2>{{ objectStore.getActiveObject('item')?.name }}</h2>
			<div class="itemActions">
				<NcButton @click="objectStore.setActiveObject('item', objectStore.getActiveObject('item')); navigationStore.setModal('editItem')">
					<template #icon>
						<Pencil :size="20" />
					</template>
					Bewerken
				</NcButton>
				<NcButton type="error" @click="objectStore.setActiveObject('item', objectStore.getActiveObject('item')); navigationStore.setDialog('deleteItem')">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
					Verwijderen
				</NcButton>
			</div>
		</div>

		<div class="itemContent">
			<div class="itemInfo">
				<div class="itemDescription">
					<h3>Beschrijving</h3>
					<span>{{ objectStore.getActiveObject('item')?.description }}</span>
				</div>

				<div class="itemUnique">
					<h3>Uniek</h3>
					<span>{{ objectStore.getActiveObject('item')?.unique }}</span>
				</div>
			</div>

			<div class="itemRelations">
				<h3>Karakters <NcCounterBubble>{{ objectStore.getRelations('item')?.length || 0 }}</NcCounterBubble></h3>
				<ObjectList :objects="objectStore.getRelations('item')" />
			</div>

			<div class="itemAudit">
				<h3>Logging <NcCounterBubble>{{ objectStore.getAuditTrails('item')?.length || 0 }}</NcCounterBubble></h3>
				<ObjectList :objects="objectStore.getAuditTrails('item')" />
			</div>
		</div>
	</div>
</template>

<style scoped>
.itemDetails {
	display: flex;
	flex-direction: column;
	gap: 2rem;
	padding: 2rem;
}

.itemHeader {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.itemActions {
	display: flex;
	gap: 1rem;
}

.itemContent {
	display: flex;
	flex-direction: column;
	gap: 2rem;
}

.itemInfo {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.itemDescription,
.itemUnique {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.itemRelations,
.itemAudit {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}
</style>
