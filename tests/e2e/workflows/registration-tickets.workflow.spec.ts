/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: ticket types, options and codes on a registration
 * (registration-ticket-types-and-options).
 *
 * The admin stands in for the game master who defines the ticket types and
 * for the player who chooses on a registration. Objects are posted by schema
 * slug. Prices are in cents. The offer and the counts come from larpinq's own
 * endpoints, because a player cannot read hidden ticket types or codes
 * through the object API.
 *
 * @spec openspec/specs/event-registration/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	cleanupLedger,
	FixtureLedger,
	fixtureName,
	newApi,
	OR_BASE,
	resolveSchemaIds,
} from './fixtures.ts'

const JSON_HEADERS = {
	Accept: 'application/json',
	'Content-Type': 'application/json',
}
const API = `${BASE_URL}/index.php/apps/larpinq/api`

let admin: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}

/**
 * Post an object by schema slug.
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<{status: number, body: any}>} The answer.
 */
async function post(slug: string, body: Record<string, unknown>) {
	const res = await admin.post(`${OR_BASE}/larpinq/${slug}`, {
		headers: JSON_HEADERS,
		data: body,
	})
	return { status: res.status(), body: await res.json().catch(() => ({})) }
}

/**
 * Change an object by schema slug.
 *
 * @param {string} slug The schema slug.
 * @param {string} id The uuid.
 * @param {object} body The fields.
 * @return {Promise<{status: number, body: any}>} The answer.
 */
async function patch(slug: string, id: string, body: Record<string, unknown>) {
	const res = await admin.patch(`${OR_BASE}/larpinq/${slug}/${id}`, {
		headers: JSON_HEADERS,
		data: body,
	})
	return { status: res.status(), body: await res.json().catch(() => ({})) }
}

/**
 * Read an object by schema slug.
 *
 * @param {string} slug The schema slug.
 * @param {string} id The uuid.
 * @return {Promise<any>} The object.
 */
async function read(slug: string, id: string) {
	const res = await admin.get(`${OR_BASE}/larpinq/${slug}/${id}`, {
		headers: JSON_HEADERS,
	})
	expect(res.ok(), await res.text()).toBe(true)
	return res.json()
}

/**
 * Create an object and track it for clean-up.
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<string>} The uuid.
 */
async function make(slug: string, body: Record<string, unknown>) {
	const created = await post(slug, body)
	expect(created.status, JSON.stringify(created.body)).toBeLessThan(300)
	return ledger.track(slug, String(created.body.id ?? created.body['@self']?.id))
}

/**
 * The ticket types and options larpinq offers on a registration.
 *
 * @param {string} registration The registration uuid.
 * @param {string} code An entered code, or empty.
 * @return {Promise<any>} The offer.
 */
async function offer(registration: string, code = '') {
	const res = await admin.get(
		`${API}/registrations/${registration}/offer?code=${encodeURIComponent(code)}`,
		{ headers: JSON_HEADERS },
	)
	expect(res.ok(), await res.text()).toBe(true)
	return res.json()
}

/**
 * The names in an offer's ticket types.
 *
 * @param {any} body The offer.
 * @return {string[]} The names.
 */
function ticketNames(body: { ticketTypes: Array<{ name: string }> }) {
	return body.ticketTypes.map((ticket) => ticket.name)
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	ids.world = await make('setting', { name: fixtureName('Aldmoor') })
	ids.anna = await make('player', {
		name: fixtureName('Anna de Vries'),
		userUid: process.env.NC_USER || 'admin',
	})
	ids.joris = await make('player', { name: fixtureName('Joris') })
	ids.event = await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
		approvalRequired: false,
	})
	const ticket = (name: string, extra: Record<string, unknown>) =>
		make('larping_ticket_type', {
			event: ids.event,
			name: fixtureName(name),
			currency: 'EUR',
			...extra,
		})
	ids.player = await ticket('Player', { role: 'player', amount: 11000, order: 2 })
	ids.earlyBird = await ticket('Player early bird', {
		role: 'player',
		amount: 9500,
		saleUntil: '2026-01-01T00:00:00+00:00',
		order: 1,
	})
	ids.crew = await ticket('Crew', {
		role: 'crew',
		amount: 4500,
		placeLimit: 1,
		order: 3,
	})
	ids.crewFriends = await ticket('Crew friends', {
		role: 'crew',
		amount: 3000,
		hidden: true,
		order: 4,
	})
	ids.vegan = await make('larping_registration_option', {
		event: ids.event,
		name: fixtureName('Full catering, vegan'),
		category: 'meal',
		amount: 6000,
		currency: 'EUR',
	})
	ids.meat = await make('larping_registration_option', {
		event: ids.event,
		name: fixtureName('Full catering, meat'),
		category: 'meal',
		amount: 6000,
		currency: 'EUR',
	})
	ids.lantern = await make('larping_access_code', {
		event: ids.event,
		code: 'LANTERN',
		unlocks: [ids.crewFriends],
		maxUses: 5,
	})
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	await admin.dispose()
})

