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
	const bags = {
		values: objectOr(character?.customFields, {}),
		privateValues: objectOr(character?.customFieldsPrivate, null),
	}
	const byKey = applicableDefinitions(definitions, character?.setting || '')
	const rows = [...byKey.values()]
		.sort(byOrderThenLabel)
		.map((definition) => rowOf(definition, bags))

	return {
		rows,
		orphans: orphansOf(byKey, bags),
		canEditPrivate:
			bags.privateValues !== null || rows.some((row) => row.private),
		values: bags.values,
		privateValues: bags.privateValues,
	}
}

/**
 * The definitions that apply in a world, by key: a world's own definition
 * wins over a global one with the same key.
 *
 * @param {Array<object>|null} definitions The definitions the user may read.
 * @param {string} world The character's world, or ''.
 * @return {Map<string, object>} The definitions by key, in first-seen order.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function applicableDefinitions(definitions, world) {
	const byKey = new Map()
	for (const definition of definitions || []) {
		if (appliesIn(definition, world) && !shadowed(definition, byKey)) {
			byKey.set(definition.key, definition)
		}
	}
	return byKey
}

/**
 * Whether a global definition comes after one with the same key.
 *
 * @param {object} definition The definition.
 * @param {Map<string, object>} byKey The definitions kept so far.
 * @return {boolean} True when it is global and the key is taken.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function shadowed(definition, byKey) {
	return !definition.setting && byKey.has(definition.key)
}

/**
 * Whether a definition has a key and no world or this world.
 *
 * @param {object|null} definition The definition.
 * @param {string} world The character's world, or ''.
 * @return {boolean} True when it applies.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function appliesIn(definition, world) {
	const scope = definition?.setting || ''
	return Boolean(definition?.key) && (scope === '' || scope === world)
}

/**
 * Sort order of sheet definitions: on order, then label.
 *
 * @param {object} a A definition.
 * @param {object} b Another definition.
 * @return {number} The comparison.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function byOrderThenLabel(a, b) {
	return (
		(a.order ?? 0) - (b.order ?? 0)
		|| String(a.label).localeCompare(String(b.label))
	)
}

/**
 * One sheet row for a definition.
 *
 * @param {object} definition The definition.
 * @param {{values: object, privateValues: object|null}} bags The character's values.
 * @return {object} The row.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function rowOf(definition, bags) {
	return {
		key: definition.key,
		label: definition.label || definition.key,
		fieldType: definition.fieldType || 'text',
		choices: Array.isArray(definition.choices) ? definition.choices : [],
		help: definition.help || '',
		private: definition.visibility === 'gamemasters',
		value: valueOf(definition, bags),
	}
}

/**
 * A definition's current value, from the bag its visibility names.
 *
 * @param {object} definition The definition.
 * @param {{values: object, privateValues: object|null}} bags The character's values.
 * @return {unknown} The value, or null.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function valueOf(definition, bags) {
	const source =
		definition.visibility === 'gamemasters'
			? bags.privateValues || {}
			: bags.values
	return source[definition.key] ?? null
}

/**
 * The stored values that have no applicable definition.
 *
 * @param {Map<string, object>} byKey The applicable definitions.
 * @param {{values: object, privateValues: object|null}} bags The character's values.
 * @return {Array<{key: string, value: unknown}>} The orphans.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function orphansOf(byKey, bags) {
	const orphans = []
	for (const bag of [bags.values, bags.privateValues || {}]) {
		for (const [key, value] of Object.entries(bag)) {
			if (!byKey.has(key)) {
				orphans.push({ key, value })
			}
		}
	}
	return orphans
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
		return numberValue(raw)
	}
	return textValue(raw)
}

/**
 * The stored value of a text or choice input.
 *
 * @param {unknown} raw The input.
 * @return {string|null} The trimmed text, or null when it is empty.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function textValue(raw) {
	const text = String(raw).trim()
	return text === '' ? null : text
}

/**
 * The stored value of a number input.
 *
 * @param {unknown} raw The input.
 * @return {number|null} The number, or null for an empty or invalid input.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
function numberValue(raw) {
	const text = String(raw).trim()
	const number = Number(text)
	return text === '' || !Number.isFinite(number) ? null : number
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
