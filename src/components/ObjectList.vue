<template>
	<div>
		<div v-if="objects.length > 0">
			<NcListItem v-for="object in objects"
				:key="object.id"
				:name="getObjectName(object)"
				:bold="false"
				:details="getObjectDetails(object)"
				:force-display-actions="true"
				@click="handleObjectClick(object)">
				<template #icon>
					<ShieldSwordOutline v-if="getObjectType(object) === 'ability'" :size="44" />
					<BriefcaseAccountOutline v-else-if="getObjectType(object) === 'character'" :size="44" />
					<EmoticonSickOutline v-else-if="getObjectType(object) === 'condition'" :size="44" />
					<MagicStaff v-else-if="getObjectType(object) === 'effect'" :size="44" />
					<CalendarMonthOutline v-else-if="getObjectType(object) === 'event'" :size="44" />
					<Sword v-else-if="getObjectType(object) === 'item'" :size="44" />
					<Account v-else-if="getObjectType(object) === 'player'" :size="44" />
					<SwordCross v-else-if="getObjectType(object) === 'skill'" :size="44" />
					<ChatOutline v-else-if="getObjectType(object) === 'template'" :size="44" />
					<TimelineQuestionOutline v-else :size="44" />
				</template>
				<template #subname>
					<div class="object-info">
						<div>{{ renderEffects(object) }}</div>
						<div v-if="object['@self.schema']">
							<span v-for="field in getDisplayableFields(object)" :key="field.key">
								{{ field.title }}: {{ formatFieldValue(object[field.key], field) }}
							</span>
						</div>
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
import { NcListItem, NcActionButton } from '@nextcloud/vue'
import { objectStore, navigationStore } from '../store/store.js'

// Icons
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

