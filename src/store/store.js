import { useObjectStore } from './modules/object.js'
import { useSettingsStore } from './modules/settings.js'

const SCHEMA_SLUGS = [
	'character',
	'player',
	'ability',
	'skill',
	'item',
	'condition',
	'effect',
	'event',
	'setting',
]

export async function initializeStores() {
	const settingsStore = useSettingsStore()
	const objectStore = useObjectStore()

	const config = await settingsStore.fetchSettings()

	if (config) {
		for (const slug of SCHEMA_SLUGS) {
			const schemaKey = `${slug}_schema`
			if (config.register && config[schemaKey]) {
				objectStore.registerObjectType(slug, config[schemaKey], config.register)
			}
		}
	}

	return { settingsStore, objectStore }
}

export { useObjectStore, useSettingsStore }
