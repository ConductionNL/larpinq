/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/dialogs/XpAwardDialog.vue: loading the roster, the
 * labels it shows, and saving the ticked awards. Only fetch is faked; the
 * real xpAwardBatch service runs.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import XpAwardDialog from '../../src/dialogs/XpAwardDialog.vue'

vi.mock('@nextcloud/l10n', () => {
	const translate = (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => String(vars[name] ?? match))
	return { translate, translatePlural: (app, one) => one }
})
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))

const ROSTER = '/index.php/apps/larpinq/api/events/ev-1/xp-award-roster'
const AWARDS = '/index.php/apps/larpinq/api/events/ev-1/xp-awards'

/**
 * Fake fetch answering each request in turn.
 *
 * @param {Array<{status: number, body: object}>} answers The answers, in order.
 * @return {Array<{url: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		calls.push({ url, method: options.method ?? 'GET', body: options.body ? JSON.parse(options.body) : undefined })
		const answer = answers.shift()
		return { ok: answer.status < 400, status: answer.status, json: async () => answer.body }
	}
	return calls
}

/**
 * The dialog's state, bound to its methods, with toSave computed live.
 *
 * @return {object} The instance.
 */
function instance() {
	const self = { ...XpAwardDialog.data(), id: 'ev-1' }
	for (const [name, method] of Object.entries(XpAwardDialog.methods)) {
		self[name] = method.bind(self)
	}
	Object.defineProperty(self, 'toSave', { get: () => XpAwardDialog.computed.toSave.call(self) })
	return self
}

afterEach(() => {
	delete globalThis.fetch
})

describe('XpAwardDialog load', () => {
	it('loads the roster and proposes the reason from the event', async () => {
		const calls = fakeFetch([
			{ status: 200, body: { eventName: 'Winter Court', rows: [{ character: 'c-1', name: 'Aldric', attendance: 'checked-in', ticked: true }] } },
		])
		const self = instance()

		await XpAwardDialog.mounted.call(self)

		expect(calls.map((call) => call.url)).toEqual([ROSTER])
		expect(self.status).toBe('ready')
		expect(self.rows.map((row) => [row.character, row.ticked, row.noCheckIn])).toEqual([['c-1', true, false]])
		expect(self.defaultReason).toBe('Attended Winter Court')
	})

	it('keeps a reason already typed', async () => {
		fakeFetch([{ status: 200, body: { eventName: 'Winter Court', rows: [] } }])
		const self = instance()
		self.defaultReason = 'Great roleplay'

		await self.load()

		expect(self.defaultReason).toBe('Great roleplay')
	})

	it('is forbidden for someone who may not award', async () => {
		fakeFetch([{ status: 403, body: {} }])
		const self = instance()

		await self.load()

		expect(self.status).toBe('forbidden')
		expect(self.rows).toEqual([])
		expect(self.defaultReason).toBe('')
	})
})

describe('XpAwardDialog labels', () => {
	it.each([
		['checked-in', 'Checked in'],
		['no-show', 'No-show'],
		['', 'No check-in recorded'],
	])('attendance %s reads %s', (attendance, label) => {
		expect(XpAwardDialog.methods.attendanceLabel({ attendance })).toBe(label)
	})

	it.each([
		[{ amount: 5, awardedBy: 'gm', reason: 'Quest' }, '5 XP by gm: Quest'],
		[{ amount: 5, awardedBy: 'gm' }, '5 XP by gm'],
		[{ amount: 2 }, '2 XP by Unknown'],
	])('award %j reads %s', (award, label) => {
		expect(XpAwardDialog.methods.awardLabel(award)).toBe(label)
	})

	it('names a character from the roster, else by id', () => {
		const self = { rows: [{ character: 'c-1', name: 'Aldric' }] }

		expect(XpAwardDialog.methods.nameOf.call(self, 'c-1')).toBe('Aldric')
		expect(XpAwardDialog.methods.nameOf.call(self, 'c-9')).toBe('c-9')
	})

	it('opens an award and closes', () => {
		const push = vi.fn()
		const emitted = []

		XpAwardDialog.methods.openAward.call({ $router: { push }, $emit: (...args) => emitted.push(args) }, 'a/1')

		expect(push).toHaveBeenCalledWith('/xp-awards/a%2F1')
		expect(emitted).toEqual([['close']])
	})

	it('reads the event id from the prop, else the route', () => {
		expect(XpAwardDialog.computed.id.call({ eventId: 'ev-1', $route: { params: { id: 'ev-2' } } })).toBe('ev-1')
		expect(XpAwardDialog.computed.id.call({ eventId: '@x', $route: { params: { id: 'ev-2' } } })).toBe('ev-2')
	})
})

describe('XpAwardDialog save', () => {
	/**
	 * A loaded dialog with one ticked and one unticked row.
	 *
	 * @return {Promise<{self: object, calls: Array<object>}>} The dialog and the fetch calls.
	 */
	async function loaded(answers) {
		const calls = fakeFetch([
			{
				status: 200,
				body: {
					eventName: 'Winter Court',
					rows: [
						{ character: 'c-1', attendance: 'checked-in', ticked: true },
						{ character: 'c-2', attendance: 'no-show', ticked: false },
					],
				},
			},
			...answers,
		])
		const self = instance()
		await self.load()
		self.defaultAmount = '3'
		calls.length = 0
		return { self, calls }
	}

	it('saves the ticked rows and reloads the roster', async () => {
		const { self, calls } = await loaded([
			{ status: 200, body: { created: [{ id: 'a-1' }], refused: [] } },
			{ status: 200, body: { eventName: 'Winter Court', rows: [] } },
		])

		await self.save()

		expect(calls.map((call) => `${call.method} ${call.url}`)).toEqual([`POST ${AWARDS}`, `GET ${ROSTER}`])
		expect(calls[0].body).toEqual({ rows: [{ character: 'c-1', amount: 3, reason: 'Attended Winter Court', extra: false }] })
		expect(self.result).toEqual({ ok: true, created: 1, refused: [], error: '' })
		expect(self.busy).toBe(false)
	})

	it('keeps the roster when the save fails', async () => {
		const { self, calls } = await loaded([{ status: 500, body: { error: 'Database busy' } }])

		await self.save()

		expect(calls.map((call) => call.method)).toEqual(['POST'])
		expect(self.result).toEqual({ ok: false, created: 0, refused: [], error: 'Database busy' })
		expect(self.busy).toBe(false)
	})

	it('sends nothing without a positive amount, or while busy', async () => {
		const { self, calls } = await loaded([])
		self.defaultAmount = '0'
		await self.save()

		self.defaultAmount = '3'
		self.busy = true
		await self.save()

		expect(calls).toEqual([])
		expect(self.result).toBeNull()
	})

	it('explains each refusal code', () => {
		expect(XpAwardDialog.methods.refusalText('duplicate')).toBe('already has an award for this event')
		expect(XpAwardDialog.methods.refusalText('something-new')).toBe('something-new')
	})
})
