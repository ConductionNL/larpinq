/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the scan panel of src/views/EventRoster.vue: the camera is
 * used only where the browser reads QR codes, a typed or scanned code checks
 * the participant in and refreshes the roster, the answer shows for three
 * seconds, and the camera seeing the same code again does not resend it
 * (events-qr-checkin, REQ-EQC-003 / REQ-EQC-004).
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import EventRoster from '../../src/views/EventRoster.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
	translatePlural: (app, one) => one,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnDataTable: {},
	CnStatusBadge: {},
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (url) => url }))

const CODE = 'ABCDEFGHIJKLMNOPQRSTUVWXY2'
const m = EventRoster.methods

/**
 * A roster view's `this`, with the methods bound to it.
 *
 * @param {object} extra Extra state.
 * @return {object} The view.
 */
function view(extra = {}) {
	const self = {
		...EventRoster.data(),
		eventId: 'ev1',
		t: (app, text, vars = {}) =>
			text.replace(/\{(\w+)\}/g, (all, key) => vars[key] ?? all),
		$nextTick: async () => {},
		$refs: {},
		fetchRoster: vi.fn(async () => {}),
		...extra,
	}
	for (const name of Object.keys(m)) {
		if (!(name in self)) {
			self[name] = m[name].bind(self)
		}
	}
	return self
}

/**
 * A camera stream with one track.
 *
 * @return {object} The stream.
 */
function stream() {
	const track = { stop: vi.fn() }
	return { track, getTracks: () => [track] }
}

beforeEach(() => {
	vi.useFakeTimers()
	globalThis.OC = { requestToken: 'token-1' }
})

afterEach(() => {
	vi.useRealTimers()
	vi.restoreAllMocks()
	delete globalThis.fetch
	delete globalThis.OC
	delete globalThis.BarcodeDetector
	vi.unstubAllGlobals()
})

describe('EventRoster scan: typed codes', () => {
	it('checks the participant in, shows who, refreshes the roster and clears the field', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: async () => ({
				status: 'checked-in',
				name: 'Anna de Vries',
				character: 'Mirela the Wanderer',
			}),
		})
		const self = view({ scanCode: CODE })

		await self.submitCode(` ${CODE.toLowerCase()} `)

		expect(JSON.parse(globalThis.fetch.mock.calls[0][1].body)).toEqual({
			code: CODE,
		})
		expect(self.scanResult).toEqual({
			ok: true,
			text: 'Checked in: Anna de Vries, Mirela the Wanderer',
		})
		expect(self.fetchRoster).toHaveBeenCalledTimes(1)
		expect(self.scanCode).toBe('')
		expect(self.scanBusy).toBe(false)

		vi.advanceTimersByTime(2999)
		expect(self.scanResult).not.toBeNull()
		vi.advanceTimersByTime(1)
		expect(self.scanResult).toBeNull()
	})

	it('reports a second use without refreshing the roster', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: async () => ({ status: 'already', at: 'not a date', by: 'Joris' }),
		})
		const self = view()

		await self.submitCode(CODE)

		expect(self.scanResult).toEqual({
			ok: false,
			text: 'Already checked in at not a date by Joris',
		})
		expect(self.fetchRoster).not.toHaveBeenCalled()
	})

	it('does not resend the code whose answer is still shown, and does once it is gone', async () => {
		globalThis.fetch = vi
			.fn()
			.mockResolvedValue({
				ok: false,
				status: 404,
				json: async () => ({ status: 'unknown' }),
			})
		const self = view()

		await self.submitCode(CODE)
		await self.submitCode(CODE)
		expect(globalThis.fetch).toHaveBeenCalledTimes(1)

		vi.advanceTimersByTime(3000)
		await self.submitCode(CODE)
		expect(globalThis.fetch).toHaveBeenCalledTimes(2)
	})

	it('sends nothing while a code is being checked in', async () => {
		globalThis.fetch = vi.fn()
		const self = view({ scanBusy: true })
		await self.submitCode(CODE)
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('EventRoster scan: the camera', () => {
	it('stays off without a BarcodeDetector, leaving the code field', async () => {
		const getUserMedia = vi.fn()
		vi.stubGlobal('navigator', { mediaDevices: { getUserMedia } })
		const self = view()

		await self.toggleScan()

		expect(self.scanOpen).toBe(true)
		expect(self.cameraOn).toBe(false)
		expect(getUserMedia).not.toHaveBeenCalled()
	})

	it('stays off when the detector cannot read QR codes', async () => {
		globalThis.BarcodeDetector = class {
			static async getSupportedFormats() {
				return ['ean_13']
			}
		}
		const getUserMedia = vi.fn()
		vi.stubGlobal('navigator', { mediaDevices: { getUserMedia } })
		const self = view()

		await self.toggleScan()

		expect(self.cameraOn).toBe(false)
		expect(getUserMedia).not.toHaveBeenCalled()
	})

	it('reads camera frames and checks in the code it sees, then stops on close', async () => {
		const detect = vi
			.fn()
			.mockResolvedValueOnce([])
			.mockResolvedValue([{ rawValue: CODE }])
		let formats = null
		globalThis.BarcodeDetector = class {
			constructor(options) {
				formats = options.formats
			}

			static async getSupportedFormats() {
				return ['qr_code']
			}

			detect(source) {
				return detect(source)
			}
		}
		const camera = stream()
		const getUserMedia = vi.fn().mockResolvedValue(camera)
		vi.stubGlobal('navigator', { mediaDevices: { getUserMedia } })
		const video = { play: vi.fn(async () => {}) }
		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: true,
			status: 200,
			json: async () => ({
				status: 'checked-in',
				name: 'Anna de Vries',
				character: 'Mirela the Wanderer',
			}),
		})
		const self = view({ $refs: { scanVideo: video } })

		await self.toggleScan()

		expect(formats).toEqual(['qr_code'])
		expect(getUserMedia).toHaveBeenCalledWith({
			video: { facingMode: 'environment' },
			audio: false,
		})
		expect(self.cameraOn).toBe(true)
		expect(video.srcObject).toBe(camera)

		await vi.advanceTimersByTimeAsync(300)
		expect(globalThis.fetch).not.toHaveBeenCalled()
		await vi.advanceTimersByTimeAsync(300)
		expect(detect).toHaveBeenLastCalledWith(video)
		expect(globalThis.fetch).toHaveBeenCalledTimes(1)
		expect(self.scanResult.text).toBe(
			'Checked in: Anna de Vries, Mirela the Wanderer',
		)
		// The same code stays in view while its answer shows: not resent.
		await vi.advanceTimersByTimeAsync(900)
		expect(globalThis.fetch).toHaveBeenCalledTimes(1)

		await self.toggleScan()
		expect(self.scanOpen).toBe(false)
		expect(self.cameraOn).toBe(false)
		expect(camera.track.stop).toHaveBeenCalled()
		const calls = detect.mock.calls.length
		await vi.advanceTimersByTimeAsync(900)
		expect(detect.mock.calls.length).toBe(calls)
	})

	it('stays off when the steward refuses the camera', async () => {
		globalThis.BarcodeDetector = class {
			static async getSupportedFormats() {
				return ['qr_code']
			}
		}
		vi.stubGlobal('navigator', {
			mediaDevices: {
				getUserMedia: vi
					.fn()
					.mockRejectedValue(new Error('NotAllowedError')),
			},
		})
		const self = view()

		await self.toggleScan()

		expect(self.scanOpen).toBe(true)
		expect(self.cameraOn).toBe(false)
	})
})
