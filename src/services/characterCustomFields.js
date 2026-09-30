/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Extra character fields (characters-custom-fields): the rows the Extra
 * fields tab shows for one character, and the values it saves.
 *
 * Definitions are `larping_character_field` objects; values live on the
 * character in `customFields` (the player may read them) and
 * `customFieldsPrivate` (game masters only). OpenRegister strips what the
 * user may not read, so a player never receives the private definitions or
 * values, and this file needs no role check of its own.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

const OBJECTS = '/apps/openregister/api/objects/larpinq'

/** The most definitions read for one sheet. */
const MAX_DEFINITIONS = 500

/**
 * The rows of a character's sheet.
 *
 * A definition applies when it has no world or the character's world; a
 * world's own definition wins over a global one with the same key. Rows are
 * sorted on order, then label.
 *
 * @param {Array<object>} definitions The definitions the user may read.
 * @param {object} character The character as the user may read it.
 * @return {{rows: Array<object>, orphans: Array<{key: string, value: unknown}>, canEditPrivate: boolean, values: object, privateValues: object|null}} The sheet.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
export function sheetFrom(definitions, character) {
	const world = character?.setting || ''
	const values = objectOr(character?.customFields, {})
	const privateValues = objectOr(character?.customFieldsPrivate, null)
	const byKey = new Map()
	for (const definition of definitions || []) {
		const key = definition?.key
		const scope = definition?.setting || ''
		if (!key || (scope !== '' && scope !== world)) {
			continue
		}
		if (scope === '' && byKey.has(key)) {
			continue
		}
		byKey.set(key, definition)
	}

	const rows = [...byKey.values()]
		.sort(
			(a, b) =>
				(a.order ?? 0) - (b.order ?? 0)
				|| String(a.label).localeCompare(String(b.label)),
		)
		.map((definition) => {
			const isPrivate = definition.visibility === 'gamemasters'
			const source = isPrivate ? privateValues || {} : values
			return {
				key: definition.key,
				label: definition.label || definition.key,
				fieldType: definition.fieldType || 'text',
				choices: Array.isArray(definition.choices) ? definition.choices : [],
				help: definition.help || '',
				private: isPrivate,
				value: source[definition.key] ?? null,
			}
		})

	const orphans = []
	for (const bag of [values, privateValues || {}]) {
		for (const [key, value] of Object.entries(bag)) {
			if (!byKey.has(key)) {
				orphans.push({ key, value })
			}
		}
	}

	return {
		rows,
		orphans,
		canEditPrivate: privateValues !== null || rows.some((row) => row.private),
		values,
		privateValues,
	}
}

/**
 * The value to store for a form input.
 *
 * @param {string} fieldType text, number, choice or yes-no.
 * @param {unknown} raw The input.
 * @return {unknown} The stored value, or null for an empty or invalid input.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
export function inputValue(fieldType, raw) {
	if (raw === null || raw === undefined) {
		return null
	}
	if (fieldType === 'yes-no') {
		return raw === true
	}
	if (fieldType === 'number') {
		const text = String(raw).trim()
		const number = Number(text)
		return text === '' || !Number.isFinite(number) ? null : number
	}
	const text = String(raw).trim()
	return text === '' ? null : text
}

/**
 * The PATCH body for the edited values: every value lands in the property
 * its visibility names, values without a definition stay as they were, and
 * the private property is only sent by someone who may read it.
 *
 * @param {object} sheet The sheet from sheetFrom().
 * @param {object} edits The edited values, by key.
 * @return {{customFields: object, customFieldsPrivate?: object}} The body.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
export function valuesFrom(sheet, edits) {
	const customFields = { ...sheet.values }
	const customFieldsPrivate = { ...(sheet.privateValues || {}) }
	for (const row of sheet.rows) {
		if (!Object.hasOwn(edits, row.key)) {
			continue
		}
		const target = row.private ? customFieldsPrivate : customFields
		target[row.key] = edits[row.key]
	}

	return sheet.canEditPrivate
		? { customFields, customFieldsPrivate }
		: { customFields }
}

/**
 * Load a character's sheet.
 *
 * @param {string} characterId The character UUID.
 * @return {Promise<object|null>} The sheet, or null when the character cannot be read.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
export async function fetchCustomFields(characterId) {
	const headers = { requesttoken: getRequestToken(), Accept: 'application/json' }
	const characterResponse = await fetch(
		generateUrl(`${OBJECTS}/character/${encodeURIComponent(characterId)}`),
		{ headers },
	)
	if (!characterResponse.ok) {
		return null
	}
	const character = await characterResponse.json()

	const query = new URLSearchParams({ _limit: String(MAX_DEFINITIONS) })
	const definitionsResponse = await fetch(
		generateUrl(`${OBJECTS}/larping_character_field?${query.toString()}`),
		{ headers },
	)
	const body = definitionsResponse.ok ? await definitionsResponse.json() : {}
	return sheetFrom(Array.isArray(body?.results) ? body.results : [], character)
}

/**
 * Save the values with one PATCH.
 *
 * @param {string} characterId The character UUID.
 * @param {object} values The body from valuesFrom().
 * @return {Promise<{ok: boolean, errors: object}>} The result, with an error per refused key.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
export async function saveCustomFields(characterId, values) {
	const response = await fetch(
		generateUrl(`${OBJECTS}/character/${encodeURIComponent(characterId)}`),
		{
			method: 'PATCH',
			headers: {
				requesttoken: getRequestToken(),
				Accept: 'application/json',
				'Content-Type': 'application/json',
			},
			body: JSON.stringify(values),
		},
	)
	if (response.ok) {
		return { ok: true, errors: {} }
	}
	let body
	try {
		body = await response.json()
	} catch {
		body = {}
	}
	const errors = objectOr(body?.fields ?? body?.errors?.fields, {})
	return { ok: false, errors }
}

/**
 * The value when it is a plain object, else the fallback.
 *
 * @param {unknown} value The value.
 * @param {unknown} fallback The fallback.
 * @return {unknown} The object or the fallback.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function objectOr(value, fallback) {
	return value && typeof value === 'object' && !Array.isArray(value)
		? value
		: fallback
}
