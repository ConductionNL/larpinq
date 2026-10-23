/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Ticket type, options and code on a registration: the offer larpinq serves
 * (hidden ticket types and codes are not readable by players through the
 * object API), saving the choices through the OpenRegister objects API, and
 * the per-event counts for game masters. Prices are never sent: larpinq writes
 * the price lines itself.
 *
 * @spec openspec/specs/event-registration/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

const REGISTRATION = '/apps/openregister/api/objects/larpinq/larping_registration'

/**
 * The request headers.
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
 * Read a JSON answer of larpinq: ok, refused (403) or failed.
 *
 * @param {Response} response The answer.
 * @return {Promise<object>} The body with `state: 'ok'`, or `{state: 'refused'|'failed'}`.
 */
async function answer(response) {
	if (response.status === 403) {
		return { state: 'refused' }
	}
	if (!response.ok) {
		return { state: 'failed' }
	}
	return { state: 'ok', ...(await response.json()) }
}

/**
 * The ticket types and options a registration's player may choose now.
 *
 * @param {string} registrationId The registration UUID.
 * @param {string} code The code the player typed, or ''.
 * @return {Promise<object>} `{state: 'ok', ticketTypes, options, code, chosen}`, or why there is none.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export async function fetchOffer(registrationId, code) {
	if (!registrationId) {
		return { state: 'failed' }
	}
	const typed = String(code ?? '').trim()
	const query = typed === '' ? '' : `?code=${encodeURIComponent(typed)}`
	const response = await fetch(
		generateUrl(
			`/apps/larpinq/api/registrations/${encodeURIComponent(registrationId)}/offer`,
		) + query,
		{ headers: headers() },
	)
	return answer(response)
}

/**
 * The PATCH body: the choices only, never a price line.
 *
 * @param {object} choices `{ticketType, options, code}`.
 * @return {object} The body.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export function choiceBody(choices) {
	const body = {
		options: Array.isArray(choices?.options) ? choices.options : [],
	}
	if (choices?.ticketType) {
		body.ticketType = String(choices.ticketType)
	}
	const code = String(choices?.code ?? '').trim()
	if (code !== '') {
		body.code = code
	}
	return body
}

/**
 * Save the choices of a registration. A refusal carries larpinq's reason.
 *
 * @param {string} registrationId The registration UUID.
 * @param {object} choices `{ticketType, options, code}`.
 * @return {Promise<{ok: boolean, message: string}>} The result.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export async function saveChoices(registrationId, choices) {
	const response = await fetch(
		generateUrl(`${REGISTRATION}/${encodeURIComponent(registrationId)}`),
		{
			method: 'PATCH',
			headers: headers(),
			body: JSON.stringify(choiceBody(choices)),
		},
	)
	if (response.ok) {
		return { ok: true, message: '' }
	}
	let refusal
	try {
		refusal = await response.json()
	} catch {
		refusal = {}
	}
	const errors =
		refusal?.errors && typeof refusal.errors === 'object'
			? refusal.errors
			: refusal
	return {
		ok: false,
		message: String(errors?.message ?? refusal?.message ?? ''),
	}
}

/**
 * How many accepted registrations of an event chose each ticket type and option.
 *
 * @param {string} eventId The event UUID.
 * @return {Promise<object>} `{state: 'ok', ticketTypes, options}`, or why there is none.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export async function fetchChoiceCounts(eventId) {
	if (!eventId) {
		return { state: 'failed' }
	}
	const response = await fetch(
		generateUrl(
			`/apps/larpinq/api/events/${encodeURIComponent(eventId)}/choices`,
		),
		{ headers: headers() },
	)
	return answer(response)
}

/**
 * A listed price in cents, printed in the user's locale.
 *
 * @param {number} amount The price in cents.
 * @param {string} currency The currency, such as EUR.
 * @return {string} The price, or '' when it cannot be printed.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export function formatPrice(amount, currency) {
	const cents = Number(amount)
	if (!Number.isFinite(cents)) {
		return ''
	}
	try {
		return new Intl.NumberFormat(undefined, {
			style: 'currency',
			currency: currency || 'EUR',
		}).format(cents / 100)
	} catch {
		return ''
	}
}
