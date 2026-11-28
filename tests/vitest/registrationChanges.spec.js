/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/registrationChanges.js: what the signed-in user
 * may change, cancelling with a refund or credit, adding a participant and
 * handing a registration over (registration-cancel-transfer-refund).
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	acceptTransfer,
	addParticipant,
	cancelRegistration,
	fetchChanges,
	offerTransfer,
	withdrawTransfer,
} from '../../src/services/registrationChanges.js'

const REG = 'e0000000-0000-4000-8000-000000000001'
const BASE = `/index.php/apps/larpinq/api/registrations/${REG}`

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

/**
 * A fetch that answers once.
 *
 * @param {number} status The status.
 * @param {object} body The JSON body.
 * @return {Function} The mock.
 */
function answering(status, body) {
	return vi.fn().mockResolvedValueOnce({
		ok: status < 400,
		status,
		json: async () => body,
	})
}

describe('fetchChanges', () => {
	it('asks larpinq what the user may change', async () => {
		globalThis.fetch = answering(200, { canCancel: true })
		const result = await fetchChanges(REG)
		expect(globalThis.fetch.mock.calls[0][0]).toBe(`${BASE}/changes`)
		expect(result).toEqual({ ok: true, data: { canCancel: true }, message: '' })
	})

	it('fails without asking when there is no id', async () => {
		globalThis.fetch = vi.fn()
		expect((await fetchChanges('')).ok).toBe(false)
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('cancelRegistration', () => {
	it('sends the choice of credit', async () => {
		globalThis.fetch = answering(200, { status: 'cancelled' })
		const result = await cancelRegistration(REG, 'credit')
		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe(`${BASE}/cancel`)
		expect(init.method).toBe('POST')
		expect(JSON.parse(init.body)).toEqual({ settlement: 'credit' })
		expect(result.data.status).toBe('cancelled')
	})

	it('sends no choice that is not refund or credit', async () => {
		globalThis.fetch = answering(200, {})
		await cancelRegistration(REG, 'half')
		expect(JSON.parse(globalThis.fetch.mock.calls[0][1].body)).toEqual({})
	})

	it("carries larpinq's reason when it is too late", async () => {
		globalThis.fetch = answering(409, {
			error: 'The cancel-by date has passed. Please contact the organisers to cancel.',
		})
		const result = await cancelRegistration(REG, '')
		expect(result.ok).toBe(false)
		expect(result.message).toContain('contact the organisers')
	})
})

describe('booking and transfer', () => {
	it('adds a participant by their trimmed name', async () => {
		globalThis.fetch = answering(200, { id: 'new' })
		await addParticipant(REG, ' Mila ')
		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe(`${BASE}/participants`)
		expect(JSON.parse(init.body)).toEqual({ name: 'Mila' })
	})

	it('offers, accepts and withdraws on their own endpoints', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: true,
			status: 200,
			json: async () => ({}),
		})
		await offerTransfer(REG, ' pieter ')
		await acceptTransfer(REG)
		await withdrawTransfer(REG)
		const calls = globalThis.fetch.mock.calls
		expect(calls.map((call) => call[0])).toEqual([
			`${BASE}/transfer`,
			`${BASE}/transfer/accept`,
			`${BASE}/transfer/withdraw`,
		])
		expect(JSON.parse(calls[0][1].body)).toEqual({ account: 'pieter' })
	})
})