/**
 * @component ObjectList
 * @category Components
 * @package
 * @author Ruben Linde
 * @copyright 2024 Ruben Linde
 * @license AGPL-3.0
 * @version 1.0.0
 * @link https://github.com/MetaProvide/larpingapp
 *
 * A generic list component that dynamically renders objects based on their schema.
 * Supports various object types including abilities, characters, conditions, effects,
 * events, items, players, skills, and templates.
 */
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
		/**
		 * Array of objects to display in the list
		 * @type {Array<object>}
		 */
		objects: {
			type: Array,
			required: true,
			default: () => [],
		},
	},
	methods: {
		/**
		 * Get formatted details for an object based on its schema
		 * @param {object} object - The object to get details for
		 * @return {string} Formatted details string
		 */
		getObjectDetails(object) {
			if (!object['@self.schema']) {
				return object.objectType
			}

			const details = []
			for (const [key, field] of Object.entries(object['@self.schema'])) {
				if (this.shouldDisplayField(key, field) && object[key]) {
					details.push(`${field.title}: ${this.formatFieldValue(object[key], field)}`)
				}
			}
			return details.length ? details.join(' | ') : object.objectType
		},

		/**
		 * Get displayable fields from an object's schema
		 * @param {object} object - The object to get fields from
		 * @return {Array} Array of displayable fields with their keys
		 */
		getDisplayableFields(object) {
			if (!object['@self.schema']) {
				return []
			}

			return Object.entries(object['@self.schema'])
				.filter(([key, field]) => this.shouldDisplayField(key, field))
				.map(([key, field]) => ({ ...field, key }))
		},

		/**
		 * Determine if a field should be displayed in the list view
		 * @param {string} key - The field key
		 * @param {object} field - The field schema
		 * @return {boolean} Whether the field should be displayed
		 */
		shouldDisplayField(key, field) {
			// Skip internal fields and complex objects
			const skipFields = ['id', 'name', 'objectType', '@self', 'effects']
			return !skipFields.includes(key)
				&& field.type !== 'object'
				&& field.type !== 'array'
				&& !field.hidden
		},

		/**
		 * Format a field value based on its schema type
		 * @param {any} value - The field value
		 * @param {object} field - The field schema
		 * @return {string} Formatted value
		 */
		formatFieldValue(value, field) {
			if (value === null || value === undefined) {
				return ''
			}

			switch (field.type) {
			case 'boolean':
				return value ? 'Ja' : 'Nee'
			case 'date':
				return new Date(value).toLocaleDateString()
			case 'datetime':
				return new Date(value).toLocaleString()
			default:
				return String(value)
			}
		},

		/**
		 * Renders effects and effect property for an object
		 * @param {object} object - The object containing effects and effect property
		 * @return {string} Formatted string of effects or object description
		 */
		renderEffects(object) {
			// If the object has a description, show that
			if (object.description) {
				return object.description
			}

			// If the object has effects, show them
			if (object?.effects?.length) {
				const effectStrings = object.effects.map(effectId => {
					const effect = objectStore.getCollection('effect').results.find(e => e.id === effectId)
					if (!effect?.abilities?.length) {
						return null
					}

					return effect.abilities.map(ability => {
						const sign = effect.modification === 'negative' ? '-' : '+'
						return `${ability.name} (${sign}${effect.name.replace(/[^0-9]/g, '')})`
					}).join(', ')
				}).filter(Boolean)

				return effectStrings.length ? effectStrings.join(', ') : object.name || 'No description'
			}

			// If no effects or description, show the name or a default message
			return object.name || 'No description'
		},

		/**
		 * Determines the object type for icon display
		 * @param {object} object - The object to determine type for
		 * @return {string} The object type
		 */
		getObjectType(object) {
			// If objectType is already set, use it
			if (object.objectType) {
				return object.objectType
			}

			// Try to determine type from schema
			if (object['@self.schema']?.title) {
				const schemaTitle = object['@self.schema'].title.toLowerCase()
				if (schemaTitle.includes('skill')) return 'skill'
				if (schemaTitle.includes('condition')) return 'condition'
				if (schemaTitle.includes('event')) return 'event'
				if (schemaTitle.includes('item')) return 'item'
				if (schemaTitle.includes('effect')) return 'effect'
				if (schemaTitle.includes('ability')) return 'ability'
				if (schemaTitle.includes('player')) return 'player'
				if (schemaTitle.includes('character')) return 'character'
				if (schemaTitle.includes('template')) return 'template'
			}

			// Default to unknown type
			return 'unknown'
		},

		/**
		 * Handles click on an object list item
		 * @param {object} object - The clicked object
		 */
		handleObjectClick(object) {
			// Set the object in the appropriate store
			switch (object.objectType) {
			case 'ability':
				objectStore.setActiveObject('ability', object)
				navigationStore.setSelected('abilities')
				break
			case 'skill':
				objectStore.setActiveObject('skill', object)
				navigationStore.setSelected('skills')
				break
			case 'item':
				objectStore.setActiveObject('item', object)
				navigationStore.setSelected('items')
				break
			case 'event':
				objectStore.setActiveObject('event', object)
				navigationStore.setSelected('events')
				break
			case 'condition':
				objectStore.setActiveObject('condition', object)
				navigationStore.setSelected('conditions')
				break
			case 'effect':
				objectStore.setActiveObject('effect', object)
				navigationStore.setSelected('effects')
				break
			case 'character':
				objectStore.setActiveObject('character', object)
				navigationStore.setSelected('characters')
				break
			case 'player':
				objectStore.setActiveObject('player', object)
				navigationStore.setSelected('players')
				break
			default:
				console.warn('Unknown object type:', object.objectType)
			}
		},

		/**
		 * Gets the display name for an object, checking various possible name properties
		 * @param {object} object - The object to get the name from
		 * @return {string} The display name
		 */
		getObjectName(object) {
			// Debug log
			console.log('Object in getObjectName:', {
				object,
				hasName: 'name' in object,
				hasTitle: 'title' in object,
				name: object.name,
				title: object.title,
				id: object.id,
				objectType: object.objectType,
				schema: object['@self.schema'],
			})

			// Check for name or title in the object itself
			if (object.name) return object.name
			if (object.title) return object.title

			// If we have an ID and object type, use that
			if (object.id && object.objectType) {
				return `${object.objectType.charAt(0).toUpperCase() + object.objectType.slice(1)} ${object.id}`
			}

			// Check schema title if available
			if (object['@self.schema']?.title) {
				return `${object['@self.schema'].title} ${object.id || ''}`
			}

			// Fallback to ID or Unknown
			return object.id ? `Object ${object.id}` : 'Unknown Object'
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
