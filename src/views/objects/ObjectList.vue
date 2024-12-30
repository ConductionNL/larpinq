<script setup>
import { navigationStore, objectStore } from '../../store/store.js'
</script>

<template>
	<div>
		<div v-if="objects.length > 0">
			<NcListItem v-for="object in objects"
				:key="object.id"
				:name="object.name"
				:bold="false"
				:details="object.objectType"
				:force-display-actions="true">
				<template #icon>
					<Sword v-if="object.objectType === 'ability'" :size="44" />
					<BriefcaseAccountOutline v-else-if="object.objectType === 'character'" :size="44" />
					<AlertDecagram v-else-if="object.objectType === 'condition'" :size="44" />
					<MagicStaff v-else-if="object.objectType === 'effect'" :size="44" />
					<CalendarClock v-else-if="object.objectType === 'event'" :size="44" />
					<BagPersonal v-else-if="object.objectType === 'item'" :size="44" />
					<Account v-else-if="object.objectType === 'player'" :size="44" />
					<SchoolOutline v-else-if="object.objectType === 'skill'" :size="44" />
					<FileDocumentMultiple v-else-if="object.objectType === 'template'" :size="44" />
					<TimelineQuestionOutline v-else :size="44" />
				</template>
				<template #subname>
					{{ new Date(object.created).toLocaleString() }}
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
			Geen relaties gevonden
		</div>		
	</div>
</template>
<script>
import { NcListItem, NcActionButton, NcLoadingIcon } from '@nextcloud/vue'
import TimelineQuestionOutline from 'vue-material-design-icons/TimelineQuestionOutline.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/AccountGroup.vue'
import AlertDecagram from 'vue-material-design-icons/AlertDecagram.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import BagPersonal from 'vue-material-design-icons/BagPersonal.vue'
import Account from 'vue-material-design-icons/Account.vue'
import SchoolOutline from 'vue-material-design-icons/SchoolOutline.vue'
import FileDocumentMultiple from 'vue-material-design-icons/FileDocumentMultiple.vue'

export default {
	name: 'ObjectList',
	components: {
		NcListItem,
		NcActionButton,
		NcLoadingIcon,
		// Icons
		TimelineQuestionOutline,
		Eye,
		Sword,
		BriefcaseAccountOutline,
		AlertDecagram,
		MagicStaff,
		CalendarClock,
		BagPersonal,
		Account,
		SchoolOutline,
		FileDocumentMultiple
	},
	props: {
		objects: {
			type: Array,
			required: true,
			default: () => [],
		},
	}
}
</script>