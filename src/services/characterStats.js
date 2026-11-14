/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Character stat sheet: fetch the sheet and shape it for the Stats tab.
 *
 * @spec openspec/specs/character-management/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

/**
 * Fetch the stat sheet of one character.
 *
 * @param {string} characterId The character UUID.
 * @return {Promise<{abilities: Array<object>, xp: object|null}|null>} The sheet, or null when it cannot be read.
 *
 * @spec openspec/specs/character-management/spec.md
 */
export async function fetchCharacterStats(characterId) {
	if (!characterId) {
		return null
	}

	const response = await fetch(
		generateUrl(
			`/apps/larpinq/api/characters/${encodeURIComponent(characterId)}/stats`,
		),
		{ headers: { requesttoken: getRequestToken(), Accept: 'application/json' } },
	)
	if (!response.ok) {
		return null
	}

	const body = await response.json()
	return {
		abilities: Array.isArray(body?.abilities) ? body.abilities : [],
		xp: body?.xp ?? null,
	}
}

/**
 * A modifier's change as the sheet prints it: +3, -2 or 0.
 *
 * @param {number} change The change.
 * @return {string} The signed number.
 *
 * @spec openspec/specs/character-management/spec.md
 */
export function formatChange(change) {
	const value = Number(change) || 0
	return value > 0 ? `+${value}` : String(value)
}

/**
 * The tone of a modifier: negative ones stand out (REQ-CSP-001).
 *
 * @param {number} change The change.
 * @return {'positive'|'negative'|'none'} The tone.
 *
 * @spec openspec/specs/character-management/spec.md
 */
export function changeTone(change) {
	const value = Number(change) || 0
	if (value < 0) {
		return 'negative'
	}
	return value > 0 ? 'positive' : 'none'
}

/**
 * Whether an ability was moved by anything (REQ-CSP-002).
 *
 * @param {{modifiers?: Array<object>}} ability The ability row.
 * @return {boolean} True when at least one modifier applied.
 *
 * @spec openspec/specs/character-management/spec.md
 */
export function hasModifiers(ability) {
	return Array.isArray(ability?.modifiers) && ability.modifiers.length > 0
}
