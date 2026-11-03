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

import { generateUrl } from '@nextcloud/router'
import { jsonHeaders } from './requestHeaders.js'

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
 * The message of a refusal body: the nested `errors` object's message, else
 * the body's own message or error.
 *
 * @param {object|string|null} body The parsed response body.
 * @return {string|number|undefined} The message, or undefined.
 */
function refusalMessage(body) {
	const errors =
		body?.errors && typeof body.errors === 'object' ? body.errors : body
	return errors?.message ?? body?.message ?? body?.error
}

/**
 * The reason OpenRegister gave for a refused write.
 *
 * @param {Response} response The response.
 * @return {Promise<string>} The reason, or the HTTP status.
 */
async function reasonOf(response) {
	try {
		const reason = refusalMessage(await response.json())
		if (reason) {
			return String(reason)
		}
	} catch {
		// No JSON body: fall through to the status.
	}
	return `HTTP ${response.status}`
}

/**
 * PATCH one character.
 *
 * @param {string} id The character id.
 * @param {string} body The JSON body.
 * @return {Promise<string|null>} Null when written, else the reason it was refused.
 */
async function patchCharacter(id, body) {
	let response
	try {
		response = await fetch(
			generateUrl(`${OBJECTS}/character/${encodeURIComponent(id)}`),
			{ method: 'PATCH', headers: jsonHeaders(), body },
		)
	} catch (error) {
		return String(error)
	}
	return response.ok ? null : reasonOf(response)
}

/**
 * Write one character of a bulk edit and add the outcome to the run's report.
 *
 * @param {object} run The run: `body`, `names`, `changed` and `refused`.
 * @param {string} id The character id.
 * @return {Promise<void>}
 */
async function writeCharacter(run, id) {
	const reason = await patchCharacter(id, run.body)
	if (reason === null) {
		run.changed.push(id)
		return
	}
	run.refused.push({ id, name: run.names[id] || id, reason })
}

/**
 * A fresh bulk-edit run: the PATCH body, the names and an empty report.
 *
 * @param {object} choices The chosen values.
 * @param {object} names Character names by id.
 * @return {object} The run.
 */
function bulkRun(choices, names) {
	return {
		body: JSON.stringify(bulkBody(choices)),
		names,
		changed: [],
		refused: [],
	}
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
	const run = bulkRun(choices, names)
	for (const [index, id] of (ids || []).entries()) {
		await writeCharacter(run, id)
		onProgress(index + 1)
	}
	return { changed: run.changed, refused: run.refused }
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
			{ headers: jsonHeaders() },
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
		headers: jsonHeaders(),
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
