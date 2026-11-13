/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The failure paths of the registration and attendance reads: a refused or
 * failed answer, an answer that is not JSON, a missing id and missing fields.
 *
 * @spec openspec/specs/event-registration/spec.md
 * @spec openspec/specs/events-players/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { fetchPlayerAttendance } from '../../src/services/playerAttendance.js'
import {
	acceptTransfer,
	addParticipant,
	fetchChanges,
	offerTransfer,
} from '../../src/services/registrationChanges.js'
import {
	choiceBody,
	fetchChoiceCounts,
	fetchOffer,
	formatPrice,
} from '../../src/services/registrationChoices.js'

const realFetch = globalThis.fetch

afterEach(() => {
	globalThis.fetch = realFetch
})

describe('registration changes', () => {
	it('reads a non-JSON success as empty data', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.reject(new SyntaxError('x')) })

		expect(await fetchChanges('r1')).toEqual({ ok: true, data: {}, message: '' })
	})

	it('reads a JSON null success as empty data', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(null) })

		expect(await fetchChanges('r1')).toEqual({ ok: true, data: {}, message: '' })
	})

	it('fails with no message when the refusal carries none or is not JSON', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: false, json: () => Promise.resolve({}) })
		expect(await acceptTransfer('r1')).toEqual({ ok: false, data: {}, message: '' })

		globalThis.fetch = vi.fn().mockResolvedValue({ ok: false, json: () => Promise.reject(new SyntaxError('x')) })
		expect(await acceptTransfer('r1')).toEqual({ ok: false, data: {}, message: '' })
	})

	it('posts nothing without a registration id', async () => {
		const fetch = vi.fn()
		globalThis.fetch = fetch

		expect(await addParticipant('', 'Bo')).toEqual({ ok: false, data: {}, message: '' })
		expect(await offerTransfer(undefined, 'bo')).toEqual({ ok: false, data: {}, message: '' })
		expect(fetch).not.toHaveBeenCalled()
	})

	it('sends an empty name and account for missing input', async () => {
		const fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve({}) })
		globalThis.fetch = fetch

		await addParticipant('r1', null)
		await offerTransfer('r1', undefined)

		expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ name: '' })
		expect(JSON.parse(fetch.mock.calls[1][1].body)).toEqual({ account: '' })
	})
})

describe('registration choices', () => {
	it('fails on a non-403 error', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: false, status: 500 })

		expect(await fetchOffer('r1', '')).toEqual({ state: 'failed' })
		expect(await fetchChoiceCounts('e1')).toEqual({ state: 'failed' })
	})

	it('asks for the offer without a code query for a blank code', async () => {
		const fetch = vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve({ ticketTypes: [] }) })
		globalThis.fetch = fetch

		expect(await fetchOffer('r1', '   ')).toEqual({ state: 'ok', ticketTypes: [] })
		expect(fetch.mock.calls[0][0]).toBe('/index.php/apps/larpinq/api/registrations/r1/offer')
	})

	it('reads no counts without an event id', async () => {
		const fetch = vi.fn()
		globalThis.fetch = fetch

		expect(await fetchChoiceCounts('')).toEqual({ state: 'failed' })
		expect(fetch).not.toHaveBeenCalled()
	})

	it('sends no options when choices are missing', () => {
		expect(choiceBody(undefined)).toEqual({ options: [] })
		expect(choiceBody({ options: 'one', ticketType: 7, code: ' X ' })).toEqual({ options: [], ticketType: '7', code: 'X' })
	})

	it('prints in euros without a currency and nothing for an unknown currency', () => {
		expect(formatPrice(1250, '')).toBe(formatPrice(1250, 'EUR'))
		expect(formatPrice(1250, '')).not.toBe('')
		expect(formatPrice(1250, 'not a currency')).toBe('')
	})
})

describe('player attendance', () => {
	it('fails on a non-403 error', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: false, status: 502 })

		expect(await fetchPlayerAttendance('p1')).toEqual({ state: 'failed' })
	})

	it('reads a missing count and events as none', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve({ count: 'many', events: 'x' }) })

		expect(await fetchPlayerAttendance('p1')).toEqual({ state: 'ok', count: 0, events: [] })
	})

	it('reads a null answer as no history', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve(null) })

		expect(await fetchPlayerAttendance('p1')).toEqual({ state: 'ok', count: 0, events: [] })
	})
})
