import { useObjectStore } from './modules/object.js'
import { useSettingsStore } from './modules/settings.js'

import pinia from '../pinia.js'

/**
 * Initialize stores and register object types from settings.
 *
 * Fetches settings from the backend to get register/schema IDs,
 * then registers each object type with the generic object store.
 *
 * @return {Promise<{ settingsStore, objectStore }>}
 */
export async function initializeStores() {
	const settingsStore = useSettingsStore(pinia)
	const objectStore = useObjectStore(pinia)

	const config = await settingsStore.fetchSettings()

	if (config) {
		if (config.register && config.character_schema) {
			objectStore.registerObjectType('character', config.character_schema, config.register)
		}
		if (config.register && config.player_schema) {
			objectStore.registerObjectType('player', config.player_schema, config.register)
		}
		if (config.register && config.ability_schema) {
			objectStore.registerObjectType('ability', config.ability_schema, config.register)
		}
		if (config.register && config.skill_schema) {
			objectStore.registerObjectType('skill', config.skill_schema, config.register)
		}
		if (config.register && config.item_schema) {
			objectStore.registerObjectType('item', config.item_schema, config.register)
		}
		if (config.register && config.condition_schema) {
			objectStore.registerObjectType('condition', config.condition_schema, config.register)
		}
		if (config.register && config.effect_schema) {
			objectStore.registerObjectType('effect', config.effect_schema, config.register)
		}
		if (config.register && config.event_schema) {
			objectStore.registerObjectType('event', config.event_schema, config.register)
		}
		if (config.register && config.setting_schema) {
			objectStore.registerObjectType('setting', config.setting_schema, config.register)
		}
	}

	return { settingsStore, objectStore }
}

// Re-export store constructors for direct use in components
export { useObjectStore } from './modules/object.js'
export { useSettingsStore } from './modules/settings.js'
