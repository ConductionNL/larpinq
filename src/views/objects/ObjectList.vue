<template>
	<div>
		<div v-if="objects && objects.length > 0">
			<NcListItem v-for="object in objects"
				:key="object.id"
				:name="object.name"
				:bold="false"
				:details="object.objectType"
				:force-display-actions="true"
				@click="handleObjectClick(object)">
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
					<NcActionButton @click="handleObjectClick(object)">
						<template #icon>
							<Eye :size="20" />
						</template>
						{{ t('larpingapp', 'Bekijken') }}
					</NcActionButton>
				</template>
			</NcListItem>
		</div>
		<div v-else>
			{{ t('larpingapp', 'Geen relaties gevonden') }}
		</div>
	</div>
</template>

<script>
import { useObjectStore } from '../../store/modules/object.js'
import pinia from '../../pinia.js'
import { NcListItem, NcActionButton } from '@nextcloud/vue'
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
		ChatOutline,
	},
	props: {
		objects: {
			type: Array,
			required: true,
			default: () => [],
		},
	},
	computed: {
		objectStore() {
			return useObjectStore(pinia)
		},
	},
	methods: {
		/**
		 * Renders effects and effect property for an object
		 * @param {Object} object - The object containing effects and effect property
		 * @returns {string} Formatted string of effects
		 */
		renderEffects(object) {
			if (!object?.effects?.length) return t('larpingapp', 'Geen berekende effecten')

			const effectStrings = object.effects.map(effectId => {
				const effectList = this.objectStore.getCollection('effect').results || []
				const effect = effectList.find(e => e.id === effectId)
				if (!effect?.abilities?.length) return null

				return effect.abilities.map(ability => {
					const sign = effect.modification === 'negative' ? '-' : '+'
					return `${ability.name} (${sign}${effect.name.replace(/[^0-9]/g, '')})`
				}).join(', ')
			}).filter(Boolean)

			if (!effectStrings.length) return t('larpingapp', 'Geen berekende effecten')
			return effectStrings.join(', ')
		},
		/**
		 * Handles click on an object list item, navigating via router
		 * @param {Object} object - The clicked object
		 */
		handleObjectClick(object) {
			const routeMap = {
				ability: 'AbilityDetail',
				skill: 'SkillDetail',
				item: 'ItemDetail',
				event: 'EventDetail',
				condition: 'ConditionDetail',
				effect: 'EffectDetail',
				character: 'CharacterDetail',
				player: 'PlayerDetail',
				template: 'TemplateDetail',
			}

			const routeName = routeMap[object.objectType]
			if (routeName) {
				this.$router.push({ name: routeName, params: { id: object.id } })
			} else {
				console.warn('Unknown object type:', object.objectType)
			}
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
