<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="abilityDetails">
		<div class="abilityHeader">
			<h2>{{ objectStore.getActiveObject('ability')?.name }}</h2>
			<div class="abilityActions">
				<NcActions>
					<NcActionButton @click="objectStore.setActiveObject('ability', objectStore.getActiveObject('ability')); navigationStore.setModal('editAbility')">
						<template #icon>
							<Pencil :size="20" />
						</template>
						Bewerken
					</NcActionButton>
					<NcActionButton @click="objectStore.setActiveObject('ability', objectStore.getActiveObject('ability')); navigationStore.setDialog('deleteAbility')">
						<template #icon>
							<TrashCanOutline :size="20" />
						</template>
						Verwijderen
					</NcActionButton>
				</NcActions>
			</div>
		</div>

		<div class="abilityContent">
			<div class="abilityDescription">
				<h3>Beschrijving</h3>
				<p>{{ objectStore.getActiveObject('ability')?.description }}</p>
			</div>

			<div class="abilityEffects">
				<h3>Effecten</h3>
				<ObjectList :objects="objectStore.getActiveObject('ability')?.effects || []" />
			</div>

			<div class="abilityCharacters">
				<h3>Karakters met deze vaardigheid</h3>
				<div v-if="objectStore.getRelatedObjects('ability', 'character')?.length > 0" class="characterList">
					<NcListItem v-for="character in objectStore.getRelatedObjects('ability', 'character')"
						:key="character.id"
						:title="character.name"
						@click="objectStore.setActiveObject('character', character); navigationStore.setSelected('characters')">
						<template #icon>
							<Account :size="20" />
						</template>
					</NcListItem>
				</div>
				<div v-else class="noCharacters">
					<p>Geen karakters gevonden met deze vaardigheid.</p>
				</div>
			</div>

			<div class="abilityAuditLog">
				<h3>Audit log</h3>
				<AuditTable :audit-log="objectStore.getAuditLog('ability')" />
			</div>
		</div>
	</div>
</template>

<script>
import {
	NcActions,
	NcActionButton,
	NcListItem,
} from '@nextcloud/vue'

import Account from 'vue-material-design-icons/Account.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'

import ObjectList from '../../components/ObjectList.vue'
import AuditTable from '../auditTrail/AuditTable.vue'

export default {
	name: 'AbilityDetails',
	components: {
		NcActions,
		NcActionButton,
		NcListItem,
		Account,
		Pencil,
		TrashCanOutline,
		ShieldSwordOutline,
		ObjectList,
		AuditTable,
	},
}
</script>

<style scoped>
.abilityDetails {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	padding: 1rem;
}

.abilityHeader {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.abilityContent {
	display: flex;
	flex-direction: column;
	gap: 2rem;
}

.abilityDescription,
.abilityEffects,
.abilityCharacters,
.abilityAuditLog {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.characterList {
	display: flex;
	flex-direction: column;
}

.noCharacters {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
