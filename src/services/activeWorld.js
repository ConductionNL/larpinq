/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The active world: one campaign world a user narrows the lists to, or all
 * worlds. The choice is stored per user through larpinq's preferences API
 * (`active-world`), handed to the first paint as the `activeWorld` initial
 * state, and written into the page workspace (`cnWorkspaceContext`) under
 * `activeWorld`. Index pages and dashboard widgets of world-scoped schemas
 * carry the optional token `@workspace.activeWorld?` in their list filter, so
 * OpenRegister narrows the query itself and an unset world drops the filter.
 *
 * @spec openspec/specs/setting-management/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { reactive, ref } from 'vue'

/** The workspace key the list filters read. */
export const WORLD_KEY = 'activeWorld'

/** The list filter value every world-scoped list carries. */
export const WORLD_FILTER = `@workspace.${WORLD_KEY}?`

/** The preferences API key the choice is stored under. */
export const PREFERENCE_KEY = 'active-world'

/** The index pages of schemas that carry a `setting` (world) property. */
export const WORLD_SCOPED_PAGES = [
	'Characters',
	'Abilities',
	'Skills',
	'Items',
	'Conditions',
	'Effects',
	'Events',
]

/**
 * The active world, shared by every switcher on the page: `id` is the world
 * UUID or '' for all worlds, `checked` whether the stored world was checked.
 */
export const activeWorld = reactive({ id: '', checked: false })

/**
 * The app-wide workspace App.vue provides to every page. The dashboard
 * provides its own workspace, so the switcher writes the choice into both.
 */
export const appWorkspace = ref({})

/**
 * Write a world into a workspace, or remove it for all worlds. A ref gets a
 * new object, so the lists watching the workspace re-fetch.
 *
 * @param {{value: object}|object|null} bag The workspace (a ref or a plain object).
 * @param {string} worldId The world UUID, or '' for all worlds.
 * @return {void}
 *
 * @spec openspec/specs/setting-management/spec.md
 */
export function applyWorld(bag, worldId) {
	if (!bag || typeof bag !== 'object') {
		return
	}
	const isRef = 'value' in bag
	const next = { ...((isRef ? bag.value : bag) || {}) }
	if (worldId) {
		next[WORLD_KEY] = worldId
	} else {
		delete next[WORLD_KEY]
	}
	if (isRef) {
		bag.value = next
		return
	}
	if (!worldId) {
		delete bag[WORLD_KEY]
	}
	Object.assign(bag, next)
}

/**
 * Store the choice through the preferences API.
 *
 * @param {string} worldId The world UUID, or '' for all worlds.
 * @return {Promise<boolean>} Whether it was stored.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
export async function saveWorld(worldId) {
	const response = await fetch(
		generateUrl(`/apps/larpinq/api/preferences/${PREFERENCE_KEY}`),
		{
			method: 'PUT',
			headers: {
				requesttoken: getRequestToken(),
				'Content-Type': 'application/json',
				Accept: 'application/json',
			},
			body: JSON.stringify({ value: worldId || '' }),
		},
	)
	return response.ok
}

/**
 * An OpenRegister URL on the larpinq worlds (the `setting` schema).
 *
 * @param {string} query The path and query after the schema.
 * @return {string} The URL.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
function worldsUrl(query) {
	return generateUrl(`/apps/openregister/api/objects/larpinq/setting${query}`)
}

/**
 * Check a stored world: an active world stays, an archived or missing one
 * falls back to all worlds and the stored choice is cleared.
 *
 * @param {string} worldId The stored world UUID, or ''.
 * @return {Promise<string>} The world to use, or '' for all worlds.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
export async function resolveWorld(worldId) {
	if (!worldId) {
		return ''
	}
	const response = await fetch(worldsUrl(`/${encodeURIComponent(worldId)}`), {
		headers: { requesttoken: getRequestToken(), Accept: 'application/json' },
	})
	let world = null
	if (response.ok) {
		try {
			world = await response.json()
		} catch {
			world = null
		}
	}
	if (world && world.status !== 'archived') {
		return worldId
	}
	await saveWorld('')
	return ''
}

/**
 * The active worlds a user can narrow to, by name.
 *
 * @return {Promise<Array<{id: string, label: string}>>} The worlds.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
export async function listWorlds() {
	const response = await fetch(worldsUrl('?status=active&_limit=200'), {
		headers: { requesttoken: getRequestToken(), Accept: 'application/json' },
	})
	if (!response.ok) {
		return []
	}
	const body = await response.json()
	const results = Array.isArray(body?.results) ? body.results : []
	return results
		.filter((w) => w && w.id)
		.map((w) => ({ id: String(w.id), label: String(w.name || w.id) }))
		.sort((a, b) => a.label.localeCompare(b.label))
}

/**
 * Start from the world the page was served with (the `activeWorld` initial
 * state), so lists are narrowed before their first fetch.
 *
 * @param {string} worldId The stored world UUID, or ''.
 * @return {{value: object}} The app-wide workspace, for App.vue to provide.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
export function startActiveWorld(worldId) {
	activeWorld.id = typeof worldId === 'string' ? worldId : ''
	activeWorld.checked = false
	applyWorld(appWorkspace, activeWorld.id)
	return appWorkspace
}
