/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for check-out on src/views/EventRoster.vue: the on-site-now
 * count, the "checked out {day} {time}" row text, and what a recorded
 * check-out writes onto the row.
 *
 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-the-roster-shows-who-is-on-site-now-req-eco-002
 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import EventRoster, { checkOutMoment, countOnSite } from '../../src/views/EventRoster.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars = {}) => key.replace(/{(\w+)}/g, (match, name) => vars[name] ?? match),
	translatePlural: (app, one) => one,
	getCanonicalLocale: () => 'nl-NL',
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnDataTable: {},
	CnIcon: {},
	CnStatusBadge: {},
	useObjectStore: () => ({}),
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (url) => url }))

const t = (app, key, vars = {}) => key.replace(/{(\w+)}/g, (match, name) => vars[name] ?? match)

afterEach(() => {
	vi.restoreAllMocks()
})

describe('on site now', () => {
	it('counts 38 of 40 checked in when 2 checked out', () => {
		const rows = [
			...Array.from({ length: 38 }, (_, i) => ({ character: `in-${i}`, status: 'checked-in' })),
			{ character: 'out-1', status: 'checked-out' },
			{ character: 'out-2', status: 'checked-out' },
			{ character: 'reg', status: 'registered' },
			{ character: 'gone', status: 'no-show' },
		]

		expect(countOnSite(rows)).toBe(38)
		expect(EventRoster.computed.onSiteCount.call({ participants: rows })).toBe(38)
	})

	it('counts someone checked in again after a check-out', () => {
		expect(countOnSite([{ status: 'checked-in' }, { status: 'checked-out' }])).toBe(1)
		expect(countOnSite([])).toBe(0)
	})
})

describe('checked out row text', () => {
	it('reads "Checked out" with the weekday and time in the locale', () => {
		vi.spyOn(Date.prototype, 'toLocaleDateString').mockReturnValue('za')
		vi.spyOn(Date.prototype, 'toLocaleTimeString').mockReturnValue('22:10')
		const row = { status: 'checked-out', checkedOutAt: '2026-12-05T22:10:00+01:00' }

		expect(EventRoster.methods.checkedOutLabel.call({ t }, row)).toBe('Checked out za 22:10')
		expect(Date.prototype.toLocaleDateString).toHaveBeenCalledWith('nl-NL', { weekday: 'short' })
	})

	it('formats a real stamp as a weekday and a time', () => {
		const moment = checkOutMoment('2026-12-05T22:10:00Z', 'en-GB')

		expect(moment.day).toMatch(/^\p{L}+/u)
		expect(moment.time).toMatch(/^\d{2}:\d{2}$/)
	})

	it.each([
		['a checked-in row', { status: 'checked-in', checkedOutAt: '2026-12-05T22:10:00+01:00' }],
		['a checked-out row without a stamp', { status: 'checked-out', checkedOutAt: '' }],
		['a checked-out row with a bad stamp', { status: 'checked-out', checkedOutAt: 'later' }],
	])('is empty for %s', (label, row) => {
		expect(EventRoster.methods.checkedOutLabel.call({ t }, row)).toBe('')
	})
})

describe('check-out write', () => {
	it('puts the server stamps on the row', async () => {
		globalThis.OC = { requestToken: 'token-1' }
		const sent = []
		globalThis.fetch = async (url, init) => {
			sent.push(JSON.parse(init.body))
			return {
				ok: true,
				status: 200,
				json: async () => ({ status: 'checked-out', checkedInAt: 'in', checkedInBy: 'gm-bram', checkedOutAt: '2026-12-05T22:10:00+01:00', checkedOutBy: 'gm-anna' }),
			}
		}
		const row = { character: 'isolde', status: 'checked-in', checkedInAt: 'in', checkedInBy: 'gm-bram', name: 'Isolde Raaf' }

		await EventRoster.methods.setStatus.call({ eventId: 'e1', saving: null }, row, 'checked-out')

		expect(sent).toEqual([{ character: 'isolde', status: 'checked-out' }])
		expect(row).toEqual({
			character: 'isolde',
			status: 'checked-out',
			checkedInAt: 'in',
			checkedInBy: 'gm-bram',
			checkedOutAt: '2026-12-05T22:10:00+01:00',
			checkedOutBy: 'gm-anna',
			name: 'Isolde Raaf',
		})
	})

	it('labels the checked-out status', () => {
		expect(EventRoster.methods.statusLabel.call({ t }, 'checked-out')).toBe('Checked out')
		expect(EventRoster.methods.statusVariant.call({}, 'checked-out')).toBe('warning')
	})
})
