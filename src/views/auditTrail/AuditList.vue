<script setup>
import { navigationStore, objectStore } from '../../store/store.js'
</script>

<template>
	<div>
		<div v-if="logs.length > 0">
			<NcListItem v-for="auditTrail in logs"
				:key="auditTrail.id"
				:name="new Date(auditTrail.created).toLocaleString()"
				:bold="false"
				:details="auditTrail.action"
				:counter-number="Object.keys(auditTrail.changed).length"
				:force-display-actions="true">
				<template #icon>
					<TimelineQuestionOutline disable-menu
						:size="44" />
				</template>
				<template #subname>
					{{ auditTrail.userName }}
				</template>
				<template #actions>
					<NcActionButton @click="objectStore.setAuditTrailItem(auditTrail); navigationStore.setModal('viewObjectAuditTrail')">
						<template #icon>
							<Eye :size="20" />
						</template>
						View details
					</NcActionButton>
				</template>
			</NcListItem>
		</div>
		<div v-else>
			Geen logging gevonden
		</div>		
	</div>
</template>
<script>
import { NcListItem, NcActionButton, NcLoadingIcon } from '@nextcloud/vue'
import TimelineQuestionOutline from 'vue-material-design-icons/TimelineQuestionOutline.vue'
import Eye from 'vue-material-design-icons/Eye.vue'

export default {
	name: 'AuditList',
	components: {
		NcListItem,
		NcActionButton,
		NcLoadingIcon,
		// Icons
		TimelineQuestionOutline,
		Eye,
	},
	props: {
		logs: {
			type: Array,
			required: true,
			default: () => [],
		},
	}
}
</script>