/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/eventCasting.js (events-casting-and-crew-roles):
 * the proposal and its confirmation, the crew places and volunteers, the
 * casting deadline, and turning a player's ranking into preference writes.
 * Only fetch is faked.
 *
 * @spec openspec/specs/event-casting/spec.md
 */

import { afterEach, describe, expect, it } from 'vitest'
import {
	confirmCasting,
	crewPlaces,
	fetchProposal,
	fetchRegistrationCasting,
	rankingChanges,
	rankingClosed,
	rankingIsValid,
	registrationLabel,
	saveRanking,
	volunteersFor,
} from '../../src/services/eventCasting.js'

const OBJECTS = '/index.php/apps/openregister/api/objects/larpinq'

/**
 * Fake fetch answering by URL prefix, in turn per prefix.
 *
 * @param {Array<[string, number, object]>} answers `[url start, status, body]`, in order.
 * @return {Array<{url: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		calls.push({
			url,
			method: options.method ?? 'GET',
			body: options.body ? JSON.parse(options.body) : undefined,
		})
		const index = answers.findIndex(([start]) => url.startsWith(start))
		const [, status, body] =
			index === -1 ? ['', 404, {}] : answers.splice(index, 1)[0]
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

afterEach(() => {
	delete globalThis.fetch
})

describe('fetchProposal', () => {
	it('asks larpinq for the proposal of the event', async () => {
		const calls = fakeFetch([
			[
				'/index.php/apps/larpinq/api/events/ev-1/casting/proposal',
				200,
				{ pairs: [], unmatched: ['r-1'], characters: [] },
			],
		])

		expect(await fetchProposal('ev-1')).toEqual({
			state: 'ok',
			pairs: [],
			unmatched: ['r-1'],
			characters: [],
		})
		expect(calls).toHaveLength(1)
	})

	it('says refused to a player, failed on an error, and asks nothing without an event', async () => {
		fakeFetch([
			['/index.php/apps/larpinq', 403, {}],
			['/index.php/apps/larpinq', 422, {}],
		])

		expect(await fetchProposal('ev-1')).toEqual({ state: 'refused' })
		expect(await fetchProposal('ev-1')).toEqual({ state: 'failed' })
		expect(await fetchProposal('')).toEqual({ state: 'failed' })
	})
})

describe('confirmCasting', () => {
	it('posts the kept pairs only and reports the refusals', async () => {
		const calls = fakeFetch([
			[
				'/index.php/apps/larpinq/api/events/ev-1/casting/confirm',
				200,
				{
					written: [{ registration: 'r-anna', character: 'heir' }],
					refused: [
						{
							registration: 'r-pieter',
							character: 'bard',
							reason: 'character-not-open',
						},
					],
				},
			],
		])

		const result = await confirmCasting('ev-1', [
			{ registration: 'r-anna', character: 'heir' },
			{ registration: 'r-pieter', character: 'bard' },
			{ registration: 'r-sanne', character: '' },
		])

		expect(calls[0].method).toBe('POST')
		expect(calls[0].body).toEqual({
			pairs: [
				{ registration: 'r-anna', character: 'heir' },
				{ registration: 'r-pieter', character: 'bard' },
			],
		})
		expect(result.ok).toBe(false)
		expect(result.written).toEqual([
			{ registration: 'r-anna', character: 'heir' },
		])
		expect(result.refused[0].reason).toBe('character-not-open')
	})

	it('writes nothing it can report when larpinq refuses', async () => {
		fakeFetch([['/index.php/apps/larpinq', 403, {}]])

		expect(
			await confirmCasting('ev-1', [{ registration: 'r', character: 'c' }]),
		).toEqual({ ok: false, written: [], refused: [] })
	})
})

describe('crew', () => {
	const roles = [
		{ id: 'bandits', name: 'Bandits', kind: 'npc', places: 6 },
		{ id: 'kitchen', name: 'Kitchen', kind: 'crew', places: 3 },
	]

	it('counts filled and open places per role', () => {
		const places = crewPlaces(roles, [
			{ crewRole: 'kitchen', registration: 'r-joris' },
		])

		expect(places.map(({ name, filled, open }) => [name, filled, open])).toEqual(
			[
				['Bandits', 0, 6],
				['Kitchen', 1, 2],
			],
		)
		expect(places[1].assignments).toEqual([
			{ crewRole: 'kitchen', registration: 'r-joris' },
		])
	})

	it('offers accepted registrations with a crew or NPC ticket that are not yet in the role', () => {
		const tickets = [
			{ id: 't-player', role: 'player' },
			{ id: 't-crew', role: 'crew' },
			{ id: 't-npc', role: 'npc' },
		]
		const registrations = [
			{ id: 'r-joris', status: 'accepted', ticketType: 't-crew' },
			{ id: 'r-lotte', status: 'accepted', ticketType: 't-npc' },
			{ id: 'r-anna', status: 'accepted', ticketType: 't-player' },
			{ id: 'r-karel', status: 'declined', ticketType: 't-crew' },
		]

		expect(
			volunteersFor(registrations, tickets, [{ registration: 'r-joris' }]).map(
				(r) => r.id,
			),
		).toEqual(['r-lotte'])
	})

	it('names a registration by its player account, then its submitter, then its id', () => {
		expect(
			registrationLabel({ id: 'r', playerUid: 'anna', submitterUid: 'ruben' }),
		).toBe('anna')
		expect(registrationLabel({ id: 'r', submitterUid: 'ruben' })).toBe('ruben')
		expect(registrationLabel({ '@self': { id: 'r-9' } })).toBe('r-9')
	})
})

describe('ranking', () => {
	it('closes from the casting deadline on, and stays open without one', () => {
		const event = { castingDeadline: '2026-11-01T00:00:00+00:00' }

		expect(rankingClosed(event, new Date('2026-10-31T23:59:00Z'))).toBe(false)
		expect(rankingClosed(event, new Date('2026-11-01T00:00:00Z'))).toBe(true)
		expect(rankingClosed({}, new Date('2030-01-01'))).toBe(false)
	})

	it('accepts each rank once, 1 to 5', () => {
		expect(rankingIsValid({ heir: 1, spy: 2, bard: 0 })).toBe(true)
		expect(rankingIsValid({ heir: 1, spy: 1 })).toBe(false)
		expect(rankingIsValid({ heir: 6 })).toBe(false)
	})

	it('turns the wanted ranking into creates, updates and removals', () => {
		const stored = [
			{ id: 'p-heir', character: 'heir', rank: 1 },
			{ id: 'p-spy', character: 'spy', rank: 2 },
			{ id: 'p-bard', character: 'bard', rank: 3 },
		]

		expect(
			rankingChanges(stored, { heir: 1, spy: 3, bard: 0, chancellor: 2 }),
		).toEqual({
			create: [{ character: 'chancellor', rank: 2 }],
			update: [{ id: 'p-spy', rank: 3 }],
			remove: ['p-bard'],
		})
	})

	it('saves removals first, then updates, then creates, and stops at a refusal', async () => {
		const calls = fakeFetch([
			[`${OBJECTS}/larping_casting_preference/p-bard`, 200, {}],
			[`${OBJECTS}/larping_casting_preference/p-spy`, 200, {}],
			[
				`${OBJECTS}/larping_casting_preference`,
				400,
				{ message: 'The casting deadline of this event has passed.' },
			],
		])
		const registration = { id: 'r-anna', event: 'ev-1' }
		const stored = [
			{ id: 'p-spy', character: 'spy', rank: 2 },
			{ id: 'p-bard', character: 'bard', rank: 3 },
		]

		const result = await saveRanking(registration, stored, {
			spy: 1,
			bard: 0,
			heir: 2,
			chancellor: 3,
		})

		expect(calls.map((call) => [call.method, call.url])).toEqual([
			['DELETE', `${OBJECTS}/larping_casting_preference/p-bard`],
			['PATCH', `${OBJECTS}/larping_casting_preference/p-spy`],
			['POST', `${OBJECTS}/larping_casting_preference`],
		])
		expect(calls[1].body).toEqual({ rank: 1 })
		expect(calls[2].body).toEqual({
			event: 'ev-1',
			registration: 'r-anna',
			character: 'heir',
			rank: 2,
		})
		expect(result).toEqual({
			ok: false,
			message: 'The casting deadline of this event has passed.',
		})
	})

	it('reads a registration, its event, the open characters, the ranking and the crew roles', async () => {
		const calls = fakeFetch([
			[
				`${OBJECTS}/larping_registration/r-joris`,
				200,
				{ id: 'r-joris', event: 'ev-1' },
			],
			[
				`${OBJECTS}/larping_event/ev-1`,
				200,
				{ id: 'ev-1', castingDeadline: '2026-11-01T00:00:00+00:00' },
			],
			[
				`${OBJECTS}/character?`,
				200,
				{ results: [{ id: 'heir', name: 'The Heir' }] },
			],
			[`${OBJECTS}/larping_casting_preference?`, 200, { results: [] }],
			[
				`${OBJECTS}/larping_crew_assignment?`,
				200,
				{ results: [{ crewRole: 'kitchen', registration: 'r-joris' }] },
			],
			[
				`${OBJECTS}/larping_crew_role?`,
				200,
				{
					results: [
						{ id: 'bandits', name: 'Bandits' },
						{ id: 'kitchen', name: 'Kitchen' },
					],
				},
			],
		])

		const view = await fetchRegistrationCasting('r-joris')

		expect(view.state).toBe('ok')
		expect(view.characters.map((c) => c.name)).toEqual(['The Heir'])
		expect(view.crew.map((role) => role.name)).toEqual(['Kitchen'])
		expect(
			calls.find((call) => call.url.startsWith(`${OBJECTS}/character?`)).url,
		).toContain('castingEvent=ev-1')
		expect(
			calls.find((call) =>
				call.url.startsWith(`${OBJECTS}/larping_casting_preference?`),
			).url,
		).toContain('registration=r-joris')
	})

	it('fails when the registration cannot be read', async () => {
		fakeFetch([[`${OBJECTS}/larping_registration/r-x`, 404, {}]])

		expect(await fetchRegistrationCasting('r-x')).toEqual({ state: 'failed' })
		expect(await fetchRegistrationCasting('')).toEqual({ state: 'failed' })
	})
})
