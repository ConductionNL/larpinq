/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Bulk edit on the Characters index (characters-status-and-bulk-edit): set
 * status, type or world on the selected characters. Each character is written
 * with its own PATCH through the OpenRegister objects API, so every write
 * passes the same rules as a single edit (RBAC, the requirement listener, the
 * unique-holder check). Successes and refusals are reported per character.
 *
 * @spec openspec/specs/character-management/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

const OBJECTS = '/apps/openregister/api/objects/larpinq'

/** The fields a bulk edit may set. */
export const BULK_FIELDS = ['status', 'type', 'setting']

/**
 * The PATCH body: only the fields the user chose a value for.
 *
 * @param {object} choices Field to chosen value; empty means leave unchanged.
 * @return {object} The body.
 *
 * @spec openspec/specs/character-management/spec.md
 */
export function bulkBody(choices) {
	const body = {}
	for (const field of BULK_FIELDS) {
		const value = choices?.[field]
		if (value !== undefined && value !== null && value !== '') {
			body[field] = value
		}
	}
	return body
}

/**
 * The headers of every request.
 *
 * @return {object} The headers.
 */
function headers() {
	return {
		requesttoken: getRequestToken(),
		Accept: 'application/json',
		'Content-Type': 'application/json',
	}
}

/**
 * The reason OpenRegister gave for a refused write.
 *
 * @param {Response} response The response.
 * @return {Promise<string>} The reason, or the HTTP status.
 */
async function reasonOf(response) {
	try {
		const body = await response.json()
		const errors =
			body?.errors && typeof body.errors === 'object' ? body.errors : body
		const reason = errors?.message ?? body?.message ?? body?.error
		if (reason) {
			return String(reason)
		}
	} catch {
		// No JSON body: fall through to the status.
	}
	return `HTTP ${response.status}`
}

/**
 * Write the choices to each selected character, one PATCH each, in order
 * (REQ-CSB-003, REQ-CSB-004). A refused character does not stop the others.
 *
 * @param {Array<string>} ids The selected character ids.
 * @param {object} choices The chosen values.
 * @param {object} [names] Character names by id, for the report.
 * @param {(count: number) => void} [onProgress] Called with the number written so far.
 * @return {Promise<{changed: Array<string>, refused: Array<{id: string, name: string, reason: string}>}>} The report.
 *
 * @spec openspec/specs/character-management/spec.md
 */
export async function applyBulkEdit(
	ids,
	choices,
	names = {},
	onProgress = () => {},
) {
	const body = JSON.stringify(bulkBody(choices))
	const changed = []
	const refused = []
	for (const [index, id] of (ids || []).entries()) {
		let response
		try {
			response = await fetch(
				generateUrl(`${OBJECTS}/character/${encodeURIComponent(id)}`),
				{ method: 'PATCH', headers: headers(), body },
			)
		} catch (error) {
			refused.push({ id, name: names[id] || id, reason: String(error) })
			onProgress(index + 1)
			continue
		}
		if (response.ok) {
			changed.push(id)
		} else {
			refused.push({
				id,
				name: names[id] || id,
				reason: await reasonOf(response),
			})
		}
		onProgress(index + 1)
	}
	return { changed, refused }
}

/**
 * The names of the selected characters, read as the user.
 *
 * @param {Array<string>} ids The character ids.
 * @return {Promise<object>} Names by id; an unreadable character is left out.
 *
 * @spec openspec/specs/character-management/spec.md
 */
export async function fetchCharacterNames(ids) {
	const names = {}
	for (const id of ids || []) {
		const response = await fetch(
			generateUrl(`${OBJECTS}/character/${encodeURIComponent(id)}`),
			{ headers: headers() },
		)
		if (response.ok) {
			const character = await response.json()
			names[id] = String(character?.name ?? id)
		}
	}
	return names
}

/**
 * The worlds a character can be moved to.
 *
 * @return {Promise<Array<{id: string, label: string}>>} The worlds.
 *
 * @spec openspec/specs/character-management/spec.md
 */
export async function fetchWorlds() {
	const response = await fetch(generateUrl(`${OBJECTS}/setting?_limit=500`), {
		headers: headers(),
	})
	if (!response.ok) {
		return []
	}
	const body = await response.json()
	return (Array.isArray(body?.results) ? body.results : []).map((world) => ({
		id: String(world.id),
		label: String(world.name ?? world.id),
	}))
}
