/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/EventCasting.vue and src/views/RegistrationCasting.vue
 * (events-casting-and-crew-roles): the proposal table a game master edits and
 * confirms, the crew places, and a player's ranking. Only fetch is faked; the
 * real eventCasting service runs.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import EventCasting from '../../src/views/EventCasting.vue'
import RegistrationCasting from '../../src/views/RegistrationCasting.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars) =>
		key.replace(/\{(\w+)\}/g, (_, name) => String(vars?.[name] ?? `{${name}}`)),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))
vi.mock('@conduction/nextcloud-vue', () => ({ CnDataTable: {} }))

const OBJECTS = '/index.php/apps/openregister/api/objects/larpinq'
const API = '/index.php/apps/larpinq/api/events/ev-winter/casting'

/** The proposal design.md names for the Winter Court seed. */
const PROPOSAL = {
	pairs: [
		{
			registration: 'r-anna',
			character: 'heir',
			characterName: 'The Heir',
			rank: 1,
		},
		{
			registration: 'r-pieter',
			character: 'bard',
			characterName: 'The Bard',
			rank: 2,
		},
		{
			registration: 'r-sanne',
			character: 'spy',
			characterName: 'The Spy',
			rank: 1,
		},
	],
	unmatched: ['r-lotte'],
	characters: [
		{
			id: 'chancellor',
			name: 'The Chancellor',
			ranked: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 },
		},
		{ id: 'spy', name: 'The Spy', ranked: { 1: 1, 2: 1, 3: 0, 4: 0, 5: 0 } },
		{ id: 'heir', name: 'The Heir', ranked: { 1: 2, 2: 0, 3: 0, 4: 0, 5: 0 } },
		{ id: 'bard', name: 'The Bard', ranked: { 1: 0, 2: 1, 3: 0, 4: 0, 5: 0 } },
	],
}

const REGISTRATIONS = [
	{ id: 'r-anna', playerUid: 'anna', status: 'accepted', ticketType: 't-player' },
	{
		id: 'r-pieter',
		playerUid: 'pieter',
		status: 'accepted',
		ticketType: 't-player',
	},
	{
		id: 'r-sanne',
		playerUid: 'sanne',
		status: 'accepted',
		ticketType: 't-player',
	},
	{
		id: 'r-lotte',
		playerUid: 'lotte',
		status: 'accepted',
		ticketType: 't-player',
	},
	{ id: 'r-joris', playerUid: 'joris', status: 'accepted', ticketType: 't-crew' },
]

/**
 * Fake fetch answering by URL prefix; answers are reused.
 *
 * @param {Array<[string, number, object]>} answers `[url start, status, body]`.
 * @return {Array<{url: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		const method = options.method ?? 'GET'
		calls.push({
			url,
			method,
			body: options.body ? JSON.parse(options.body) : undefined,
		})
		const found = answers.find(
			([start, , , verb]) =>
				url.startsWith(start) && (verb ?? 'GET') === method,
		)
		const [, status, body] = found ?? ['', 404, {}]
		return {
			ok: status < 400,
			status,
			json: async () => body,
			text: async () => JSON.stringify(body),
			headers: { get: () => 'application/json' },
		}
	}
	return calls
}

/**
 * A component's state with its computed properties and methods bound.
 *
 * @param {object} component The component options.
 * @param {object} extra Extra instance fields.
 * @return {object} The instance.
 */
function instance(component, extra) {
	const self = { ...component.data(), ...extra }
	for (const [name, method] of Object.entries(component.methods)) {
		self[name] = method.bind(self)
	}
	for (const [name, getter] of Object.entries(component.computed)) {
		Object.defineProperty(self, name, {
			get: getter.bind(self),
			configurable: true,
		})
	}
	return self
}

/**
 * The game master's casting tab over the Winter Court data.
 *
 * @param {Array} more Extra fake answers, checked first.
 * @return {Promise<{self: object, calls: Array}>} The loaded tab and the calls.
 */
