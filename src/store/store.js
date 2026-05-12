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
]

/**
 * Shared object store for all LarpingApp OpenRegister CRUD. The Pinia store id
 * `'larpingapp-objects'` is unique to this app so a future change that mounts
 * both LarpingApp and an embedded OpenRegister sidebar in the same Pinia tree
 * can't collide on the default `'conduction-objects'` id.
 *
 * @type {import('pinia').StoreDefinition}
 */
export const useObjectStore = createObjectStore('larpingapp-objects', {
	baseUrl: generateUrl('/apps/openregister/api/objects'),
})

/**
 * Boot hook called from App.vue. Loads LarpingApp's settings (register slug,
 * per-schema slug overrides), then registers every logical object type the
 * manifest pages reference against the shared lib store.
 *
 * Idempotent — Pinia's `defineStore` is, `fetchSettings()` is safe to re-await,
 * and `registerObjectType()` overwrites with the same payload.
 *
 * @return {Promise<{settingsStore: object, objectStore: object}>}
 */
export async function initializeStores() {
	const settingsStore = useSettingsStore()
	const objectStore = useObjectStore()

	const config = (await settingsStore.fetchSettings()) || {}
	const register = config.register || 'larpingapp'

	for (const slug of SCHEMA_SLUGS) {
		const schema = config[`${slug}_schema`] || slug
		objectStore.registerObjectType(slug, schema, register)
	}

	return { settingsStore, objectStore }
}

export { useSettingsStore }
