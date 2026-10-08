/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The batch XP award of an event (events-xp-batch-award): read the award
 * roster, turn the dialog's rows into the rows a save sends, and save them.
 * The server decides the default ticks (checked in and not yet awarded) and
 * refuses what it must; this module only carries the game master's choices.
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

/**
 * A larpinq URL for an event's awards.
 *
 * @param {string} eventId The event UUID.
 * @param {string} tail The path after the event.
 * @return {string} The URL.
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
function eventUrl(eventId, tail) {
	return generateUrl(
		`/apps/larpinq/api/events/${encodeURIComponent(eventId)}/${tail}`,
	)
}

/**
 * The JSON body of a response, or an empty object.
 *
 * @param {Response} response The response.
 * @return {Promise<object>} The body.
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
async function bodyOf(response) {
	try {
		const body = await response.json()
		return body && typeof body === 'object' ? body : {}
	} catch {
		return {}
	}
}

/**
 * Read the award roster of an event.
 *
 * @param {string} eventId The event UUID.
 * @return {Promise<{status: string, eventName: string, rows: Array<object>, attendanceAvailable: boolean}>}
 *   status is `ready`, `forbidden` or `failed`.
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
export async function fetchAwardRoster(eventId) {
	const empty = { eventName: '', rows: [], attendanceAvailable: false }
	let response
	try {
		response = await fetch(eventUrl(eventId, 'xp-award-roster'), {
			headers: { requesttoken: getRequestToken(), Accept: 'application/json' },
		})
	} catch {
		return { status: 'failed', ...empty }
	}
	if (response.status === 401 || response.status === 403) {
		return { status: 'forbidden', ...empty }
	}
	if (!response.ok) {
		return { status: 'failed', ...empty }
	}
	const body = await bodyOf(response)
	return {
		status: 'ready',
		eventName: String(body.eventName || ''),
		rows: Array.isArray(body.rows) ? body.rows : [],
		attendanceAvailable: body.attendanceAvailable === true,
	}
}

/**
 * The dialog's rows: the server's tick, an empty amount and reason (the
 * defaults apply), and a hint flag for a character without a recorded check-in.
 *
 * @param {Array<object>} rows The award roster rows.
 * @return {Array<object>} The editable rows.
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
export function initialRows(rows) {
	return rows.map((row) => ({
		character: String(row.character || ''),
		name: String(row.name || ''),
		playerName: String(row.playerName || ''),
		attendance: String(row.attendance || ''),
		awards: Array.isArray(row.awards) ? row.awards : [],
		ticked: row.ticked === true,
		noCheckIn: !row.attendance,
		amount: '',
		reason: '',
	}))
}

/**
 * The rows a save sends: every ticked row with its own amount and reason, or
 * the defaults. A row whose character already has an award for the event is
 * an extra award. Rows without a positive amount are left out.
 *
 * @param {Array<object>} rows The dialog's rows.
 * @param {string|number} defaultAmount The amount for rows without their own.
 * @param {string} defaultReason The reason for rows without their own.
 * @return {Array<{character: string, amount: number, reason: string, extra: boolean}>} The rows.
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
export function rowsToSave(rows, defaultAmount, defaultReason) {
	return rows
		.filter((row) => row.ticked)
		.map((row) => {
			const own = String(row.amount ?? '').trim()
			const amount = Number(own !== '' ? own : defaultAmount)
			const reason =
				String(row.reason || '').trim() || String(defaultReason || '').trim()
			return {
				character: row.character,
				amount,
				reason,
				extra: row.awards.length > 0,
			}
		})
		.filter((row) => Number.isFinite(row.amount) && row.amount > 0)
}

/**
 * Save the rows.
 *
 * @param {string} eventId The event UUID.
 * @param {Array<object>} rows The rows from rowsToSave().
 * @return {Promise<{ok: boolean, created: number, refused: Array<{character: string, reason: string}>, error: string}>}
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
export async function saveAwards(eventId, rows) {
	let response
	try {
		response = await fetch(eventUrl(eventId, 'xp-awards'), {
			method: 'POST',
			headers: {
				requesttoken: getRequestToken(),
				'Content-Type': 'application/json',
				Accept: 'application/json',
			},
			body: JSON.stringify({ rows }),
		})
	} catch (error) {
		return {
			ok: false,
			created: 0,
			refused: [],
			error: String(error?.message || error),
		}
	}
	const body = await bodyOf(response)
	if (!response.ok) {
		return {
			ok: false,
			created: 0,
			refused: [],
			error: String(body.error || response.status),
		}
	}
	return {
		ok: true,
		created: Array.isArray(body.created) ? body.created.length : 0,
		refused: Array.isArray(body.refused) ? body.refused : [],
		error: '',
	}
}

/**
 * Why the server refused a row, in words.
 *
 * @param {string} code The refusal code.
 * @return {string} The explanation.
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
export function refusalText(code) {
	const texts = {
		'not-on-roster': t('larpinq', 'not part of this event'),
		'invalid-amount': t('larpinq', 'the amount must be above zero'),
		duplicate: t('larpinq', 'already has an award for this event'),
		'extra-needs-reason': t('larpinq', 'an extra award needs a reason'),
		'not-saved': t('larpinq', 'could not be saved'),
	}
	return texts[code] || code
}