async function eventTab(more = []) {
	const calls = fakeFetch([
		...more,
		[`${API}/proposal`, 200, PROPOSAL],
		[`${OBJECTS}/larping_registration?`, 200, { results: REGISTRATIONS }],
		[
			`${OBJECTS}/larping_ticket_type?`,
			200,
			{
				results: [
					{ id: 't-player', role: 'player' },
					{ id: 't-crew', role: 'crew' },
				],
			},
		],
		[
			`${OBJECTS}/larping_crew_role?`,
			200,
			{
				results: [
					{ id: 'kitchen', name: 'Kitchen', kind: 'crew', places: 3 },
				],
			},
		],
		[`${OBJECTS}/larping_crew_assignment?`, 200, { results: [] }],
	])
	const self = instance(EventCasting, { eventId: 'ev-winter' })
	await EventCasting.mounted.call(self)
	return { self, calls }
}

afterEach(() => {
	delete globalThis.fetch
})

describe('EventCasting proposal table', () => {
	it('lists each proposed pair by player, then the players without a match', async () => {
		const { self } = await eventTab()

		expect(self.loading).toBe(false)
		expect(self.proposalRows).toEqual([
			{ id: 'r-anna', player: 'anna', rank: 1 },
			{ id: 'r-pieter', player: 'pieter', rank: 2 },
			{ id: 'r-sanne', player: 'sanne', rank: 1 },
			{ id: 'r-lotte', player: 'lotte', rank: '' },
		])
		expect(self.cast['r-anna'].label).toBe('The Heir')
		expect(self.cast['r-lotte']).toBeUndefined()
		expect(self.unmatchedNames).toBe('lotte')
	})

	it('counts how often each open character was ranked at each rank', async () => {
		const { self } = await eventTab()

		expect(self.rankColumns.map((column) => column.label)).toEqual([
			'Character',
			'Rank 1',
			'Rank 2',
			'Rank 3',
			'Rank 4',
			'Rank 5',
		])
		expect(self.rankRows.find((row) => row.id === 'heir')).toMatchObject({
			name: 'The Heir',
			rank1: 2,
			rank2: 0,
		})
		expect(self.characterOptions.map((option) => option.label)).toEqual([
			'Not cast',
			'The Chancellor',
			'The Spy',
			'The Heir',
			'The Bard',
		])
	})

	it('gives a character to one player only when the game master changes the table', async () => {
		const { self } = await eventTab()
		const heir = self.characterOptions.find((option) => option.id === 'heir')

		self.pick('r-lotte', heir)

		expect(self.cast['r-lotte'].id).toBe('heir')
		expect(self.cast['r-anna'].id).toBe('')
	})

	it('confirms the table as it stands and reloads after a write', async () => {
		const { self, calls } = await eventTab([
			[
				`${API}/confirm`,
				200,
				{
					written: [{ registration: 'r-anna', character: 'heir' }],
					refused: [],
				},
				'POST',
			],
		])

		await self.confirm()

		const confirm = calls.find((call) => call.method === 'POST')
		expect(confirm.url).toBe(`${API}/confirm`)
		expect(confirm.body.pairs).toEqual([
			{ registration: 'r-anna', character: 'heir' },
			{ registration: 'r-pieter', character: 'bard' },
			{ registration: 'r-sanne', character: 'spy' },
		])
		expect(self.confirmed).toBe(true)
		expect(calls.filter((call) => call.url === `${API}/proposal`)).toHaveLength(
			2,
		)
	})

	it('names the refused pairs', async () => {
		const { self } = await eventTab([
			[
				`${API}/confirm`,
				200,
				{
					written: [],
					refused: [
						{
							registration: 'r-pieter',
							character: 'bard',
							reason: 'character-not-open',
						},
					],
				},
				'POST',
			],
		])

		await self.confirm()

		expect(self.confirmed).toBe(false)
		expect(self.refusedNames).toBe('pieter')
	})

	it('says so to a player instead of showing the proposal', async () => {
		fakeFetch([[`${API}/proposal`, 403, {}]])
		const self = instance(EventCasting, { eventId: 'ev-winter' })

		await EventCasting.mounted.call(self)

		expect(self.view).toEqual({ state: 'refused' })
	})
})

