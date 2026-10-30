/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: the events a player attended (players-attendance-history).
 *
 * Provisions players Anna and Karel with their own accounts, Anna's characters
 * "Mirela the Wanderer" and "Old Captain Harrow", Karel's "Oswin", two events
 * and check-ins through the event check-in endpoint (as the admin, a game
 * master). Then reads GET /api/players/{id}/attendance as Anna, as Karel and
 * as the admin, and opens Anna's Events attended tab as Anna.
 *
 * @spec openspec/specs/events-players/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
	RUN_ID,
} from './fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const users = {
	anna: { uid: `anna-${RUN_ID}`, pass: `Anna-${RUN_ID}-pw!9` },
	karel: { uid: `karel-${RUN_ID}`, pass: `Karel-${RUN_ID}-pw!9` },
}

let admin: APIRequestContext
const as: Record<string, APIRequestContext> = {}
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}

/**
 * Read a player's history as one account.
 *
 * @param {APIRequestContext} who The account.
 * @param {string} playerId The player UUID.
 * @return {Promise<{status: number, body: any}>} The answer.
 */
async function historyOf(who: APIRequestContext, playerId: string) {
	const res = await who.get(
		`${BASE_URL}/index.php${BASE}/api/players/${playerId}/attendance`,
		{ headers: OCS },
	)
	return { status: res.status(), body: await res.json() }
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, {
		headers: OCS,
		form: { groupid: 'larpers' },
	})
	for (const [key, user] of Object.entries(users)) {
		const created = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
			headers: OCS,
			form: { userid: user.uid, password: user.pass, 'groups[]': 'larpers' },
		})
		expect(created.ok(), await created.text()).toBe(true)
		as[key] = await request.newContext({
			httpCredentials: { username: user.uid, password: user.pass },
		})
		ids[key] = ledger.track(
			'player',
			await createObject(admin, 'player', {
				name: fixtureName(key),
				userUid: user.uid,
			}),
		)
	}

	ids.spring = ledger.track(
		'event',
		await createObject(admin, 'event', {
			name: fixtureName('Spring Moot 2025'),
			startDate: '2025-04-12T10:00:00+00:00',
		}),
	)
	ids.summer = ledger.track(
		'event',
		await createObject(admin, 'event', {
			name: fixtureName('Summer Siege 2025'),
			startDate: '2025-07-20T10:00:00+00:00',
		}),
	)
	for (const [key, name, player] of [
		['mirela', 'Mirela the Wanderer', 'anna'],
		['harrow', 'Old Captain Harrow', 'anna'],
		['oswin', 'Oswin', 'karel'],
	]) {
		ids[key] = ledger.track(
			'character',
			await createObject(admin, 'character', {
				name: fixtureName(name),
				ocName: ids[player],
				events: [ids.spring, ids.summer],
			}),
		)
	}
	for (const [event, character, status] of [
		['spring', 'harrow', 'checked-in'],
		['summer', 'mirela', 'checked-in'],
		['spring', 'mirela', 'no-show'],
		['summer', 'oswin', 'checked-in'],
	]) {
		const res = await admin.post(
			`${BASE_URL}/index.php${BASE}/api/events/${ids[event]}/attendance`,
			{ headers: OCS, data: { character: ids[character], status } },
		)
		expect(res.ok(), await res.text()).toBe(true)
	}
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	for (const [key, user] of Object.entries(users)) {
		await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${user.uid}`, {
			headers: OCS,
		})
		await as[key].dispose()
	}
	await admin.dispose()
})

test.describe('events a player attended', () => {
	// @e2e openspec/specs/events-players/spec.md#annas-two-seasons
	test("a game master sees Anna's two seasons, newest first", async () => {
		const { status, body } = await historyOf(admin, ids.anna)
		expect(status).toBe(200)
		expect(body.events.map((row) => row.event.id)).toEqual([
			ids.summer,
			ids.spring,
		])
		expect(body.events.map((row) => row.character.id)).toEqual([
			ids.mirela,
			ids.harrow,
		])
		expect(body.count).toBe(2)
	})

	// @e2e openspec/specs/events-players/spec.md#anna-sees-her-own-history
	test('Anna sees her own history, on the API and on her player page', async ({
		browser,
	}) => {
		const { status, body } = await historyOf(as.anna, ids.anna)
		expect(status).toBe(200)
		expect(body.count).toBe(2)

		const context = await browser.newContext({
			httpCredentials: { username: users.anna.uid, password: users.anna.pass },
		})
		const page = await context.newPage()
		await page.goto(`${BASE_URL}/index.php/apps/larpinq/players/${ids.anna}`)
		await page.getByRole('tab', { name: 'Events attended' }).click()
		await expect(page.getByTestId('player-attendance-count')).toContainText('2')
		await expect(page.getByTestId('player-attendance-row')).toHaveCount(2)
		await context.close()
	})

	// @e2e openspec/specs/events-players/spec.md#karel-looks-at-anna
	test("Karel is refused Anna's history and sees only his own", async () => {
		expect((await historyOf(as.karel, ids.anna)).status).toBe(403)
		const own = await historyOf(as.karel, ids.karel)
		expect(own.body.events.map((row) => row.character.id)).toEqual([ids.oswin])
	})
})
