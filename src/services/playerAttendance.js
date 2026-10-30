/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Events attended on the player page: fetch the history from larpinq, which
 * serves the player's own list behind its ownership check (DECISIONS row 30).
 *
 * @spec openspec/specs/events-players/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

/**
 * Fetch the events a player was checked in at.
 *
 * @param {string} playerId The player UUID.
 * @return {Promise<{state: 'ok', count: number, events: Array<object>}|{state: 'refused'}|{state: 'failed'}>} The history, or why there is none.
 *
 * @spec openspec/specs/events-players/spec.md
 */
export async function fetchPlayerAttendance(playerId) {
	if (!playerId) {
		return { state: 'failed' }
	}

	const response = await fetch(
		generateUrl(
			`/apps/larpinq/api/players/${encodeURIComponent(playerId)}/attendance`,
		),
		{ headers: { requesttoken: getRequestToken(), Accept: 'application/json' } },
	)
	if (response.status === 403) {
		return { state: 'refused' }
	}
	if (!response.ok) {
		return { state: 'failed' }
	}

	const body = await response.json()
	return {
		state: 'ok',
		count: Number(body?.count) || 0,
		events: Array.isArray(body?.events) ? body.events : [],
	}
}

/**
 * The date an event started, as the list prints it (the day, in the user's locale).
 *
 * @param {string} value An ISO date-time, or ''.
 * @return {string} The date, or '' when there is none.
 *
 * @spec openspec/specs/events-players/spec.md
 */
export function formatEventDate(value) {
	if (!value) {
		return ''
	}
	const date = new Date(value)
	return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
}