test.describe.serial('tickets, options and codes on a registration', () => {
	// @e2e openspec/specs/event-registration/spec.md#anna-picks-a-player-ticket
	test('a chosen ticket type and option are stored with their listed prices', async () => {
		ids.annas = await make('larping_registration', {
			event: ids.event,
			player: ids.anna,
			ticketType: ids.player,
			options: [ids.vegan],
		})

		const stored = await read('larping_registration', ids.annas)
		expect(stored.ticketType).toBe(ids.player)
		const amounts = (stored.lines ?? []).map(
			(line: { kind: string; amount: number }) => [line.kind, line.amount],
		)
		expect(amounts).toEqual(
			expect.arrayContaining([
				['ticket', 11000],
				['option', 6000],
			]),
		)
	})

	// @e2e openspec/specs/event-registration/spec.md#too-late-for-the-early-bird
	test('after its sale window the early bird is neither offered nor accepted', async () => {
		const names = ticketNames(await offer(ids.annas))
		expect(names).toContain(fixtureName('Player'))
		expect(names).not.toContain(fixtureName('Player early bird'))

		const late = await patch('larping_registration', ids.annas, {
			ticketType: ids.earlyBird,
		})
		expect(late.status).toBeGreaterThanOrEqual(400)
	})

	// @e2e openspec/specs/event-registration/spec.md#a-crew-friend-uses-the-code
	test('the code LANTERN unlocks the hidden crew friends ticket', async () => {
		ids.sannes = await make('larping_registration', {
			event: ids.event,
			player: ids.joris,
		})
		expect(ticketNames(await offer(ids.sannes))).not.toContain(
			fixtureName('Crew friends'),
		)

		const unlocked = await offer(ids.sannes, 'lantern')
		expect(unlocked.code).toBe('valid')
		expect(ticketNames(unlocked)).toContain(fixtureName('Crew friends'))

		const chosen = await patch('larping_registration', ids.sannes, {
			ticketType: ids.crewFriends,
			code: 'LANTERN',
		})
		expect(chosen.status, JSON.stringify(chosen.body)).toBeLessThan(300)
		expect((await read('larping_registration', ids.sannes)).accessCode).toBe(
			ids.lantern,
		)
	})

	// @e2e openspec/specs/event-registration/spec.md#a-client-tries-its-own-price
	test('a price line sent by a client is ignored', async () => {
		await patch('larping_registration', ids.annas, {
			lines: [
				{
					kind: 'ticket',
					ref: ids.player,
					name: 'Player',
					amount: 100,
					currency: 'EUR',
				},
			],
		})

		const lines = (await read('larping_registration', ids.annas)).lines
		const ticketLine = lines.find(
			(line: { kind: string }) => line.kind === 'ticket',
		)
		expect(ticketLine.amount).toBe(11000)
	})

	// @e2e openspec/specs/event-registration/spec.md#crew-is-full
	test('a registration beyond the crew place limit is waitlisted', async () => {
		ids.firstCrew = await make('larping_registration', {
			event: ids.event,
			player: ids.joris,
			ticketType: ids.crew,
		})
		expect((await read('larping_registration', ids.firstCrew)).status).toBe(
			'accepted',
		)

		ids.secondCrew = await make('larping_registration', {
			event: ids.event,
			player: ids.anna,
			ticketType: ids.crew,
		})
		expect((await read('larping_registration', ids.secondCrew)).status).toBe(
			'waitlisted',
		)
	})

	// @e2e openspec/specs/event-registration/spec.md#the-kitchen-plans
	test('the event choices count accepted registrations and show no money total', async () => {
		const res = await admin.get(`${API}/events/${ids.event}/choices`, {
			headers: JSON_HEADERS,
		})
		expect(res.ok(), await res.text()).toBe(true)
		const body = await res.json()

		const vegan = body.options.find(
			(row: { id: string }) => row.id === ids.vegan,
		)
		const meat = body.options.find((row: { id: string }) => row.id === ids.meat)
		expect(vegan.count).toBe(1)
		expect(meat.count).toBe(0)
		expect(JSON.stringify(body)).not.toMatch(/amount|total/)
	})
})
