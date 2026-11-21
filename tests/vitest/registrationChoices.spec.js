/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/registrationChoices.js: the ticket offer of a
 * registration, saving the choices, the event's counts and how a listed price
 * is printed (registration-ticket-types-and-options).
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	choiceBody,
	fetchChoiceCounts,
	fetchOffer,
	formatPrice,
	saveChoices,
} from '../../src/services/registrationChoices.js'

const REG = 'e0000000-0000-4000-8000-000000000001'

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('fetchOffer', () => {
	it('asks larpinq for the offer with the typed code', async () => {
		const offer = {
			ticketTypes: [
				{
					id: 't1',
					name: 'Player',
					amount: 11000,
					currency: 'EUR',
					full: false,
				},
			],
			options: [],
			code: 'valid',
			chosen: { ticketType: '', options: [] },
		}
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: async () => offer,
		})
		const result = await fetchOffer(REG, ' LANTERN ')
		expect(globalThis.fetch.mock.calls[0][0]).toBe(
			`/index.php/apps/larpinq/api/registrations/${REG}/offer?code=LANTERN`,
		)
		expect(result).toEqual({ state: 'ok', ...offer })
	})

	it('says so when the caller is not the player or a game master', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: false, status: 403 })
		expect(await fetchOffer(REG, '')).toEqual({ state: 'refused' })
	})

	it('fails without asking when there is no id', async () => {
		globalThis.fetch = vi.fn()
		expect(await fetchOffer('', '')).toEqual({ state: 'failed' })
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('saveChoices', () => {
	it('sends the ticket type, options and code, never a price', async () => {
		globalThis.fetch = vi
			.fn()
			.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({}) })
		const result = await saveChoices(REG, {
			ticketType: 't1',
			options: ['o1'],
			code: ' lantern ',
			lines: [{ amount: 1 }],
		})
		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe(
			`/index.php/apps/openregister/api/objects/larpinq/larping_registration/${REG}`,
		)
		expect(init.method).toBe('PATCH')
		expect(JSON.parse(init.body)).toEqual({
			ticketType: 't1',
			options: ['o1'],
			code: 'lantern',
		})
		expect(result).toEqual({ ok: true, message: '' })
	})

	it('passes on the reason larpinq refused', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: false,
			status: 400,
			json: async () => ({ errors: { message: 'This option is full.' } }),
		})
		expect(
			await saveChoices(REG, { ticketType: '', options: [], code: '' }),
		).toEqual({
			ok: false,
			message: 'This option is full.',
		})
	})
})

describe('choiceBody', () => {
	it('leaves out an empty ticket type and code', () => {
		expect(choiceBody({ ticketType: '', options: [], code: '' })).toEqual({
			options: [],
		})
	})
})

describe('fetchChoiceCounts', () => {
	it('reads the counts of an event', async () => {
		const counts = {
			ticketTypes: [{ id: 't1', name: 'Player', count: 3 }],
			options: [],
		}
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: async () => counts,
		})
		expect(await fetchChoiceCounts('ev1')).toEqual({ state: 'ok', ...counts })
		expect(globalThis.fetch.mock.calls[0][0]).toBe(
			'/index.php/apps/larpinq/api/events/ev1/choices',
		)
	})

	it('says so to a player', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: false, status: 403 })
		expect(await fetchChoiceCounts('ev1')).toEqual({ state: 'refused' })
	})
})

describe('formatPrice', () => {
	it('prints cents as an amount in the currency', () => {
		expect(formatPrice(11000, 'EUR')).toBe(
			new Intl.NumberFormat(undefined, {
				style: 'currency',
				currency: 'EUR',
			}).format(110),
		)
	})

	it('prints nothing for a broken amount', () => {
		expect(formatPrice('x', 'EUR')).toBe('')
	})
})
