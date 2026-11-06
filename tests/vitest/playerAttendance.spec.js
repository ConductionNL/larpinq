/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/playerAttendance.js: the Events attended tab's
 * fetch, its refusal and its dates.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	fetchPlayerAttendance,
	formatEventDate,
} from '../../src/services/playerAttendance.js'

describe('fetchPlayerAttendance', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('reads the history from the player attendance endpoint', async () => {
		const events = [{ id: 'a1', event: { id: 'e1', name: 'Summer Siege 2025' } }]
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: async () => ({ count: 1, events }),
		})
		const history = await fetchPlayerAttendance('anna')
		expect(globalThis.fetch.mock.calls[0][0]).toBe(
			'/index.php/apps/larpinq/api/players/anna/attendance',
		)
		expect(history).toEqual({ state: 'ok', count: 1, events })
	})

	it('says so when the caller may not see this player', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: false, status: 403 })
		expect(await fetchPlayerAttendance('anna')).toEqual({ state: 'refused' })
	})

	it('fails without asking when there is no id', async () => {
		globalThis.fetch = vi.fn()
		expect(await fetchPlayerAttendance('')).toEqual({ state: 'failed' })
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('formatEventDate', () => {
	it('prints nothing for a missing or broken date', () => {
		expect(formatEventDate('')).toBe('')
		expect(formatEventDate('not a date')).toBe('')
	})

	it('prints the day of a date-time', () => {
		expect(formatEventDate('2025-07-20T10:00:00+00:00')).toBe(
			new Date('2025-07-20T10:00:00+00:00').toLocaleDateString(),
		)
	})
})
