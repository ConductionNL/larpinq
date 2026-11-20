/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Email participants (communication-mail-participants): preview who a
 * message reaches, save it as a draft and send it, through larpinq's
 * game-master endpoints under `/api/events/{id}/messages`.
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { bodyOf } from './responseBody.js'

/** Registration statuses a message can go to, in the order the dialog offers them. */
export const STATUSES = ['accepted', 'waitlisted', 'pending']

/** Ticket roles a message can go to. */
export const TICKET_ROLES = ['player', 'crew', 'npc']

/**
 * The URL of an event's message endpoint.
 *
 * @param {string} eventId The event UUID.
 * @param {string} tail The path after `messages`.
 * @return {string} The URL.
 */
function messagesUrl(eventId, tail = '') {
	return generateUrl(
		`/apps/larpinq/api/events/${encodeURIComponent(eventId)}/messages${tail}`,
	)
}

/**
 * The request headers of every call.
 *
 * @param {boolean} json Whether a JSON body is sent.
 * @return {object} The headers.
 */
function headers(json = false) {
	const out = { requesttoken: getRequestToken(), Accept: 'application/json' }
	if (json) {
		out['Content-Type'] = 'application/json'
	}
	return out
}

/**
 * The audience as query parameters.
 *
 * @param {{statuses: string[], checkedIn: boolean, paymentOpen: boolean, ticketRoles: string[]}} audience The audience.
 * @return {string} The query string, without `?`.
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
 */
export function audienceQuery(audience) {
	const query = new URLSearchParams()
	for (const status of audience.statuses || []) {
		query.append('statuses[]', status)
	}
	for (const role of audience.ticketRoles || []) {
		query.append('ticketRoles[]', role)
	}
	if (audience.checkedIn) {
		query.append('checkedIn', 'true')
	}
	if (audience.paymentOpen) {
		query.append('paymentOpen', 'true')
	}
	return query.toString()
}

/**
 * How many people a message with this audience reaches.
 *
 * @param {string} eventId The event UUID.
 * @param {object} audience The audience.
 * @return {Promise<{ok: boolean, count: number, names: string[], noAddressCount: number, noAddressNames: string[], error: string}>}
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
 */
export async function previewAudience(eventId, audience) {
	const empty = { count: 0, names: [], noAddressCount: 0, noAddressNames: [] }
	try {
		const response = await fetch(
			`${messagesUrl(eventId, '/preview')}?${audienceQuery(audience)}`,
			{ headers: headers() },
		)
		const body = await bodyOf(response)
		if (!response.ok) {
			return { ok: false, ...empty, error: String(body.error || response.status) }
		}
		return {
			ok: true,
			count: Number(body.count) || 0,
			names: Array.isArray(body.names) ? body.names : [],
			noAddressCount: Number(body.noAddressCount) || 0,
			noAddressNames: Array.isArray(body.noAddressNames) ? body.noAddressNames : [],
			error: '',
		}
	} catch (e) {
		return { ok: false, ...empty, error: String(e?.message || e) }
	}
}

/**
 * Save the message as a draft, then send it.
 *
 * @param {string} eventId The event UUID.
 * @param {{subject: string, body: string, audience: object}} message The message.
 * @return {Promise<{ok: boolean, message: object|null, error: string}>}
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-each-recipient-gets-their-own-mail-req-cmp-003
 */
export async function sendMessage(eventId, message) {
	try {
		const created = await fetch(messagesUrl(eventId), {
			method: 'POST',
			headers: headers(true),
			body: JSON.stringify(message),
		})
		const draft = await bodyOf(created)
		if (!created.ok) {
			return { ok: false, message: null, error: String(draft.error || created.status) }
		}
		const id = String(draft.id || draft['@self']?.id || '')
		const sent = await fetch(
			messagesUrl(eventId, `/${encodeURIComponent(id)}/send`),
			{ method: 'POST', headers: headers() },
		)
		const queued = await bodyOf(sent)
		if (!sent.ok) {
			return { ok: false, message: draft, error: String(queued.error || sent.status) }
		}
		return { ok: true, message: queued, error: '' }
	} catch (e) {
		return { ok: false, message: null, error: String(e?.message || e) }
	}
}
