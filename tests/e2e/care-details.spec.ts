/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The care pages of players-care-details-and-erasure (task 4.1), on the seed
 * of design.md:
 *
 *   Player Anna de Vries (linked to Anna's account), allergy "hazelnuts",
 *   emergency contact her brother Joost. Event Winter Court 2026, ending a
 *   week from the run, with Anna's accepted registration, so the app copies
 *   her care details into a care record for the event.
 *
 * Anna sees her care details on her player page (REQ-PCE-001). A game master
 * (the suite's admin session) sees them on her player page and in the Care
 * details list of the event, beside the count of records due for erasure
 * (REQ-PCE-002). Player Karel sees neither on Anna's page.
 *
 * Anna and Karel are real larpers made for the run, each in a browser context
 * with an empty storage state. Fixtures carry RUN and are removed in afterAll.
 *
 * Not run in the build that wrote it: it needs a live instance.
 *
 * @spec openspec/specs/player-care-details/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL, OR_OBJECTS_API } from './_base-url.ts'
import { scrollToWidget } from './_nav.ts'
import { signedInPage } from './_sign-in.ts'
import { postInInitialState } from './workflows/fixtures.ts'

const RUN = `care${Date.now().toString(36)}`
const BASE = '/apps/larpinq'
const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const JSON_HEADERS = { ...OCS, 'Content-Type': 'application/json' }
const ADMIN = { username: process.env.NC_ADMIN_USER ?? 'admin', password: process.env.NC_ADMIN_PASS ?? 'admin' }
const OBJECTS = `${OR_OBJECTS_API}/larpinq`
const ALLERGY = `hazelnuts-${RUN}`
const CONTACT = `Joost de Vries ${RUN}`

const anna = { username: `${RUN}-anna`, password: `Anna-${RUN}-pw!1` }
const karel = { username: `${RUN}-karel`, password: `Karel-${RUN}-pw!1` }
type User = typeof anna

let admin: APIRequestContext
const created: string[] = []
let playerId = ''
let eventId = ''

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

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await apiAs(ADMIN)
	for (const user of [anna, karel]) {
		const res = await admin.post('/ocs/v2.php/cloud/users', {
			headers: OCS,
			form: { userid: user.username, password: user.password, 'groups[]': 'larpers' },
		})
		expect(res.status(), `create ${user.username}`).toBe(200)
	}

	const day = 24 * 3600 * 1000
	playerId = await create('player', {
		name: `${RUN} Anna de Vries`,
		userUid: anna.username,
		allergies: ALLERGY,
		emergencyContactName: CONTACT,
		emergencyContactPhone: '+31 6 00000000',
	})
	eventId = await create('larping_event', {
		name: `${RUN} Winter Court 2026`,
		startDate: new Date(Date.now() + 5 * day).toISOString(),
		endDate: new Date(Date.now() + 7 * day).toISOString(),
		careRetentionDays: 30,
	})
	await create('larping_registration', { event: eventId, player: playerId, playerUid: anna.username, status: 'accepted' })
})

test.afterAll(async () => {
	if (!admin) return
	const records = await admin.get(`${OBJECTS}/larping_event_care_record?event=${eventId}&_limit=100`, { headers: OCS })
	if (records.ok()) {
		for (const row of ((await records.json())?.results ?? [])) {
			created.unshift(`${OBJECTS}/larping_event_care_record/${row.id ?? row['@self']?.id}`)
		}
	}
	for (const url of created) {
		await admin.delete(url, { headers: OCS }).catch(() => undefined)
	}
	for (const user of [anna, karel]) {
		await admin.delete(`/ocs/v2.php/cloud/users/${user.username}`, { headers: OCS }).catch(() => undefined)
	}
	await admin.dispose()
})

test.describe('players-care-details-and-erasure pages', () => {
	/**
	 * @e2e care::anna-records-her-allergy
	 */
	test('Anna sees her care details on her player page', async ({ browser }) => {
		const page = await pageAs(browser, anna)
		try {
			await page.goto(`${BASE}/players/${playerId}`, { waitUntil: 'domcontentloaded' })
			const care = page.locator('[data-widget-id="player-care"]').or(page.getByRole('region', { name: 'Care details' })).first()
			await expect(care).toBeVisible({ timeout: 30_000 })
			await expect(care).toContainText(ALLERGY)
			await expect(care).toContainText(CONTACT)
		} finally {
			await page.context().close()
		}
	})

	/**
	 * @e2e care::a-game-master-sees-the-care-details-on-the-player-page
	 */
	test('a game master sees them on her player page', async ({ page }) => {
		await page.goto(`${BASE}/players/${playerId}`, { waitUntil: 'domcontentloaded' })
		const care = page.locator('[data-widget-id="player-care"]').or(page.getByRole('region', { name: 'Care details' })).first()
		await expect(care).toBeVisible({ timeout: 30_000 })
		await expect(care).toContainText(ALLERGY)
	})

	/**
	 * @e2e care::karel-gets-no-care-field
	 */
	test('player Karel sees no care field on her page', async ({ browser }) => {
		const page = await pageAs(browser, karel)
		try {
			await page.goto(`${BASE}/players/${playerId}`, { waitUntil: 'domcontentloaded' })
			await expect(page.getByText(`${RUN} Anna de Vries`).first()).toBeVisible({ timeout: 30_000 })
			await expect(page.getByText(ALLERGY)).toHaveCount(0)
			await expect(page.getByText(CONTACT)).toHaveCount(0)
		} finally {
			await page.context().close()
		}
	})

	/**
	 * @e2e care::first-aid-checks-the-list
	 */
	test('a game master finds Anna in the Care details list of the event', async ({ page }) => {
		await page.goto(`${BASE}/events/${eventId}`, { waitUntil: 'domcontentloaded' })
		const list = await scrollToWidget(page, 'Care details')
		await expect(list).toBeVisible({ timeout: 30_000 })
		await expect(list).toContainText(ALLERGY, { timeout: 30_000 })
		await expect(list).toContainText(CONTACT)

		const due = await scrollToWidget(page, 'Due for erasure')
		await expect(due).toBeVisible()
		await expect(due).toContainText('0')
	})

	/**
	 * @e2e care::a-player-reads-no-care-record
	 */
	test('Karel cannot read the care records of the event', async () => {
		const asKarel = await apiAs(karel)
		try {
			const res = await asKarel.get(`${OBJECTS}/larping_event_care_record?event=${eventId}`, { headers: OCS })
			const rows = res.ok() ? ((await res.json())?.results ?? []) : []
			expect(rows).toHaveLength(0)
		} finally {
			await asKarel.dispose()
		}
	})
})
