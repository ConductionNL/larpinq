<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="effectDetails">
		<div class="effectHeader">
			<h2>{{ objectStore.getActiveObject('effect')?.name }}</h2>
			<div class="effectActions">
				<NcButton @click="objectStore.setActiveObject('effect', objectStore.getActiveObject('effect')); navigationStore.setModal('editEffect')">
					<template #icon>
						<Pencil :size="20" />
					</template>
					Bewerken
				</NcButton>
				<NcButton type="error" @click="objectStore.setActiveObject('effect', objectStore.getActiveObject('effect')); navigationStore.setDialog('deleteObject', { objectType: 'effect', dialogTitle: 'Effect' })">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
					Verwijderen
				</NcButton>
			</div>
		</div>

		<div class="effectContent">
			<div class="effectInfo">
				<div class="effectType">
					<h3>Type</h3>
					<span>{{ objectStore.getActiveObject('effect')?.type || 'Geen type' }}</span>
				</div>

				<div class="effectDescription">
					<h3>Beschrijving</h3>
					<span>{{ objectStore.getActiveObject('effect')?.description || 'Geen beschrijving' }}</span>
				</div>

				<div class="effectDuration">
					<h3>Duur</h3>
					<span>{{ objectStore.getActiveObject('effect')?.duration || 'Geen duur' }}</span>
				</div>

				<div class="effectPower">
					<h3>Kracht</h3>
					<span>{{ objectStore.getActiveObject('effect')?.power || 'Geen kracht' }}</span>
				</div>
			</div>

			<div class="effectRelations">
				<h3>Karakters <NcCounterBubble>{{ objectStore.getRelatedData('effect', 'uses')?.length || 0 }}</NcCounterBubble></h3>
				<ObjectList :objects="objectStore.getRelatedData('effect', 'uses')" />
			</div>

			<div class="effectAudit">
				<h3>Logging <NcCounterBubble>{{ objectStore.getAuditTrails('effect')?.length || 0 }}</NcCounterBubble></h3>
				<AuditTable :logs="objectStore.getAuditTrails('effect')" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcCounterBubble } from '@nextcloud/vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import ObjectList from '../../components/ObjectList.vue'
import AuditTable from '../auditTrail/AuditTable.vue'

/**
 * EffectDetails Component
 * @module Views
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'EffectDetails',
	components: {
		NcButton,
		NcCounterBubble,
		Pencil,
		TrashCanOutline,
		ObjectList,
		AuditTable,
	},
}
</script>

<style scoped>
.effectDetails {
	display: flex;
	flex-direction: column;
	gap: 2rem;
	padding: 2rem;
}

.effectHeader {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.effectActions {
	display: flex;
	gap: 1rem;
}

.effectContent {
	display: flex;
	flex-direction: column;
	gap: 2rem;
}

.effectInfo {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.effectType,
.effectDescription,
.effectDuration,
.effectPower {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.effectRelations,
.effectAudit {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}
</style>
