/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/xpAwardBatch.js and the manifest wiring of
 * events-xp-batch-award: the Award XP action on the event page, game masters
 * only, the default ticks and the rows a save sends (event-xp-awards, the
 * batch requirement; REQ-EXB-001).
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import manifest from '../../src/manifest.json'
import {
	fetchAwardRoster,
	initialRows,
	refusalText,
	rowsToSave,
	saveAwards,
} from '../../src/services/xpAwardBatch.js'

const REGISTRY = fs.readFileSync(
	path.resolve(
		path.dirname(fileURLToPath(import.meta.url)),
		'../../src/registry.js',
	),
	'utf8',
)

const EVENT = '5a5a5a5a-0000-4000-8000-000000000001'
const roster = [
	{
		character: 'm',
		name: 'Mirela the Wanderer',
		attendance: 'checked-in',
		awards: [],
		ticked: true,
	},
	{
		character: 'b',
		name: 'Sir Bertram',
		attendance: 'checked-in',
		awards: [{ id: 'x1', amount: 5 }],
		ticked: false,
	},
	{
		character: 'h',
		name: 'Old Captain Harrow',
		attendance: 'no-show',
		awards: [],
		ticked: false,
	},
	{
		character: 'n',
		name: 'Quiet Nell',
		attendance: '',
		awards: [],
		ticked: false,
	},
]

function mockFetch(answer) {
	const fn = vi.fn().mockResolvedValue({
		ok: answer.ok ?? true,
		status: answer.status ?? 200,
		json: async () => answer.json ?? {},
	})
	globalThis.fetch = fn
	return fn
}

afterEach(() => {
	delete globalThis.fetch
})

describe('the Award XP action', () => {
	it('sits on the event page for game masters only', () => {
		const page = manifest.pages.find((p) => p.id === 'EventDetail')
		const action = page.config.headerActions.find((a) => a.id === 'award-xp')
		expect(action.type).toBe('open-modal')
		expect(action.target).toBe('XpAwardDialog')
		expect(action.visibleWhen).toEqual({
			endpoint: '/apps/larpinq/api/xp-awards/access',
			field: 'allowed',
			op: 'eq',
			value: true,
		})
		expect(REGISTRY).toMatch(/XpAwardDialog: \{\s*kind: 'modal'/)
	})
})

describe('the default ticks', () => {
	it('keeps the server ticks and marks who needs a hint', () => {
		const rows = initialRows(roster)
		expect(rows.map((r) => r.ticked)).toEqual([true, false, false, false])
		expect(rows.map((r) => r.noCheckIn)).toEqual([false, false, false, true])
		expect(rows.every((r) => r.amount === '' && r.reason === '')).toBe(true)
	})
})

describe('the rows a save sends', () => {
	it('sends ticked rows with the default amount and reason unless overridden', () => {
		const rows = initialRows(roster)
		rows[2].ticked = true
		rows[2].amount = '2'
		rows[2].reason = 'Saturday only'
		expect(rowsToSave(rows, '3', 'Attended Summer Siege')).toEqual([
			{
				character: 'm',
				amount: 3,
				reason: 'Attended Summer Siege',
				extra: false,
			},
			{ character: 'h', amount: 2, reason: 'Saturday only', extra: false },
		])
	})

	it('marks a row that already has an award as an extra award', () => {
		const rows = initialRows(roster)
		rows[1].ticked = true
		expect(rowsToSave(rows, '2', 'Plot bonus')).toContainEqual({
			character: 'b',
			amount: 2,
			reason: 'Plot bonus',
			extra: true,
		})
	})

	it('sends nothing without a positive amount', () => {
		expect(rowsToSave(initialRows(roster), '', '')).toEqual([])
		expect(rowsToSave(initialRows(roster), '0', '')).toEqual([])
	})
})

describe('the server calls', () => {
	it('reads the award roster of the event', async () => {
		const fetch = mockFetch({
			json: {
				eventName: 'Summer Siege',
				rows: roster,
				attendanceAvailable: true,
			},
		})
		const state = await fetchAwardRoster(EVENT)
		expect(fetch.mock.calls[0][0]).toContain(
			`/apps/larpinq/api/events/${EVENT}/xp-award-roster`,
		)
		expect(state.status).toBe('ready')
		expect(state.eventName).toBe('Summer Siege')
		expect(state.rows).toHaveLength(4)
	})

	it('says when the user may not award', async () => {
		mockFetch({ ok: false, status: 403, json: { error: 'Access denied' } })
		expect((await fetchAwardRoster(EVENT)).status).toBe('forbidden')
	})

	it('posts the rows and returns what was made and refused', async () => {
		const fetch = mockFetch({
			json: {
				created: [{ id: 'a1' }],
				refused: [{ character: 'b', reason: 'duplicate' }],
			},
		})
		const result = await saveAwards(EVENT, [
			{ character: 'm', amount: 3, reason: '', extra: false },
		])
		const [url, init] = fetch.mock.calls[0]
		expect(url).toContain(`/apps/larpinq/api/events/${EVENT}/xp-awards`)
		expect(init.method).toBe('POST')
		expect(JSON.parse(init.body).rows).toHaveLength(1)
		expect(result).toEqual({
			ok: true,
			created: 1,
			refused: [{ character: 'b', reason: 'duplicate' }],
			error: '',
		})
	})

	it('explains every refusal', () => {
		for (const code of [
			'not-on-roster',
			'invalid-amount',
			'duplicate',
			'extra-needs-reason',
			'not-saved',
		]) {
			expect(refusalText(code)).not.toBe(code)
		}
	})
})
