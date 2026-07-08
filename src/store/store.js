import { useObjectStore } from './modules/object.js'
import { useSettingsStore } from './modules/settings.js'

const SCHEMA_SLUGS = [
	'character',
	'player',
	'ability',
	'skill',
	'larping_item',
	'condition',
	'effect',
	'larping_event',
	'setting',
	'xpAward',
	'attendance',
]

// A few schemas are namespaced at the slug level to avoid a global OpenRegister
// slug collision (`item`/`event` clash with other apps' schemas), so their slug
// (used by the manifest + form schema resolution) differs from the config-key
// the backend exposes (which is keyed by the register's component key). Map
// slug → config-key so we register the object type under the slug the rest of
// the app fetches by, while still reading the schema id from the right config
// entry. Slugs not listed here use their own name as the config key.
const CONFIG_KEY_BY_SLUG = {
	larping_item: 'item',
	larping_event: 'event',
}

/**
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-7
 */
export async function initializeStores() {
	const settingsStore = useSettingsStore()
	const objectStore = useObjectStore()

	const config = await settingsStore.fetchSettings()

	if (config) {
		for (const slug of SCHEMA_SLUGS) {
			const configKey = CONFIG_KEY_BY_SLUG[slug] || slug
			const schemaKey = `${configKey}_schema`
			const registerKey = `${configKey}_register`
			// Prefer the per-type register id (config.<type>_register); fall back
			// to the shared top-level register. Reading config.register alone
			// broke list fetches whenever the per-type id diverged from it.
			const register = config[registerKey] || config.register
			if (register && config[schemaKey]) {
				// Register under the slug the manifest / form resolution uses.
				objectStore.registerObjectType(slug, config[schemaKey], register)
				// Back-compat: also register under the config-key name so any
				// consumer still referencing the un-namespaced type resolves.
				if (configKey !== slug) {
					objectStore.registerObjectType(configKey, config[schemaKey], register)
				}
			}
		}
	}

	return { settingsStore, objectStore }
}

export { useObjectStore, useSettingsStore }
