/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/checkinCode.js: reading a registration's
 * check-in code, drawing it as a QR code, checking in by code and the line
 * the scan panel shows for each answer (events-qr-checkin).
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */

import QRCode from 'qrcode'
import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	checkInByCode,
	fetchCheckinCode,
	normaliseCode,
	qrDrawing,
	resultMessage,
} from '../../src/services/checkinCode.js'

const CODE = 'ABCDEFGHIJKLMNOPQRSTUVWXY2'
const REG = 'e0000000-0000-4000-8000-000000000001'

/**
 * A translate function that fills the placeholders, like @nextcloud/l10n.
 *
 * @param {string} app The app id.
 * @param {string} text The text.
 * @param {object} vars The placeholders.
 * @return {string} The text with the placeholders filled.
 */
function t(app, text, vars = {}) {
	return text.replace(/\{(\w+)\}/g, (all, key) => (key in vars ? vars[key] : all))
}

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
	delete globalThis.OC
})

describe('normaliseCode', () => {
	it.each([
		['the code as stored', CODE, CODE],
		[
			'lower case with spaces and dashes',
			' abcde-fghij klmno-pqrst uvwxy2 ',
			CODE,
		],
		['too short', 'ABCDEF', ''],
		['a character outside base32', 'ABCDEFGHIJKLMNOPQRSTUVWXY1', ''],
		['nothing', null, ''],
	])('%s', (name, raw, expected) => {
		expect(normaliseCode(raw)).toBe(expected)
	})
})

describe('fetchCheckinCode', () => {
	it('reads the single registration with the code revealed', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			json: async () => ({ id: REG, checkinCode: CODE }),
		})
		expect(await fetchCheckinCode(REG)).toBe(CODE)
		expect(globalThis.fetch.mock.calls[0][0]).toBe(
			`/index.php/apps/openregister/api/objects/larpinq/larping_registration/${REG}?_reveal=checkinCode`,
		)
		expect(globalThis.fetch.mock.calls[0][1].headers.requesttoken).toBe(
			'test-token',
		)
	})

	it('gives nothing for a masked code', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			json: async () => ({ checkinCode: '*****XY2' }),
		})
		expect(await fetchCheckinCode(REG)).toBe('')
	})

	it('gives nothing when the read is refused or fails', async () => {
		globalThis.fetch = vi
			.fn()
			.mockResolvedValueOnce({ ok: false, status: 403 })
			.mockRejectedValueOnce(new Error('offline'))
		expect(await fetchCheckinCode(REG)).toBe('')
		expect(await fetchCheckinCode(REG)).toBe('')
	})

	it('does not ask without an id', async () => {
		globalThis.fetch = vi.fn()
		expect(await fetchCheckinCode('')).toBe('')
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('qrDrawing', () => {
	it('draws one unit square per dark module of the QR code', () => {
		const drawing = qrDrawing(CODE)
		const { modules } = QRCode.create(CODE, { errorCorrectionLevel: 'M' })
		let dark = 0
		for (let i = 0; i < modules.size * modules.size; i++) {
			dark += modules.data[i] ? 1 : 0
		}

		expect(drawing.size).toBe(modules.size)
		expect(drawing.path.match(/M\d+ \d+h1v1h-1z/g)).toHaveLength(dark)
		// The top-left finder pattern starts with a dark module at the origin.
		expect(drawing.path.startsWith('M0 0h1v1h-1z')).toBe(true)
	})

	it('draws nothing for something that is not a code', () => {
		expect(qrDrawing('')).toBeNull()
		expect(qrDrawing('*****XY2')).toBeNull()
	})
})

describe('checkInByCode', () => {
	it('posts the normalised code to the event', async () => {
		globalThis.OC = { requestToken: 'token-1' }
		const answer = {
			status: 'checked-in',
			name: 'Anna de Vries',
			character: 'Mirela the Wanderer',
		}
		globalThis.fetch = vi
			.fn()
			.mockResolvedValueOnce({
				ok: true,
				status: 200,
				json: async () => answer,
			})

		expect(await checkInByCode('ev1', CODE.toLowerCase())).toEqual(answer)
		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe('/index.php/apps/larpinq/api/events/ev1/checkin-code')
		expect(init.method).toBe('POST')
		expect(JSON.parse(init.body)).toEqual({ code: CODE })
		expect(init.headers.requesttoken).toBe('token-1')
	})

	it.each([
		[
			'already',
			200,
			{ status: 'already', at: '2026-12-01T18:02:00', by: 'Joris' },
			{ status: 'already', at: '2026-12-01T18:02:00', by: 'Joris' },
		],
		[
			'not accepted',
			409,
			{ status: 'not-accepted' },
			{ status: 'not-accepted' },
		],
		['unknown', 404, { status: 'unknown' }, { status: 'unknown' }],
		['refused', 403, { message: 'no' }, { status: 'refused' }],
		['rate limited', 429, {}, { status: 'busy' }],
		['unavailable', 424, { status: 'unavailable' }, { status: 'failed' }],
	])('%s', async (name, status, body, expected) => {
		globalThis.OC = { requestToken: 'token-1' }
		globalThis.fetch = vi
			.fn()
			.mockResolvedValueOnce({
				ok: status < 300,
				status,
				json: async () => body,
			})
		expect(await checkInByCode('ev1', CODE)).toEqual(expected)
	})

	it('fails when the request does not get through', async () => {
		globalThis.OC = { requestToken: 'token-1' }
		globalThis.fetch = vi.fn().mockRejectedValueOnce(new Error('offline'))
		expect(await checkInByCode('ev1', CODE)).toEqual({ status: 'failed' })
	})

	it('does not ask for a code that cannot be one', async () => {
		globalThis.fetch = vi.fn()
		expect(await checkInByCode('ev1', 'hello')).toEqual({ status: 'unknown' })
		expect(await checkInByCode('', CODE)).toEqual({ status: 'unknown' })
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('resultMessage', () => {
	it('names the participant who was checked in', () => {
		expect(
			resultMessage(t, {
				status: 'checked-in',
				name: 'Anna de Vries',
				character: 'Mirela the Wanderer',
			}),
		).toEqual({
			ok: true,
			text: 'Checked in: Anna de Vries, Mirela the Wanderer',
		})
	})

	it('says when and by whom for a second scan', () => {
		const at = new Date(2026, 11, 1, 18, 2).toISOString()
		expect(resultMessage(t, { status: 'already', at, by: 'Joris' })).toEqual({
			ok: false,
			text: 'Already checked in at 18:02 by Joris',
		})
	})

	it.each([
		[
			'not-accepted',
			'This registration is not accepted. Nobody was checked in.',
		],
		[
			'unknown',
			'Unknown code, or a code for another event. Nobody was checked in.',
		],
		['refused', 'Only game masters can check participants in.'],
		['busy', 'Too many attempts. Wait a moment and try again.'],
		['failed', 'Check-in failed. Try again.'],
	])('%s', (status, text) => {
		expect(resultMessage(t, { status })).toEqual({ ok: false, text })
	})
})
