/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The check-in code of a registration: reading it for its player (the
 * property is masked on every list read and shown only to a single-object
 * read that names it in `_reveal`), drawing it as a QR code, and checking a
 * participant in by a scanned or typed code (events-qr-checkin).
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

import { generateUrl } from '@nextcloud/router'
import QRCode from 'qrcode'
import { jsonHeaders, ocsJsonHeaders } from './requestHeaders.js'

const REGISTRATION = '/apps/openregister/api/objects/larpinq/larping_registration'

/**
 * The shape of a check-in code: 26 characters of base32.
 */
const CODE_PATTERN = /^[A-Z2-7]{26}$/

/**
 * A scanned or typed code as larpinq stores it: upper case, without the
 * spaces and dashes a person or a scanner may add. '' when what is left is
 * not a check-in code.
 *
 * @param {string} raw What was scanned or typed.
 * @return {string} The code, or ''.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-stewards-check-in-by-scanning-or-typing-a-code-req-eqc-003
 */
export function normaliseCode(raw) {
	const code = String(raw ?? '')
		.toUpperCase()
		.replace(/[\s-]+/g, '')
	return CODE_PATTERN.test(code) ? code : ''
}

/**
 * The check-in code of one registration, read by its player or a game master.
 *
 * @param {string} registrationId The registration UUID.
 * @return {Promise<string>} The code, or '' when there is none or it cannot be read.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-the-player-sees-the-code-as-a-qr-code-req-eqc-002
 */
export async function fetchCheckinCode(registrationId) {
	if (!registrationId) {
		return ''
	}
	try {
		const response = await fetch(
			generateUrl(`${REGISTRATION}/${encodeURIComponent(registrationId)}`)
				+ '?_reveal=checkinCode',
			{ headers: jsonHeaders() },
		)
		if (!response.ok) {
			return ''
		}
		const body = await response.json()
		return normaliseCode(body?.checkinCode)
	} catch {
		return ''
	}
}

/**
 * The QR code of a check-in code as an SVG drawing: its size in modules and
 * one path that fills every dark module. Drawn as a path rather than as
 * markup from the library, so the template renders it without `v-html`.
 *
 * @param {string} code The check-in code.
 * @return {{size: number, path: string}|null} The drawing, or null for no code.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-the-player-sees-the-code-as-a-qr-code-req-eqc-002
 */
export function qrDrawing(code) {
	const valid = normaliseCode(code)
	if (valid === '') {
		return null
	}
	const { modules } = QRCode.create(valid, { errorCorrectionLevel: 'M' })
	const parts = []
	for (let row = 0; row < modules.size; row++) {
		for (let col = 0; col < modules.size; col++) {
			if (modules.get(row, col)) {
				parts.push(`M${col} ${row}h1v1h-1z`)
			}
		}
	}
	return { size: modules.size, path: parts.join('') }
}

/**
 * Check a participant of an event in by the code of their registration.
 *
 * @param {string} eventId The event UUID.
 * @param {string} code The scanned or typed code.
 * @return {Promise<object>} larpinq's answer: `{status, name, character}`,
 *   `{status: 'already', at, by}`, or `{status}` with 'not-accepted',
 *   'unknown', 'refused', 'busy' or 'failed'.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-a-code-is-checked-in-once-and-only-for-its-event-req-eqc-004
 */
export async function checkInByCode(eventId, code) {
	const valid = normaliseCode(code)
	if (!eventId || valid === '') {
		return { status: 'unknown' }
	}
	try {
		const response = await fetch(
			generateUrl(
				`/apps/larpinq/api/events/${encodeURIComponent(eventId)}/checkin-code`,
			),
			{
				method: 'POST',
				headers: ocsJsonHeaders(),
				body: JSON.stringify({ code: valid }),
			},
		)
		if (response.status === 403) {
			return { status: 'refused' }
		}
		if (response.status === 429) {
			return { status: 'busy' }
		}
		const body = await response.json().catch(() => ({}))
		const known = ['checked-in', 'already', 'not-accepted', 'unknown']
		if (known.includes(body?.status)) {
			return body
		}
		return { status: 'failed' }
	} catch {
		return { status: 'failed' }
	}
}

/**
 * The hour and minute of an ISO timestamp, in the browser's time zone.
 *
 * @param {string} at The timestamp.
 * @return {string} 'HH:MM', or the input when it is not a date.
 */
function clockTime(at) {
	const date = new Date(at)
	if (Number.isNaN(date.getTime())) {
		return String(at ?? '')
	}
	return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

/**
 * The line the scan panel shows for an answer, and whether it is good news.
 *
 * @param {(app: string, text: string, vars?: object) => string} t The translate function.
 * @param {object} result The answer of checkInByCode().
 * @return {{text: string, ok: boolean}} The message.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-a-code-is-checked-in-once-and-only-for-its-event-req-eqc-004
 */
export function resultMessage(t, result) {
	switch (result?.status) {
		case 'checked-in':
			return {
				ok: true,
				text: t('larpinq', 'Checked in: {name}, {character}', {
					name: result.name ?? '',
					character: result.character ?? '',
				}),
			}
		case 'already':
			return {
				ok: false,
				text: t('larpinq', 'Already checked in at {time} by {steward}', {
					time: clockTime(result.at),
					steward: result.by ?? '',
				}),
			}
		case 'not-accepted':
			return {
				ok: false,
				text: t(
					'larpinq',
					'This registration is not accepted. Nobody was checked in.',
				),
			}
		case 'unknown':
			return {
				ok: false,
				text: t(
					'larpinq',
					'Unknown code, or a code for another event. Nobody was checked in.',
				),
			}
		case 'refused':
			return {
				ok: false,
				text: t('larpinq', 'Only game masters can check participants in.'),
			}
		case 'busy':
			return {
				ok: false,
				text: t(
					'larpinq',
					'Too many attempts. Wait a moment and try again.',
				),
			}
		default:
			return { ok: false, text: t('larpinq', 'Check-in failed. Try again.') }
	}
}
