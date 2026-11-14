/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/characterStats.js: the Stats tab's fetch and
 * how it renders positive, negative and absent modifiers.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	changeTone,
	fetchCharacterStats,
	formatChange,
	formatHistoryDate,
	hasModifiers,
	xpHistory,
} from '../../src/services/characterStats.js'

describe('fetchCharacterStats', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('reads the sheet from the stats endpoint', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			json: async () => ({ abilities: [{ id: 'str' }], xp: { left: 30 } }),
		})
		const sheet = await fetchCharacterStats('abc')
		expect(globalThis.fetch.mock.calls[0][0]).toBe(
			'/index.php/apps/larpinq/api/characters/abc/stats',
		)
		expect(sheet).toEqual({ abilities: [{ id: 'str' }], xp: { left: 30 } })
	})

	it('returns null when the character cannot be read', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: false, status: 404 })
		expect(await fetchCharacterStats('abc')).toBeNull()
	})

	it('does not ask without an id', async () => {
		globalThis.fetch = vi.fn()
		expect(await fetchCharacterStats('')).toBeNull()
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('modifier rendering', () => {
	it('prints a positive change with a plus', () => {
		expect(formatChange(3)).toBe('+3')
		expect(changeTone(3)).toBe('positive')
	})

	it('marks a negative change so it stands out', () => {
		expect(formatChange(-2)).toBe('-2')
		expect(changeTone(-2)).toBe('negative')
	})

	it('treats an ability without modifiers as untouched', () => {
		expect(hasModifiers({ modifiers: [] })).toBe(false)
		expect(hasModifiers({})).toBe(false)
		expect(hasModifiers({ modifiers: [{ change: 1 }] })).toBe(true)
		expect(changeTone(0)).toBe('none')
	})
})

describe('XP history (REQ-CSP-006)', () => {
	it('passes the history the server sends through the fetch', async () => {
		const history = [{ skillId: 'sk-herb', skillName: 'Kruidenkunde', xp: 6, date: '2026-11-14T09:00:00+00:00', user: 'Anna' }]
		globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: true, json: async () => ({ abilities: [], xp: { left: 36, history } }) })

		expect(xpHistory(await fetchCharacterStats('abc'))).toEqual(history)
		delete globalThis.fetch
	})

	it('formats a valid moment as a date and leaves an invalid one empty', () => {
		expect(formatHistoryDate('2026-11-14T09:00:00+00:00')).toBe(new Date('2026-11-14T09:00:00+00:00').toLocaleDateString())
		expect(formatHistoryDate('soon')).toBe('')
		expect(formatHistoryDate('')).toBe('')
	})
})
