<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
import ObjectList from '../../components/ObjectList.vue'

// Icons
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

// Components
import {
	NcActions,
	NcActionButton,
} from '@nextcloud/vue'
</script>

<template>
	<div class="skillDetails">
		<div class="skillHeader">
			<h2>{{ objectStore.getActiveObject('skill')?.name }}</h2>
			<div class="skillActions">
				<NcActions>
					<NcActionButton @click="objectStore.setActiveObject('skill', objectStore.getActiveObject('skill')); navigationStore.setModal('editSkill')">
						<template #icon>
							<Pencil :size="20" />
						</template>
						Bewerken
					</NcActionButton>
					<NcActionButton @click="objectStore.setActiveObject('skill', objectStore.getActiveObject('skill')); navigationStore.setDialog('deleteSkill')">
						<template #icon>
							<TrashCanOutline :size="20" />
						</template>
						Verwijderen
					</NcActionButton>
				</NcActions>
			</div>
		</div>

		<div class="detailContainer">
			<div id="app-content">
				<div class="detailSection">
					<h3>Beschrijving</h3>
					<p>{{ objectStore.getActiveObject('skill')?.description || 'Geen beschrijving' }}</p>
				</div>

				<div class="detailSection">
					<h3>Effecten</h3>
					<ObjectList :objects="objectStore.getActiveObject('skill')?.effects || []" />
				</div>

				<div class="detailSection">
					<h3>Audit log</h3>
					<ObjectList :objects="objectStore.getActiveObject('skill')?.audits || []" />
				</div>
			</div>
		</div>
	</div>
</template>

<style lang="css">
.skillDetails {
	height: 100%;
	display: flex;
	flex-direction: column;
}

.skillHeader {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 1rem;
	border-bottom: 1px solid var(--color-border);
}

.skillHeader h2 {
	margin: 0;
}

.detailContainer {
	flex: 1;
	overflow-y: auto;
	padding: 1rem;
}

.detailSection {
	margin-bottom: 2rem;
}

.detailSection h3 {
	margin-top: 0;
	margin-bottom: 1rem;
}
</style>
