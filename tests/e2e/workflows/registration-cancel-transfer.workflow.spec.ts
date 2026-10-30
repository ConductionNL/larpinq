/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: cancelling a registration, a group booking, the money of a paid
 * cancellation and handing a registration over (registration-cancel-transfer-refund).
 *
 * The admin stands in for the player and the game master. Objects are posted by
 * schema slug. The steps run through larpinq's endpoints, as the Cancel or hand
 * over tab does.
 *
 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md
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
const API = `${BASE_URL}/index.php/apps/larpinq/api/registrations`
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
 * Read a registration.
 *
 * @param {string} id The uuid.
 * @return {Promise<any>} The registration.
 */
async function registration(id: string) {
	const res = await admin.get(`${OR_BASE}/larpinq/larping_registration/${id}`, {
		headers: JSON_HEADERS,
	})
	expect(res.ok(), await res.text()).toBe(true)
	return res.json()
}

/**
 * POST to a registration endpoint of larpinq.
 *
 * @param {string} id The registration uuid.
 * @param {string} path The path after the id.
 * @param {object} body The body.
 * @return {Promise<any>} The answer.
 */
async function change(id: string, path: string, body: Record<string, unknown> = {}) {
	const res = await admin.post(`${API}/${id}/${path}`, {
		headers: JSON_HEADERS,
		data: body,
	})
	const answer = await res.json().catch(() => ({}))
	expect(res.ok(), JSON.stringify(answer)).toBe(true)
	return answer
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	ids.world = await make('setting', { name: fixtureName('Aldmoor') })
	ids.me = await make('player', { name: fixtureName('Joris'), userUid: ME })
	ids.pieter = await make('player', { name: fixtureName('Pieter') })
	ids.sanne = await make('player', { name: fixtureName('Sanne') })
	ids.event = await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
		capacity: 2,
		approvalRequired: false,
		cancellationPolicy: {
			cancelBy: '2099-11-25T00:00:00+00:00',
			paidCancellation: 'player-chooses',
		},
	})
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	await admin.dispose()
})

test.describe.serial('cancel, book for others, money back and hand over', () => {
	// @e2e openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#mila-drops-out
	test('one participant of a group booking cancels alone', async () => {
		ids.joris = await make('larping_registration', {
			event: ids.event,
			player: ids.me,
			submitterUid: ME,
		})
		const mila = await change(ids.joris, 'participants', {
			name: fixtureName('Mila'),
		})
		ids.mila = ledger.track('larping_registration', String(mila.id))
		expect(mila.bookingGroup).toBeTruthy()

		await change(ids.mila, 'cancel')
		expect((await registration(ids.mila)).status).toBe('cancelled')
		expect((await registration(ids.joris)).status).toBe('accepted')
	})

	// @e2e openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#anna-cancels-in-time
	test('a cancelled place goes to the waiting list', async () => {
		ids.second = await make('larping_registration', {
			event: ids.event,
			player: ids.pieter,
			submitterUid: ME,
		})
		ids.waiting = await make('larping_registration', {
			event: ids.event,
			player: ids.sanne,
			submitterUid: ME,
		})
		expect((await registration(ids.waiting)).status).toBe('waitlisted')

		await change(ids.second, 'cancel')
		expect((await registration(ids.waiting)).status).toBe('accepted')
	})

	// @e2e openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#milas-fee-becomes-credit
	test('a paid cancellation records the credit requested', async () => {
		const res = await admin.patch(
			`${OR_BASE}/larpinq/larping_registration/${ids.joris}`,
			{
				headers: JSON_HEADERS,
				data: { paymentState: 'paid' },
			},
		)
		expect(res.ok(), await res.text()).toBe(true)

		await change(ids.joris, 'cancel', { settlement: 'credit' })
		const cancelled = await registration(ids.joris)
		expect(cancelled.settlementChoice).toBe('credit')
		expect(cancelled.settlement).toBe('credit-requested')
		expect(cancelled.settlementRequestedAt).toBeTruthy()
	})

	// @e2e openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#sanne-hands-her-place-to-pieter
	// The accept step needs a second account; TransferOffersTest covers it with the real listeners.
	test('a hand-over goes only to an account with a player', async () => {
		const allowed = await admin.get(`${API}/${ids.waiting}/changes`, {
			headers: JSON_HEADERS,
		})
		expect(allowed.ok()).toBe(true)
		expect((await allowed.json()).canCancel).toBe(true)

		const refused = await admin.post(`${API}/${ids.waiting}/transfer`, {
			headers: JSON_HEADERS,
			data: { account: 'nobody-' + Date.now() },
		})
		expect([403, 404]).toContain(refused.status())
	})
})
