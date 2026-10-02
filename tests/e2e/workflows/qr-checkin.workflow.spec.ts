/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: an accepted registration gets a check-in code, and a game master
 * checks the participant in with it once (events-qr-checkin).
 *
 * The admin stands in for the player and the game master. Objects are posted by
 * schema slug. The check-in goes through larpinq's endpoint, as the scan panel
 * of the Check-in tab does.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
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
const ME = process.env.NC_USER || 'admin'

let admin: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}

/**
 * Post an object by schema slug and track it for clean-up.
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<string>} The uuid.
 */
async function make(slug: string, body: Record<string, unknown>) {
	const res = await admin.post(`${OR_BASE}/larpinq/${slug}`, {
		headers: JSON_HEADERS,
		data: body,
	})
	const created = await res.json().catch(() => ({}))
	expect(res.status(), JSON.stringify(created)).toBeLessThan(300)
	return ledger.track(slug, String(created.id ?? created['@self']?.id))
}

/**
 * Check in by code at the event.
 *
 * @param {string} code The code.
 * @return {Promise<{status: number, body: any}>} The answer.
 */
async function checkIn(code: string) {
	const res = await admin.post(
		`${BASE_URL}/index.php/apps/larpinq/api/events/${ids.event}/checkin-code`,
		{
			headers: JSON_HEADERS,
			data: { code },
		},
	)
	return { status: res.status(), body: await res.json().catch(() => ({})) }
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	ids.world = await make('setting', { name: fixtureName('Aldmoor') })
	ids.anna = await make('player', {
		name: fixtureName('Anna de Vries'),
		userUid: ME,
	})
	ids.mirela = await make('character', {
		name: fixtureName('Mirela the Wanderer'),
		setting: ids.world,
		ownerUid: ME,
	})
	ids.event = await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
		approvalRequired: true,
	})
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	await admin.dispose()
})

test.describe.serial('check in by QR code', () => {
	// @e2e openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#annas-registration-is-accepted
	test('an accepted registration has a check-in code', async () => {
		ids.registration = await make('larping_registration', {
			event: ids.event,
			player: ids.anna,
			character: ids.mirela,
			submitterUid: ME,
		})
		const url = `${OR_BASE}/larpinq/larping_registration/${ids.registration}`
		expect(
			(await (await admin.get(url, { headers: JSON_HEADERS })).json())
				.checkinCode ?? '',
		).toBe('')

		const res = await admin.patch(url, {
			headers: JSON_HEADERS,
			data: { status: 'accepted' },
		})
		expect(res.ok(), await res.text()).toBe(true)
		ids.code = (
			await (await admin.get(url, { headers: JSON_HEADERS })).json()
		).checkinCode
		expect(ids.code).toMatch(/^[A-Z2-7]{26}$/)
	})

	// @e2e openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#anna-arrives-at-the-gate
	test('her code checks Mirela in', async () => {
		const answer = await checkIn(ids.code.toLowerCase())
		expect(answer.status).toBe(200)
		expect(answer.body.status).toBe('checked-in')
		expect(answer.body.character).toContain('Mirela the Wanderer')
	})

	// @e2e openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#the-same-code-twice
	test('the same code twice says when and by whom', async () => {
		const answer = await checkIn(ids.code)
		expect(answer.status).toBe(200)
		expect(answer.body.status).toBe('already')
		expect(answer.body.by).toBe(ME)
		expect(answer.body.at).toBeTruthy()
		expect((await checkIn('ZZZZZZZZZZZZZZZZZZZZZZZZZZ')).status).toBe(404)
	})
})
