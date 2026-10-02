/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: sign-ups become registrations (registration-intake-and-capacity).
 *
 * The admin stands in for game masters and, where noted, for the Forms
 * intake (which writes the registration with the app's authority, exactly as
 * posted here). The form-to-registration step itself runs through Nextcloud
 * Forms' submission API when the forms app is enabled, and is skipped with a
 * note otherwise; FormSubmissionListenerTest proves what larpinq does with the
 * event Forms sends.
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
const OCS_HEADERS = { ...JSON_HEADERS, 'OCS-APIRequest': 'true' }

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

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	ids.world = await make('setting', { name: fixtureName('Aldmoor') })
	ids.anna = await make('player', {
		name: fixtureName('Anna de Vries'),
		userUid: process.env.NC_USER || 'admin',
	})
	ids.karel = await make('player', { name: fixtureName('Karel') })
	ids.mirela = await make('character', {
		name: fixtureName('Mirela the Wanderer'),
		ocName: ids.anna,
		status: 'active',
		setting: ids.world,
	})
	ids.harrow = await make('character', {
		name: fixtureName('Old Captain Harrow'),
		ocName: ids.karel,
		status: 'retired',
		setting: ids.world,
	})
	ids.venn = await make('character', {
		name: fixtureName('Lady Venn'),
		ocName: ids.karel,
		status: 'active',
		setting: ids.world,
	})
	ids.event = await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
		capacity: 1,
		approvalRequired: false,
	})
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	await admin.dispose()
})

test.describe.serial('sign-ups become registrations', () => {
	// @e2e openspec/specs/event-registration/spec.md#the-roster-follows
	test('a registration with a free place is accepted and its character joins the event', async () => {
		ids.first = await make('larping_registration', {
			event: ids.event,
			player: ids.anna,
			character: ids.mirela,
		})

		expect((await read('larping_registration', ids.first)).status).toBe(
			'accepted',
		)
		expect((await read('larping_event', ids.event)).players).toContain(
			ids.mirela,
		)
	})

	// @e2e openspec/specs/event-registration/spec.md#the-last-place
	test('a sign-up for a full event is waitlisted', async () => {
		ids.second = await make('larping_registration', {
			event: ids.event,
			player: ids.karel,
			character: ids.venn,
		})

		expect((await read('larping_registration', ids.second)).status).toBe(
			'waitlisted',
		)
	})

	// @e2e openspec/specs/event-registration/spec.md#a-place-frees-up
	test('cancelling the accepted registration gives the place to the waiting list', async () => {
		const cancelled = await patch('larping_registration', ids.first, {
			status: 'cancelled',
		})
		expect(cancelled.status, JSON.stringify(cancelled.body)).toBeLessThan(300)

		expect((await read('larping_registration', ids.second)).status).toBe(
			'accepted',
		)
		const players = (await read('larping_event', ids.event)).players
		expect(players).toContain(ids.venn)
		expect(players).not.toContain(ids.mirela)
	})

	// @e2e openspec/specs/event-registration/spec.md#a-game-master-declines-a-sign-up-for-a-retired-character
	test('on an event with approval a sign-up waits, and a declined one takes no place', async () => {
		ids.approval = await make('larping_event', {
			name: fixtureName('Spring Moot 2027'),
			setting: ids.world,
			capacity: 3,
			approvalRequired: true,
		})
		ids.karels = await make('larping_registration', {
			event: ids.approval,
			player: ids.karel,
		})
		expect((await read('larping_registration', ids.karels)).status).toBe(
			'pending',
		)

		await patch('larping_registration', ids.karels, { status: 'declined' })
		const karels = await read('larping_registration', ids.karels)
		expect(karels.status).toBe('declined')
		expect(karels.decidedBy).toBe(process.env.NC_USER || 'admin')
	})

	// @e2e openspec/specs/event-registration/spec.md#anna-chooses-mirela
	test('a registration brings only its player own active character', async () => {
		ids.annas = await make('larping_registration', {
			event: ids.approval,
			player: ids.anna,
		})

		const harrow = await patch('larping_registration', ids.annas, {
			character: ids.harrow,
		})
		expect(harrow.status).toBeGreaterThanOrEqual(400)

		const mirela = await patch('larping_registration', ids.annas, {
			character: ids.mirela,
		})
		expect(mirela.status, JSON.stringify(mirela.body)).toBeLessThan(300)
	})

	// @e2e openspec/specs/event-registration/spec.md#anna-signs-up-for-the-winter-event
	test('a form answer on the event sign-up form becomes a registration', async ({
		page,
	}) => {
		const created = await admin.post(
			`${BASE_URL}/ocs/v2.php/apps/forms/api/v3/forms`,
			{ headers: OCS_HEADERS, data: {} },
		)
		test.skip(!created.ok(), 'Nextcloud Forms is not enabled on this instance')
		const form = (await created.json()).ocs.data
		const question = await admin.post(
			`${BASE_URL}/ocs/v2.php/apps/forms/api/v3/forms/${form.id}/questions`,
			{ headers: OCS_HEADERS, data: { type: 'short', text: 'Diet' } },
		)
		const questionId = (await question.json()).ocs.data.id
		await patch('larping_event', ids.approval, { signupForm: form.id })

		const sent = await admin.post(
			`${BASE_URL}/ocs/v2.php/apps/forms/api/v3/forms/${form.id}/submissions`,
			{
				headers: OCS_HEADERS,
				data: { answers: { [questionId]: ['none'] } },
			},
		)
		expect(sent.ok(), await sent.text()).toBe(true)

		await page.goto(`${BASE_URL}/index.php/apps/larpinq/registrations`)
		await expect(
			page.getByRole('row', { name: /pending/ }).first(),
		).toBeVisible()
		await admin.delete(
			`${BASE_URL}/ocs/v2.php/apps/forms/api/v3/forms/${form.id}`,
			{ headers: OCS_HEADERS },
		)
	})
})
