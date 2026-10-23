/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Cancelling a registration, adding a participant to a booking and handing a
 * registration to another player (registration-cancel-transfer-refund). The
 * status, booking and transfer fields are larpinq's to write, so every step
 * goes through larpinq's endpoints, which check who is asking.
 *
 * @spec openspec/specs/event-registration/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

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
 * The URL of a registration endpoint.
 *
 * @param {string} registrationId The registration UUID.
 * @param {string} path The path after the id.
 * @return {string} The URL.
 */
function url(registrationId, path) {
	return generateUrl(
		`/apps/larpinq/api/registrations/${encodeURIComponent(registrationId)}/${path}`,
	)
}

/**
 * Read larpinq's answer: the body when ok, else larpinq's reason.
 *
 * @param {Response} response The answer.
 * @return {Promise<{ok: boolean, data: object, message: string}>} The result.
 */
async function answer(response) {
	let body
	try {
		body = await response.json()
	} catch {
		body = {}
	}
	if (response.ok) {
		return { ok: true, data: body ?? {}, message: '' }
	}
	return { ok: false, data: {}, message: String(body?.error ?? '') }
}

/**
 * POST to a registration endpoint.
 *
 * @param {string} registrationId The registration UUID.
 * @param {string} path The path after the id.
 * @param {object} body The JSON body.
 * @return {Promise<{ok: boolean, data: object, message: string}>} The result.
 */
async function post(registrationId, path, body) {
	if (!registrationId) {
		return { ok: false, data: {}, message: '' }
	}
	const response = await fetch(url(registrationId, path), {
		method: 'POST',
		headers: headers(),
		body: JSON.stringify(body),
	})
	return answer(response)
}

/**
 * What the signed-in user may change on a registration now.
 *
 * @param {string} registrationId The registration UUID.
 * @return {Promise<{ok: boolean, data: object, message: string}>} The answers.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export async function fetchChanges(registrationId) {
	if (!registrationId) {
		return { ok: false, data: {}, message: '' }
	}
	const response = await fetch(url(registrationId, 'changes'), {
		headers: headers(),
	})
	return answer(response)
}

/**
 * Cancel a registration, with the player's choice of refund or credit when the event lets them choose.
 *
 * @param {string} registrationId The registration UUID.
 * @param {string} settlement `refund`, `credit` or ''.
 * @return {Promise<{ok: boolean, data: object, message: string}>} The result.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export function cancelRegistration(registrationId, settlement) {
	const body = {}
	if (settlement === 'refund' || settlement === 'credit') {
		body.settlement = settlement
	}
	return post(registrationId, 'cancel', body)
}

/**
 * Add a new participant by name to the booking of a registration.
 *
 * @param {string} registrationId The caller's registration UUID.
 * @param {string} name The participant's name.
 * @return {Promise<{ok: boolean, data: object, message: string}>} The participant's registration.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export function addParticipant(registrationId, name) {
	return post(registrationId, 'participants', {
		name: String(name ?? '').trim(),
	})
}

/**
 * Offer a registration to another player's account.
 *
 * @param {string} registrationId The registration UUID.
 * @param {string} account The other player's account name.
 * @return {Promise<{ok: boolean, data: object, message: string}>} The result.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export function offerTransfer(registrationId, account) {
	return post(registrationId, 'transfer', {
		account: String(account ?? '').trim(),
	})
}

/**
 * Accept the registration offered to the signed-in user.
 *
 * @param {string} registrationId The registration UUID.
 * @return {Promise<{ok: boolean, data: object, message: string}>} The result.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export function acceptTransfer(registrationId) {
	return post(registrationId, 'transfer/accept', {})
}

/**
 * Withdraw an open offer.
 *
 * @param {string} registrationId The registration UUID.
 * @return {Promise<{ok: boolean, data: object, message: string}>} The result.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
export function withdrawTransfer(registrationId) {
	return post(registrationId, 'transfer/withdraw', {})
}
