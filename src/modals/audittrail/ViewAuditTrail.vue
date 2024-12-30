<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcDialog v-if="navigationStore.modal === 'viewAuditTrail'"
		name="Audit trail"
		size="large"
		:can-close="false">
		<p>{{ objectStore.auditTrailItem.action }}</p>

		<template #actions>
			<NcButton
				@click="navigationStore.setDialog(false)">
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
}
</script>
