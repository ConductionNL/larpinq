/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The check-in code of a registration: reading it for its player (the
 * property is masked on every list read and shown only to a single-object
 * read that names it in `_reveal`), drawing it as a QR code, and checking a
 * participant in by a scanned or typed code (events-qr-checkin).
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
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
 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-stewards-check-in-by-scanning-or-typing-a-code-req-eqc-003
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
 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-the-player-sees-the-code-as-a-qr-code-req-eqc-002
 */
export async function fetchCheckinCode(registrationId) {
	if (!registrationId) {
		return ''
	}
	try {
		return normaliseCode((await readRevealed(registrationId))?.checkinCode)
	} catch {
		return ''
	}
}

/**
 * One registration read with its check-in code revealed.
 *
 * @param {string} registrationId The registration UUID.
 * @return {Promise<object|null>} The registration, or null when the read is refused.
 */
async function readRevealed(registrationId) {
	const response = await fetch(
		generateUrl(`${REGISTRATION}/${encodeURIComponent(registrationId)}`)
			+ '?_reveal=checkinCode',
		{ headers: jsonHeaders() },
	)
	return response.ok ? response.json() : null
}

/**
 * The QR code of a check-in code as an SVG drawing: its size in modules and
 * one path that fills every dark module. Drawn as a path rather than as
 * markup from the library, so the template renders it without `v-html`.
 *
 * @param {string} code The check-in code.
 * @return {{size: number, path: string}|null} The drawing, or null for no code.
 *
 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-the-player-sees-the-code-as-a-qr-code-req-eqc-002
 */
export function qrDrawing(code) {
	const valid = normaliseCode(code)
	if (valid === '') {
		return null
	}
	const { modules } = QRCode.create(valid, { errorCorrectionLevel: 'M' })
	const parts = []
	for (let row = 0; row < modules.size; row++) {
		parts.push(rowPath(modules, row))
	}
	return { size: modules.size, path: parts.join('') }
}

/**
 * The path of the dark modules of one QR code row.
 *
 * @param {{size: number, get: (row: number, col: number) => boolean}} modules The QR code's module matrix.
 * @param {number} row The row.
 * @return {string} The row's path segments.
 */
function rowPath(modules, row) {
	let path = ''
	for (let col = 0; col < modules.size; col++) {
		if (modules.get(row, col)) {
			path += `M${col} ${row}h1v1h-1z`
		}
	}
	return path
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
 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-a-code-is-checked-in-once-and-only-for-its-event-req-eqc-004
 */
export async function checkInByCode(eventId, code) {
	const valid = normaliseCode(code)
	if (!eventId || valid === '') {
		return { status: 'unknown' }
	}
	try {
		return await answerOf(await postCode(eventId, valid))
	} catch {
		return { status: 'failed' }
	}
}

/**
 * The statuses the check-in endpoint answers with in its body.
 */
const KNOWN_STATUSES = ['checked-in', 'already', 'not-accepted', 'unknown']

/**
 * The statuses an HTTP refusal maps to, before any body is read.
 */
const HTTP_STATUSES = { 403: 'refused', 429: 'busy' }

/**
 * Post a valid code to the check-in endpoint of an event.
 *
 * @param {string} eventId The event UUID.
 * @param {string} code The normalised code.
 * @return {Promise<Response>} The response.
 */
function postCode(eventId, code) {
	return fetch(
		generateUrl(
			`/apps/larpinq/api/events/${encodeURIComponent(eventId)}/checkin-code`,
		),
		{
			method: 'POST',
			headers: ocsJsonHeaders(),
			body: JSON.stringify({ code }),
		},
	)
}

/**
 * larpinq's answer to a check-in, from the endpoint's response.
 *
 * @param {Response} response The response.
 * @return {Promise<object>} The answer, `{status: 'failed'}` for anything unexpected.
 */
async function answerOf(response) {
	if (HTTP_STATUSES[response.status]) {
		return { status: HTTP_STATUSES[response.status] }
	}
	const body = await response.json().catch(() => ({}))
	return KNOWN_STATUSES.includes(body?.status) ? body : { status: 'failed' }
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
 * The scan panel's line per answer status. Each entry calls t() with its
 * literal source string, so the extractor still finds every one.
 */
const RESULT_MESSAGES = {
	'checked-in': (t, result) => ({
		ok: true,
		text: t('larpinq', 'Checked in: {name}, {character}', {
			name: result.name ?? '',
			character: result.character ?? '',
		}),
	}),
	already: (t, result) => ({
		ok: false,
		text: t('larpinq', 'Already checked in at {time} by {steward}', {
			time: clockTime(result.at),
			steward: result.by ?? '',
		}),
	}),
	'not-accepted': (t) => ({
		ok: false,
		text: t(
			'larpinq',
			'This registration is not accepted. Nobody was checked in.',
		),
	}),
	unknown: (t) => ({
		ok: false,
		text: t(
			'larpinq',
			'Unknown code, or a code for another event. Nobody was checked in.',
		),
	}),
	refused: (t) => ({
		ok: false,
		text: t('larpinq', 'Only game masters can check participants in.'),
	}),
	busy: (t) => ({
		ok: false,
		text: t('larpinq', 'Too many attempts. Wait a moment and try again.'),
	}),
	failed: (t) => ({ ok: false, text: t('larpinq', 'Check-in failed. Try again.') }),
}

/**
 * The line the scan panel shows for an answer, and whether it is good news.
 *
 * @param {(app: string, text: string, vars?: object) => string} t The translate function.
 * @param {object} result The answer of checkInByCode().
 * @return {{text: string, ok: boolean}} The message.
 *
 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-a-code-is-checked-in-once-and-only-for-its-event-req-eqc-004
 */
export function resultMessage(t, result) {
	const message = RESULT_MESSAGES[result?.status] ?? RESULT_MESSAGES.failed
	return message(t, result)
}
