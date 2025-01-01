<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'viewAuditTrail'"
		:name="`Audit trail #${objectStore.auditTrailItem.id}`"
		id="audit-trail-modal"
		size="large"
		:can-close="false">

		<div class="audit-item-details">
			<p><strong>Action:</strong> {{ objectStore.auditTrailItem.action }}</p>
			<p><strong>User:</strong> {{ objectStore.auditTrailItem.userName }} ({{ objectStore.auditTrailItem.user }})</p>
			<p><strong>Session:</strong> {{ objectStore.auditTrailItem.session }}</p>
			<p><strong>IP Address:</strong> {{ objectStore.auditTrailItem.ipAddress }}</p>
			<p><strong>Created:</strong> {{ new Date(objectStore.auditTrailItem.created).toLocaleString() }}</p>
		</div>

		<div v-if="objectStore.auditTrailItem.changed" class="audit-trail-changes">
			<h4>Changes:</h4>
			<div class="audit-trail-table-container">
				<table class="audit-trail-table" style="width: 100%">
					<colgroup>
						<col style="width: 10%">
						<col style="width: 40%">
						<col style="width: 40%">
					</colgroup>
					<thead>
						<tr>
							<th><h5>Field</h5></th>
							<th><h5>Old Value</h5></th>
							<th><h5>New Value</h5></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(change, key) in objectStore.auditTrailItem.changed" :key="key">
							<td><strong>{{ key }}</strong></td>
							<td>
								<div :title="formatValue(change.old)">
									{{ truncateValue(formatValue(change.old)) }}
								</div>
							</td>
							<td>
								<div :title="formatValue(change.new)">
									{{ truncateValue(formatValue(change.new)) }}
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		
		<template #actions>
			<NcButton
				@click="navigationStore.setModal(false)">
				<template #icon>
					<Cancel :size="20" />
				</template>
				Close
			</NcButton>			
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
} from '@nextcloud/vue'

import Cancel from 'vue-material-design-icons/Cancel.vue'

export default {
	name: 'ViewAuditTrail',
	components: {
		NcDialog,
		NcButton,
		// Icons
		Cancel,
	},	
	methods: {
		/**
		 * Format value based on its type
		 * @param {any} value - The value to format
		 * @returns {string} Formatted value
		 */
		formatValue(value) {
			if (value === null || value === undefined) {
				return 'N/A' // Handle null or undefined
			} else if (typeof value === 'object') {
				return JSON.stringify(value, null, 2) // Format JSON objects
			} else if (typeof value === 'boolean') {
				return value ? 'True' : 'False' // Format booleans
			}
			return String(value) // Convert to string for consistent handling
		},

		/**
		 * Truncate value if it exceeds max length
		 * @param {string} value - The value to truncate
		 * @param {number} maxLength - Maximum length before truncation
		 * @returns {string} Truncated value
		 */
		truncateValue(value, maxLength = 50) {
			return value.length > maxLength ? value.substring(0, maxLength) + '...' : value
		},

		/**
		 * Check if value should be truncated
		 * @param {string} value - The value to check
		 * @param {number} maxLength - Maximum length before truncation
		 * @returns {boolean} Whether value should be truncated
		 */
		shouldTruncate(value, maxLength = 50) {
			return value.length > maxLength
		}
	}
}
</script>

<style scoped>
.audit-trail-table td {
	max-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
