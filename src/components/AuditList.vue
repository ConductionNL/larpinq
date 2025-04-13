<template>
	<div>
		<div v-if="logs.length > 0">
			<NcListItem v-for="auditTrail in logs"
				:key="auditTrail.id"
				:name="new Date(auditTrail.created).toLocaleString()"
				:bold="false"
				:details="auditTrail.action"
				:counter-number="Object.keys(auditTrail.changed).length"
				:force-display-actions="true"
				@click="objectStore.setAuditTrailItem(auditTrail); navigationStore.setModal('viewAuditTrail')">
				<template #icon>
					<TimelineQuestionOutline disable-menu :size="44" />
				</template>
				<template #subname>
					{{ auditTrail.userName }}
				</template>
				<template #actions>
					<NcActionButton>
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
import { NcListItem, NcActionButton } from '@nextcloud/vue'
import { objectStore, navigationStore } from '../store/store.js'
import TimelineQuestionOutline from 'vue-material-design-icons/TimelineQuestionOutline.vue'
import Eye from 'vue-material-design-icons/Eye.vue'

/**
 * @component AuditList
 * @category Components
 * @package LarpingApp
 * @author Ruben Linde
 * @copyright 2024 Ruben Linde
 * @license AGPL-3.0
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 */
export default {
	name: 'AuditList',
	components: {
		NcListItem,
		NcActionButton,
		// Icons
		TimelineQuestionOutline,
		Eye,
	},
	props: {
		/**
		 * Array of audit log entries
		 * @type {Array<Object>}
		 */
		logs: {
			type: Array,
			required: true,
			default: () => [],
		},
	},
}
</script>

<style scoped>
.audit-list {
	margin-top: 1rem;
}
</style> 