<script setup>
import { effectStore, abilityStore } from '../../store/store.js'
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
					<ShieldSwordOutline v-if="object.objectType === 'ability'" :size="44" />
					<BriefcaseAccountOutline v-else-if="object.objectType === 'character'" :size="44" />
					<EmoticonSickOutline v-else-if="object.objectType === 'condition'" :size="44" />
					<MagicStaff v-else-if="object.objectType === 'effect'" :size="44" />
					<CalendarMonthOutline v-else-if="object.objectType === 'event'" :size="44" />
					<Sword v-else-if="object.objectType === 'item'" :size="44" />
					<Account v-else-if="object.objectType === 'player'" :size="44" />
					<SwordCross v-else-if="object.objectType === 'skill'" :size="44" />
					<ChatOutline v-else-if="object.objectType === 'template'" :size="44" />
					<TimelineQuestionOutline v-else :size="44" />
				</template>
				<template #subname>
					<div class="object-info">
						<div>{{ renderEffects(object) }}</div>
					</div>
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
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'
import Account from 'vue-material-design-icons/Account.vue'
import ChatOutline from 'vue-material-design-icons/ChatOutline.vue'

export default {
	name: 'ObjectList',
	components: {
		NcListItem,
		NcActionButton,
		NcLoadingIcon,
		// Icons
		TimelineQuestionOutline,
		Eye,
		ShieldSwordOutline,
		BriefcaseAccountOutline,
		EmoticonSickOutline,
		MagicStaff,
		CalendarMonthOutline,
		Sword,
		SwordCross,
		Account,
		ChatOutline
	},
	props: {
		objects: {
			type: Array,
			required: true,
			default: () => [],
		},
	},
	methods: {
		/**
		 * Renders effects and effect property for an object
		 * @param {Object} object - The object containing effects and effect property
		 * @returns {string} Formatted string of effects
		 */
		renderEffects(object) {
			if (!object?.effects?.length) return 'No calculated effects'

			const effectStrings = object.effects.map(effectId => {
				const effect = effectStore.effectList.find(e => e.id === effectId)
				if (!effect?.abilities?.length) return null

				return effect.abilities.map(ability => {
					const sign = effect.modification === 'negative' ? '-' : '+'
					return `${ability.name} (${sign}${effect.name.replace(/[^0-9]/g, '')})`
				}).join(', ')
			}).filter(Boolean)

			if (!effectStrings.length) return 'No calculated effects'
			return effectStrings.join(', ')
		},
	},
}
</script>

<style scoped>
.object-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
}
</style>