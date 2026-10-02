/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/checkinCode.js: reading a registration's
 * check-in code, drawing it as a QR code, detecting camera scanning, and
 * checking in by code (events-qr-checkin).
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	cameraScanSupported,
	checkInByCode,
	fetchCheckinCode,
	qrSvg,
} from '../../src/services/checkinCode.js'

const REG = 'e0000000-0000-4000-8000-000000000001'
const EVENT = 'a0000000-0000-4000-8000-000000000001'
const CODE = 'ABCDEFGHJKMNPQRSTVWXYZ2345'

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

/**
 * A JSON answer.
 *
 * @param {number} status The status.
 * @param {object} body The body.
 * @return {object} The response.
 */
function json(status, body) {
	return { ok: status < 400, status, json: async () => body }
}

describe('fetchCheckinCode', () => {
	it('reads the code and the names through the objects API', async () => {
		globalThis.fetch = vi.fn(async (url) => {
			if (url.endsWith(`/larping_registration/${REG}`)) {
				return json(200, {
					checkinCode: CODE,
					status: 'accepted',
					player: 'p1',
					character: 'c1',
					event: EVENT,
				})
			}
			const names = {
				'/player/p1': 'Anna de Vries',
				'/character/c1': 'Mirela the Wanderer',
				[`/larping_event/${EVENT}`]: 'Winter Court 2026',
			}
			const key = Object.keys(names).find((suffix) => url.endsWith(suffix))
			return json(200, { name: names[key] })
		})
		expect(await fetchCheckinCode(REG)).toEqual({
			state: 'ok',
			code: CODE,
			status: 'accepted',
			name: 'Anna de Vries',
			character: 'Mirela the Wanderer',
			event: 'Winter Court 2026',
		})
	})

	it('fails without asking when there is no id, and on a refused read', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue(json(403, {}))
		expect((await fetchCheckinCode('')).state).toBe('failed')
		expect(globalThis.fetch).not.toHaveBeenCalled()
		expect((await fetchCheckinCode(REG)).state).toBe('failed')
	})
})

describe('qrSvg', () => {
	it('draws a code as an SVG', async () => {
		const svg = await qrSvg(CODE)
		expect(svg.startsWith('<svg')).toBe(true)
		expect(svg).toContain('<path')
	})

	it('draws nothing without a code', async () => {
		expect(await qrSvg('')).toBe('')
	})
})

describe('cameraScanSupported', () => {
	it('is true when BarcodeDetector reads qr_code', async () => {
		const scope = {
			BarcodeDetector: {
				getSupportedFormats: async () => ['ean_13', 'qr_code'],
			},
		}
		expect(await cameraScanSupported(scope)).toBe(true)
	})

	it('is false without BarcodeDetector or without qr_code', async () => {
		expect(await cameraScanSupported({})).toBe(false)
		expect(
			await cameraScanSupported({
				BarcodeDetector: { getSupportedFormats: async () => ['ean_13'] },
			}),
		).toBe(false)
	})
})

describe('checkInByCode', () => {
	it('posts the code to the event and returns the answer', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue(
			json(200, {
				status: 'checked-in',
				name: 'Anna de Vries',
				character: 'Mirela the Wanderer',
			}),
		)
		const answer = await checkInByCode(EVENT, CODE)
		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe(`/index.php/apps/larpinq/api/events/${EVENT}/checkin-code`)
		expect(init.method).toBe('POST')
		expect(JSON.parse(init.body)).toEqual({ code: CODE })
		expect(answer.status).toBe('checked-in')
	})

	it('passes on an unknown code and reads a refusal', async () => {
		globalThis.fetch = vi
			.fn()
			.mockResolvedValueOnce(json(404, { status: 'unknown' }))
			.mockResolvedValueOnce(json(403, { error: 'Access denied' }))
		expect((await checkInByCode(EVENT, 'X')).status).toBe('unknown')
		expect((await checkInByCode(EVENT, CODE)).status).toBe('refused')
	})
})
