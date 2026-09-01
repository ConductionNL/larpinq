/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the Larpinq settings Pinia store
 * (src/store/modules/settings.js): fetch envelope handling, the
 * openRegisters / isAdmin flag derivation, the config-shape fallback
 * (configuration → config → raw), the error + loading lifecycle, and the
 * save / reimport round-trips. global fetch + the OC global are mocked.
 */

import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useSettingsStore } from '../../src/store/modules/settings.js'

function mockFetchOnce({ ok = true, statusText = 'OK', json = {} }) {
	globalThis.fetch = vi.fn().mockResolvedValueOnce({
		ok,
		statusText,
		json: async () => json,
	})
}

describe('larpinq settings store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		globalThis.OC = { requestToken: 'test-token' }
	})

	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
		delete globalThis.OC
	})

	it('has sensible defaults and getters', () => {
		const store = useSettingsStore()
		expect(store.config).toBeNull()
		expect(store.loading).toBe(false)
		expect(store.error).toBeNull()
		expect(store.initialized).toBe(false)
		expect(store.hasOpenRegisters).toBe(false)
		expect(store.getIsAdmin).toBe(false)
		expect(store.isInitialized).toBe(false)
	})

	it('fetchSettings stores config, derives flags and marks initialized', async () => {
		mockFetchOnce({
			json: {
				openRegisters: true,
				isAdmin: true,
				configuration: { meetingSchema: 'meeting' },
			},
		})
		const store = useSettingsStore()
		const config = await store.fetchSettings()
		expect(globalThis.fetch).toHaveBeenCalledWith(
			'/apps/larpinq/api/settings',
			expect.objectContaining({
				method: 'GET',
				headers: expect.objectContaining({
					requesttoken: 'test-token',
					'OCS-APIREQUEST': 'true',
				}),
			}),
		)
		expect(config).toEqual({ meetingSchema: 'meeting' })
		expect(store.hasOpenRegisters).toBe(true)
		expect(store.getIsAdmin).toBe(true)
		expect(store.initialized).toBe(true)
		expect(store.loading).toBe(false)
	})

	it('fetchSettings coerces absent flags to false and falls back through config shapes', async () => {
		mockFetchOnce({ json: { config: { a: 1 } } })
		const store = useSettingsStore()
		const config = await store.fetchSettings()
		expect(config).toEqual({ a: 1 })
		expect(store.hasOpenRegisters).toBe(false)
		expect(store.getIsAdmin).toBe(false)
	})

	it('fetchSettings uses the raw body when neither configuration nor config present', async () => {
		mockFetchOnce({ json: { raw: 'value' } })
		const store = useSettingsStore()
		const config = await store.fetchSettings()
		expect(config).toEqual({ raw: 'value' })
	})

	it('fetchSettings records the error and returns null on a non-ok response', async () => {
		mockFetchOnce({ ok: false, statusText: 'Forbidden' })
		vi.spyOn(console, 'error').mockImplementation(() => {})
		const store = useSettingsStore()
		const result = await store.fetchSettings()
		expect(result).toBeNull()
		expect(store.error).toBe('Failed to fetch settings: Forbidden')
		expect(store.loading).toBe(false)
		expect(store.initialized).toBe(false)
	})

	it('fetchSettings records a network error and returns null', async () => {
		globalThis.fetch = vi.fn().mockRejectedValueOnce(new Error('offline'))
		vi.spyOn(console, 'error').mockImplementation(() => {})
		const store = useSettingsStore()
		const result = await store.fetchSettings()
		expect(result).toBeNull()
		expect(store.error).toBe('offline')
		expect(store.loading).toBe(false)
	})

	it('saveSettings POSTs the body and stores the returned config', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			statusText: 'OK',
			json: async () => ({ config: { saved: true } }),
		})
		const store = useSettingsStore()
		const result = await store.saveSettings({ x: 1 })
		const [, opts] = globalThis.fetch.mock.calls[0]
		expect(opts.method).toBe('POST')
		expect(JSON.parse(opts.body)).toEqual({ x: 1 })
		expect(result).toEqual({ saved: true })
		expect(store.config).toEqual({ saved: true })
	})

	it('reimportConfiguration POSTs to the reimport endpoint and returns the payload', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			statusText: 'OK',
			json: async () => ({ config: { reimported: true }, status: 'done' }),
		})
		const store = useSettingsStore()
		const result = await store.reimportConfiguration()
		const [url, opts] = globalThis.fetch.mock.calls[0]
		expect(url).toBe('/apps/larpinq/api/settings/reimport')
		expect(opts.method).toBe('POST')
		expect(result).toEqual({ config: { reimported: true }, status: 'done' })
		expect(store.config).toEqual({ reimported: true })
	})
})
