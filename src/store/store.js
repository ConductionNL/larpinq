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
	'xpAward',
	'attendance',
]

/**
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-7
 */
export async function initializeStores() {
	const settingsStore = useSettingsStore()
	const objectStore = useObjectStore()

	const config = await settingsStore.fetchSettings()

	if (config) {
		for (const slug of SCHEMA_SLUGS) {
			const schemaKey = `${slug}_schema`
			const registerKey = `${slug}_register`
			// Prefer the per-type register id (config.<type>_register); fall back
			// to the shared top-level register. Reading config.register alone
			// broke list fetches whenever the per-type id diverged from it.
			const register = config[registerKey] || config.register
			if (register && config[schemaKey]) {
				objectStore.registerObjectType(slug, config[schemaKey], register)
			}
		}
	}

	return { settingsStore, objectStore }
}

export { useObjectStore, useSettingsStore }
