/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: an accepted registration's payment through shillinq
 * (registration-payments-through-shillinq).
 *
 * The admin stands in for the game master. Objects are posted by schema slug.
 * With shillinq installed, accepting raises the payment request at once; without
 * it the payment waits as to-request and "Request payment" says why it cannot.
 * Both branches are asserted, so the spec runs on either instance.
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
 * Change a registration.
 *
 * @param {string} id The uuid.
 * @param {object} body The fields.
 * @return {Promise<void>}
 */
async function patchRegistration(id: string, body: Record<string, unknown>) {
	const res = await admin.patch(`${OR_BASE}/larpinq/larping_registration/${id}`, {
		headers: JSON_HEADERS,
		data: body,
	})
	expect(res.ok(), await res.text()).toBe(true)
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

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	ids.world = await make('setting', { name: fixtureName('Aldmoor') })
	ids.anna = await make('player', {
		name: fixtureName('Anna de Vries'),
		userUid: process.env.NC_USER || 'admin',
	})
	ids.event = await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
		approvalRequired: true,
		paymentRequired: true,
		payBy: '2026-11-20T00:00:00+00:00',
		paymentCode: 'WC26',
	})
	ids.sanne = await make('player', { name: fixtureName('Sanne') })
	ids.pieter = await make('player', { name: fixtureName('Pieter') })
	ids.fullEvent = await make('larping_event', {
		name: fixtureName('Summer Moot 2026'),
		setting: ids.world,
		capacity: 1,
		approvalRequired: false,
		paymentRequired: true,
		paymentCode: 'SM26',
	})
	ids.ticket = await make('larping_ticket_type', {
		event: ids.event,
		name: fixtureName('Player'),
		role: 'player',
		amount: 8500,
		currency: 'EUR',
	})
	ids.meals = await make('larping_registration_option', {
		event: ids.event,
		name: fixtureName('Full catering'),
		category: 'meal',
		amount: 3500,
		currency: 'EUR',
	})
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	await admin.dispose()
})

test.describe.serial('payment of an accepted registration', () => {
	// @e2e openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#a-club-pays-for-its-member
	test('a player asks for an invoice before the registration is accepted', async () => {
		ids.annas = await make('larping_registration', {
			event: ids.event,
			player: ids.anna,
			ticketType: ids.ticket,
			options: [ids.meals],
		})
		await patchRegistration(ids.annas, { invoiceRequested: true })

		const row = await registration(ids.annas)
		expect(row.status).toBe('pending')
		expect(row.invoiceRequested).toBe(true)
		expect(row.paymentState ?? null).toBeNull()
	})

	// @e2e openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#anna-gets-her-payment-link
	test('accepting asks shillinq for the payment, or leaves it to request', async () => {
		await patchRegistration(ids.annas, { status: 'accepted' })

		const row = await registration(ids.annas)
		expect(row.status).toBe('accepted')
		expect(row.payBy).toBe('2026-11-20T00:00:00+00:00')
		if (row.paymentState === 'open') {
			expect(row.paymentReference).toBe('WC26-0001')
			expect(row.paymentRequestId).toBeTruthy()
			return
		}
		expect(row.paymentState).toBe('to-request')

		const res = await admin.post(
			`${API}/registrations/${ids.annas}/payment-request`,
			{
				headers: JSON_HEADERS,
			},
		)
		const answer = await res.json()
		if (res.status() === 409) {
			expect(String(answer.error).toLowerCase()).toContain('shillinq')
			return
		}
		expect(res.status(), JSON.stringify(answer)).toBe(200)
		expect(answer.paymentState).toBe('open')
		expect(answer.paymentReference).toBe('WC26-0001')
	})

	// @e2e openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#who-still-owes
	test('the registrations of the event filter on their payment state', async () => {
		const state = (await registration(ids.annas)).paymentState
		const res = await admin.get(
			`${OR_BASE}/larpinq/larping_registration?event=${ids.event}&paymentState=${state}`,
			{ headers: JSON_HEADERS },
		)
		expect(res.ok(), await res.text()).toBe(true)
		const body = await res.json()
		const rows = body.results ?? body
		expect(rows.map((one: { id: string }) => one.id)).toContain(ids.annas)
	})

	// @e2e openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#a-bank-transfer-is-matched
	test('a captured request marks the registration paid', async () => {
		const row = await registration(ids.annas)
		if (row.paymentState === 'open') {
			// shillinq captures the request; larpinq follows its object event.
			const res = await admin.patch(
				`${OR_BASE}/shillinq/PaymentRequest/${row.paymentRequestId}`,
				{
					headers: JSON_HEADERS,
					data: { state: 'captured' },
				},
			)
			expect(res.ok(), await res.text()).toBe(true)
		} else {
			// Without shillinq a game master sets the state by hand.
			await patchRegistration(ids.annas, { paymentState: 'paid' })
		}

		await expect
			.poll(async () => (await registration(ids.annas)).paymentState)
			.toBe('paid')
	})

	// @e2e openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#anna-forgot-to-pay
	test('the reminder moment notifies the player', async () => {
		ids.reminded = await make('larping_registration', {
			event: ids.event,
			player: ids.anna,
			ticketType: ids.ticket,
		})
		await patchRegistration(ids.reminded, { status: 'accepted' })
		// What the daily job writes three days before the pay-by date.
		await patchRegistration(ids.reminded, {
			paymentReminderAt: new Date().toISOString(),
		})

		await expect
			.poll(
				async () => {
					const res = await admin.get(
						`${BASE_URL}/ocs/v2.php/apps/notifications/api/v2/notifications?format=json`,
						{ headers: { ...JSON_HEADERS, 'OCS-APIRequest': 'true' } },
					)
					const body = await res.json().catch(() => ({}))
					return JSON.stringify(body?.ocs?.data ?? [])
				},
				{ timeout: 15000 },
			)
			.toContain('WC26-')
	})

	// @e2e openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#the-place-goes-to-pieter
	test('an unpaid registration that expires gives its place to the waiting list', async () => {
		const first = await make('larping_registration', {
			event: ids.fullEvent,
			player: ids.sanne,
			ticketType: ids.ticket,
		})
		const pieters = await make('larping_registration', {
			event: ids.fullEvent,
			player: ids.pieter,
		})
		expect((await registration(first)).status).toBe('accepted')
		expect((await registration(pieters)).status).toBe('waitlisted')

		// What the daily job writes a day after the pay-by date.
		await patchRegistration(first, {
			status: 'cancelled',
			cancelReason: 'unpaid',
			paymentState: 'expired',
		})

		expect((await registration(first)).cancelReason).toBe('unpaid')
		await expect
			.poll(async () => (await registration(pieters)).status)
			.toBe('accepted')
	})
})
