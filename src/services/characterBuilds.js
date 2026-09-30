/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Character builds (characters-multiple-builds): the check of a build, which
 * larpinq runs server-side without writing anything
 * (`/api/builds/{id}/report`), and applying a build, which is one normal
 * PATCH of the character's skills, items and conditions. OpenRegister and
 * larpinq's requirement listener check that write like any other.
 *
 * @spec openspec/specs/character-builds/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

const OBJECTS = '/apps/openregister/api/objects/larpinq'

/** The lists a build replaces on the character. */
export const BUILD_LISTS = ['skills', 'items', 'conditions']

/**
 * The build id from a prop, the injected object context or the route. An
 * `open-modal` header action forwards its props verbatim, so a prop that
 * still reads `@objectId` is ignored.
 *
 * @param {...string} candidates The candidates, in order.
 * @return {string} The first real id, or ''.
 *
 * @spec openspec/specs/character-builds/spec.md
 */
export function firstId(...candidates) {
	for (const candidate of candidates) {
		const id = String(candidate ?? '')
		if (id !== '' && !id.startsWith('@')) {
			return id
		}
	}
	return ''
}

/**
 * The check of one build (REQ-CMB-002).
 *
 * @param {string} buildId The build UUID.
 * @return {Promise<object|null>} `{build, character, report, stats, lists, changes}`, or null when it cannot be read.
 *
 * @spec openspec/specs/character-builds/spec.md
 */
export async function fetchBuildReport(buildId) {
	if (!buildId) {
		return null
	}
	const response = await fetch(
		generateUrl(`/apps/larpinq/api/builds/${encodeURIComponent(buildId)}/report`),
		{ headers: { requesttoken: getRequestToken(), Accept: 'application/json' } },
	)
	if (!response.ok) {
		return null
	}
	return response.json()
}

/**
 * Whether applying the build changes anything.
 *
 * @param {object} changes The `changes` block of the report.
 * @return {boolean} True when a list gains or loses an entry.
 *
 * @spec openspec/specs/character-builds/spec.md
 */
export function hasChanges(changes) {
	return BUILD_LISTS.some(
		(list) =>
			(changes?.[list]?.added?.length ?? 0) > 0
			|| (changes?.[list]?.removed?.length ?? 0) > 0,
	)
}

/**
 * Apply a build: replace the character's three lists with the build's in one
 * PATCH (REQ-CMB-003). A refusal carries the listener's message and, when the
 * XP budget is short, its budget block.
 *
 * @param {string} characterId The character UUID.
 * @param {object} lists The `lists` block of the report.
 * @return {Promise<{ok: boolean, message: string, shortfall: number}>} The result.
 *
 * @spec openspec/specs/character-builds/spec.md
 */
export async function applyBuild(characterId, lists) {
	const body = {}
	for (const list of BUILD_LISTS) {
		body[list] = Array.isArray(lists?.[list]) ? lists[list] : []
	}
	const response = await fetch(
		generateUrl(`${OBJECTS}/character/${encodeURIComponent(characterId)}`),
		{
			method: 'PATCH',
			headers: {
				requesttoken: getRequestToken(),
				Accept: 'application/json',
				'Content-Type': 'application/json',
			},
			body: JSON.stringify(body),
		},
	)
	if (response.ok) {
		return { ok: true, message: '', shortfall: 0 }
	}
	let refusal
	try {
		refusal = await response.json()
	} catch {
		refusal = {}
	}
	const errors = refusal?.errors && typeof refusal.errors === 'object' ? refusal.errors : refusal
	return {
		ok: false,
		message: String(errors?.message ?? refusal?.message ?? ''),
		shortfall: Number(errors?.budget?.shortfall ?? 0),
	}
}
