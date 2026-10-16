// SPDX-License-Identifier: AGPL-3.0-or-later
// SPDX-FileCopyrightText: Conduction B.V. <info@conduction.nl>
//
// LarpingApp store — thin wrapper around @conduction/nextcloud-vue's shared
// object store (createObjectStore), plus the LarpingApp-specific settings
// store. The hand-rolled Pinia object store that previously lived in
// src/store/modules/object.js was replaced by the library's CRUD store as
// part of the Tier-4 manifest migration; CnIndexPage / CnDetailPage drive
// every list/detail page from src/manifest.json against this store.

import { useObjectStore } from './modules/object.js'
import { useSettingsStore } from './modules/settings.js'

/**
 * The LarpingApp schemas that get registered on the shared object store. The
 * value is the default schema slug; the per-install settings (register slug +
 * `<schema>_schema` overrides) take precedence when present.
 */
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

	const config = (await settingsStore.fetchSettings()) || {}
	registerObjectTypes(objectStore, config)

	return { settingsStore, objectStore }
}

/**
 * Register the given types on an object store from the app settings, unless
 * every one of them is registered already.
 *
 * @param {object} objectStore The object store the caller reads from.
 * @param {Array<string>} types The type slugs the caller needs.
 * @return {Promise<void>}
 * @spec openspec/specs/skill-tree-visualization/spec.md
 */
export async function ensureObjectTypes(objectStore, types) {
	const known = Array.isArray(objectStore.objectTypes) ? objectStore.objectTypes : []
	if (types.every((type) => known.includes(type))) {
		return
	}
	const config = (await useSettingsStore().fetchSettings()) || {}
	registerObjectTypes(objectStore, config)
}

/**
 * Register every configured schema in SCHEMA_SLUGS on the object store.
 *
 * @param {object} objectStore The object store to register on.
 * @param {object} config The app settings configuration.
 * @return {void}
 */
function registerObjectTypes(objectStore, config) {
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

export { useSettingsStore }