describe('EventCasting crew', () => {
	it('shows the kitchen with its places and assigns Joris, who has a crew ticket', async () => {
		const { self, calls } = await eventTab([
			[`${OBJECTS}/larping_crew_assignment`, 201, { id: 'a-1' }, 'POST'],
		])
		const kitchen = self.crew[0]

		expect([kitchen.filled, kitchen.places, kitchen.open]).toEqual([0, 3, 3])
		expect(self.volunteerOptions(kitchen)).toEqual([
			{ id: 'r-joris', label: 'joris' },
		])

		self.crewChoice = { kitchen: { id: 'r-joris', label: 'joris' } }
		await self.assign(kitchen)

		const write = calls.find((call) => call.method === 'POST')
		expect(write.url).toBe(`${OBJECTS}/larping_crew_assignment`)
		expect(write.body).toEqual({ crewRole: 'kitchen', registration: 'r-joris' })
		expect(self.crewMessage).toBe('')
		expect(self.kindLabel('npc')).toBe('npc')
		expect(self.kindLabel('crew')).toBe('crew')
	})
})

describe('RegistrationCasting', () => {
	/**
	 * Anna's casting tab.
	 *
	 * @param {string} deadline The event's casting deadline.
	 * @param {Array} more Extra fake answers, checked first.
	 * @return {Promise<{self: object, calls: Array}>} The tab and the calls.
	 */
	async function annaTab(deadline, more = []) {
		const calls = fakeFetch([
			...more,
			[
				`${OBJECTS}/larping_registration/r-anna`,
				200,
				{ id: 'r-anna', event: 'ev-winter' },
			],
			[
				`${OBJECTS}/larping_event/ev-winter`,
				200,
				{ id: 'ev-winter', castingDeadline: deadline },
			],
			[
				`${OBJECTS}/character?`,
				200,
				{
					results: [
						{ id: 'heir', name: 'The Heir' },
						{ id: 'spy', name: 'The Spy' },
					],
				},
			],
			[
				`${OBJECTS}/larping_casting_preference?`,
				200,
				{ results: [{ id: 'p-heir', character: 'heir', rank: 1 }] },
			],
			[`${OBJECTS}/larping_crew_assignment?`, 200, { results: [] }],
			[`${OBJECTS}/larping_crew_role?`, 200, { results: [] }],
		])
		const self = instance(RegistrationCasting, { registrationId: 'r-anna' })
		await RegistrationCasting.mounted.call(self)
		return { self, calls }
	}

	it('starts from the stored ranking, every open character listed', async () => {
		const { self } = await annaTab('2099-01-01T00:00:00+00:00')

		expect(self.ranks).toEqual({ heir: 1, spy: 0 })
		expect(self.closed).toBe(false)
		expect(self.optionOf(1).label).toBe('Rank 1')
	})

	it('saves The Spy second as a new preference', async () => {
		const { self, calls } = await annaTab('2099-01-01T00:00:00+00:00', [
			[`${OBJECTS}/larping_casting_preference`, 201, { id: 'p-spy' }, 'POST'],
		])

		self.rankChanged('spy', { id: 2 })
		await self.save()

		const write = calls.find((call) => call.method === 'POST')
		expect(write.body).toEqual({
			event: 'ev-winter',
			registration: 'r-anna',
			character: 'spy',
			rank: 2,
		})
		expect(self.saved).toBe(true)
		expect(self.message).toBe('')
	})

	it('refuses to save one rank twice, and saves nothing after the deadline', async () => {
		const open = await annaTab('2099-01-01T00:00:00+00:00')
		open.self.rankChanged('spy', { id: 1 })
		expect(open.self.valid).toBe(false)
		await open.self.save()
		expect(open.calls.filter((call) => call.method !== 'GET')).toEqual([])

		const late = await annaTab('2026-11-01T00:00:00+00:00')
		expect(late.self.closed).toBe(true)
		late.self.rankChanged('spy', { id: 2 })
		await late.self.save()
		expect(late.calls.filter((call) => call.method !== 'GET')).toEqual([])
	})
})
