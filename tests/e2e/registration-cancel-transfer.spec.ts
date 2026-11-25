/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Cancel or hand over tab of registration-cancel-transfer-refund
 * (task 3.1), clicked through as the players themselves:
 *
 *   Joris booked himself and his daughter Mila for "Winter Court 2026" and
 *   cancels Mila's place on her registration page; his own place stays
 *   accepted (REQ-RCT-002, scenario "Mila drops out").
 *
 *   Sanne's accepted, paid place is offered to Pieter; Pieter accepts it on
 *   the same tab and the registration is his, still accepted and paid
 *   (REQ-RCT-004, scenario "Sanne hands her place to Pieter").
 *
 * Joris, Sanne and Pieter are real larpers made for the run, each in a browser
 * context with an empty storage state. Fixtures carry RUN and are removed in
 * afterAll. The API-level flow is in workflows/registration-cancel-transfer.workflow.spec.ts.
 *
 * Not run in the build that wrote it: it needs a live instance.
 *
 * @spec openspec/specs/event-registration/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL, OR_OBJECTS_API } from './_base-url.ts'
import { signedInPage } from './_sign-in.ts'
import { postInInitialState } from './workflows/fixtures.ts'

const RUN = `rct${Date.now().toString(36)}`
const BASE = '/apps/larpinq'
const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const JSON_HEADERS = { ...OCS, 'Content-Type': 'application/json' }
const ADMIN = { username: process.env.NC_ADMIN_USER ?? 'admin', password: process.env.NC_ADMIN_PASS ?? 'admin' }
const OBJECTS = `${OR_OBJECTS_API}/larpinq`
const REGISTRATIONS_API = '/index.php/apps/larpinq/api/registrations'

const joris = { username: `${RUN}-joris`, password: `Joris-${RUN}-pw!1` }
const sanne = { username: `${RUN}-sanne`, password: `Sanne-${RUN}-pw!1` }
const pieter = { username: `${RUN}-pieter`, password: `Pieter-${RUN}-pw!1` }
type User = typeof joris
const USERS = [joris, sanne, pieter]

let admin: APIRequestContext
const created: string[] = []
const ids: Record<string, string> = {}

/**
 * A request context that signs in as one user and carries no stored session.
 *
 * @param user The account.
 * @return The context.
 */
function apiAs(user: User): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		httpCredentials: { username: user.username, password: user.password, send: 'always' },
	})
}

/**
 * A browser page signed in as one user, with an empty storage state.
 *
 * @param browser The browser.
 * @param user The account.
 * @return The page.
 */
async function pageAs(browser: Browser, user: User): Promise<Page> {
	return signedInPage(browser, user.username, user.password)
}

/**
 * Create an object as the admin and remember it for afterAll.
 *
 * @param schema The schema slug.
 * @param data The object.
 * @return The new id.
 */
async function create(schema: string, data: Record<string, unknown>): Promise<string> {
	const res = await postInInitialState(admin, `${OBJECTS}/${schema}`, data, JSON_HEADERS)
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	const id = String(json?.id ?? json?.['@self']?.id)
	created.unshift(`${OBJECTS}/${schema}/${id}`)
	return id
}

/**
 * Read a registration as the admin.
 *
 * @param id The registration id.
 * @return The registration.
 */
async function registration(id: string): Promise<Record<string, unknown>> {
	const res = await admin.get(`${OBJECTS}/larping_registration/${id}`, { headers: OCS })
	expect(res.ok(), await res.text()).toBe(true)
	return res.json()
}

/**
 * Open the Cancel or hand over tab of one registration.
 *
 * @param page The signed-in page.
 * @param id The registration id.
 * @return The tab's panel.
 */
