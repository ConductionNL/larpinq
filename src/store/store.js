// SPDX-License-Identifier: AGPL-3.0-or-later
// SPDX-FileCopyrightText: Conduction B.V. <info@conduction.nl>
//
// LarpingApp store — thin wrapper around @conduction/nextcloud-vue's shared
// object store (createObjectStore), plus the LarpingApp-specific settings
// store. The hand-rolled Pinia object store that previously lived in
// src/store/modules/object.js was replaced by the library's CRUD store as
// part of the Tier-4 manifest migration; CnIndexPage / CnDetailPage drive
// every list/detail page from src/manifest.json against this store.

import { generateUrl } from '@nextcloud/router'
import { createObjectStore } from '@conduction/nextcloud-vue'
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
	const register = config.register || 'larpingapp'

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

export { useSettingsStore }
