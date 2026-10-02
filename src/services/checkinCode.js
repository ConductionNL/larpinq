/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Check-in codes: read a registration's code through the OpenRegister
 * objects API (only game masters and the registration's player may read it),
 * draw it as a QR code, and check a participant in by a scanned or typed code.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import QRCode from 'qrcode'

const OBJECTS = '/apps/openregister/api/objects/larpinq'
const REGISTRATION = `${OBJECTS}/larping_registration`

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
 * The name of a register object, or '' when it cannot be read.
 *
 * @param {string} schema The schema slug.
 * @param {string} id The object UUID.
 * @return {Promise<string>} The name.
 */
async function nameOf(schema, id) {
	if (!id) {
		return ''
	}
	try {
		const response = await fetch(generateUrl(`${OBJECTS}/${schema}/${id}`), {
			headers: headers(),
		})
		return response.ok ? (await response.json()).name || '' : ''
	} catch {
		return ''
	}
}

/**
 * The registration with its check-in code, or why there is none.
 *
 * @param {string} registrationId The registration UUID.
 * @return {Promise<object>} `{state: 'ok', code, status, name, character, event}` or `{state: 'failed'}`.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */
export async function fetchCheckinCode(registrationId) {
	if (!registrationId) {
		return { state: 'failed' }
	}
	try {
		const response = await fetch(
			generateUrl(`${REGISTRATION}/${registrationId}`),
			{ headers: headers() },
		)
		if (!response.ok) {
			return { state: 'failed' }
		}
		const registration = await response.json()
		const [name, character, event] = await Promise.all([
			nameOf('player', registration.player),
			nameOf('character', registration.character),
			nameOf('larping_event', registration.event),
		])
		return {
			state: 'ok',
			code: registration.checkinCode || '',
			status: registration.status || '',
			name,
			character,
			event,
		}
	} catch {
		return { state: 'failed' }
	}
}

/**
 * The QR code of a check-in code as SVG markup.
 *
 * @param {string} code The check-in code.
 * @return {Promise<string>} The SVG, or '' without a code.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */
export async function qrSvg(code) {
	if (!code) {
		return ''
	}
	return QRCode.toString(code, {
		type: 'svg',
		errorCorrectionLevel: 'M',
		margin: 2,
	})
}

/**
 * Whether this browser reads QR codes from the camera.
 *
 * @param {object} scope Where to look for `BarcodeDetector` (window).
 * @return {Promise<boolean>} True when `qr_code` is supported.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */
export async function cameraScanSupported(scope) {
	const detector = scope?.BarcodeDetector
	if (!detector || typeof detector.getSupportedFormats !== 'function') {
		return false
	}
	try {
		return (await detector.getSupportedFormats()).includes('qr_code')
	} catch {
		return false
	}
}

/**
 * Check in the participant a code belongs to.
 *
 * @param {string} eventId The event UUID.
 * @param {string} code The scanned or typed code.
 * @return {Promise<object>} The answer (`status`: checked-in, already, unknown,
 * not-accepted, no-character), or `{status: 'failed'}`.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */
export async function checkInByCode(eventId, code) {
	try {
		const response = await fetch(
			generateUrl('/apps/larpinq/api/events/{id}/checkin-code', {
				id: eventId,
			}),
			{
				method: 'POST',
				headers: headers(),
				body: JSON.stringify({ code }),
			},
		)
		if (response.status === 403) {
			return { status: 'refused' }
		}
		const body = await response.json().catch(() => ({}))
		return body.status ? body : { status: 'failed' }
	} catch {
		return { status: 'failed' }
	}
}