async function openChanges(page: Page, id: string) {
	await page.goto(`${BASE}/registrations/${id}`, { waitUntil: 'domcontentloaded' })
	await page.getByRole('tab', { name: 'Cancel or hand over' }).or(page.getByText('Cancel or hand over', { exact: true })).first().click({ timeout: 30_000 })
	const panel = page.getByTestId('registration-changes')
	await expect(panel).toBeVisible({ timeout: 30_000 })
	return panel
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await apiAs(ADMIN)
	for (const user of USERS) {
		const res = await admin.post('/ocs/v2.php/cloud/users', {
			headers: OCS,
			form: { userid: user.username, password: user.password, 'groups[]': 'larpers' },
		})
		expect(res.status(), `create ${user.username}`).toBe(200)
	}

	ids.world = await create('setting', { name: `${RUN} Aldmoor` })
	ids.joris = await create('player', { name: `${RUN} Joris`, userUid: joris.username })
	ids.sanne = await create('player', { name: `${RUN} Sanne`, userUid: sanne.username })
	ids.pieter = await create('player', { name: `${RUN} Pieter`, userUid: pieter.username })
	ids.event = await create('larping_event', {
		name: `${RUN} Winter Court 2026`,
		setting: ids.world,
		capacity: 10,
		approvalRequired: false,
		cancellationPolicy: { cancelBy: '2099-11-25T00:00:00+00:00', paidCancellation: 'player-chooses' },
	})

	ids.jorisReg = await create('larping_registration', {
		event: ids.event,
		player: ids.joris,
		playerUid: joris.username,
		submitterUid: joris.username,
		status: 'accepted',
	})
	ids.sanneReg = await create('larping_registration', {
		event: ids.event,
		player: ids.sanne,
		playerUid: sanne.username,
		submitterUid: sanne.username,
		status: 'accepted',
		paymentState: 'paid',
	})

	// Joris books Mila into his group, as the Add to my booking button does.
	const asJoris = await apiAs(joris)
	try {
		const res = await asJoris.post(`${REGISTRATIONS_API}/${ids.jorisReg}/participants`, {
			headers: JSON_HEADERS,
			data: { name: `${RUN} Mila` },
		})
		const mila = await res.json().catch(() => ({}))
		expect(res.ok(), JSON.stringify(mila)).toBe(true)
		ids.milaReg = String(mila.id ?? mila['@self']?.id)
		created.unshift(`${OBJECTS}/larping_registration/${ids.milaReg}`)
	} finally {
		await asJoris.dispose()
	}
})

test.afterAll(async () => {
	if (!admin) return
	for (const url of created) {
		await admin.delete(url, { headers: OCS }).catch(() => undefined)
	}
	for (const user of USERS) {
		await admin.delete(`/ocs/v2.php/cloud/users/${user.username}`, { headers: OCS }).catch(() => undefined)
	}
	await admin.dispose()
})

test.describe('registration-cancel-transfer-refund pages', () => {
	/**
	 * @e2e openspec/specs/event-registration/spec.md#mila-drops-out
	 */
	test('Joris cancels Mila and keeps his own place', async ({ browser }) => {
		const page = await pageAs(browser, joris)
		try {
			const panel = await openChanges(page, ids.milaReg)
			await panel.getByTestId('registration-cancel').click()
			await panel.getByTestId('registration-cancel-confirm').click()
			await expect(panel.getByTestId('registration-changes-done')).toContainText('The registration is cancelled.')
		} finally {
			await page.context().close()
		}

		expect((await registration(ids.milaReg)).status).toBe('cancelled')
		expect((await registration(ids.jorisReg)).status).toBe('accepted')
	})

	/**
	 * @e2e openspec/specs/event-registration/spec.md#sanne-hands-her-place-to-pieter
	 */
	test('Sanne offers her place and Pieter accepts it', async ({ browser }) => {
		const asSanne = await pageAs(browser, sanne)
		try {
			const panel = await openChanges(asSanne, ids.sanneReg)
			await panel.getByTestId('transfer-account').locator('input').or(panel.getByLabel('Their account name')).first().fill(pieter.username)
			await panel.getByTestId('transfer-offer').click()
			await expect(panel.getByTestId('registration-changes-done')).toContainText('The offer is sent.')
		} finally {
			await asSanne.context().close()
		}

		const asPieter = await pageAs(browser, pieter)
		try {
			const panel = await openChanges(asPieter, ids.sanneReg)
			await expect(panel.getByText('A place is offered to you')).toBeVisible()
			await panel.getByTestId('transfer-accept').click()
			await expect(panel.getByTestId('registration-changes-done')).toBeVisible()
		} finally {
			await asPieter.context().close()
		}

		const handedOver = await registration(ids.sanneReg)
		expect(handedOver.player).toBe(ids.pieter)
		expect(handedOver.playerUid).toBe(pieter.username)
		expect(handedOver.status).toBe('accepted')
		expect(handedOver.paymentState).toBe('paid')
		expect(handedOver.transferredFrom).toBeTruthy()
	})
})
