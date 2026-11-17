/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: a steward checks a participant in by typing the code of their
 * registration in the scan panel of the Check-in tab (events-qr-checkin).
 *
 * As admin (a game master), builds event "Winter Court 2026" with Anna's
 * accepted registration for Mirela, reads its check-in code, types it in the
 * scan field and sees Mirela checked in on the roster. The same code again
 * reports "already checked in". A context without a session gets no check-in.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './_base-url.ts'
import {
	BASE,
	cleanupLedger,
	FixtureLedger,
	fixtureName,
	newApi,
	OR_BASE,
	resolveSchemaIds,
} from './workflows/fixtures.ts'

const JSON_HEADERS = {
	Accept: 'application/json',
	'Content-Type': 'application/json',
}
const OCS_HEADERS = { ...JSON_HEADERS, 'OCS-APIRequest': 'true' }
const API = `${BASE_URL}/index.php${BASE}/api`

let admin: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}
let code = ''

/**
 * Create an object by schema slug and track it for clean-up.
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<string>} The uuid.
 */
async function make(slug: string, body: Record<string, unknown>): Promise<string> {
	const res = await admin.post(`${OR_BASE}/larpinq/${slug}`, {
		headers: JSON_HEADERS,
		data: body,
	})
	const created = await res.json().catch(() => ({}))
	expect(res.status(), JSON.stringify(created)).toBeLessThan(300)
	return ledger.track(slug, String(created.id ?? created['@self']?.id))
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
 * Mirela's attendance status on the event roster.
 *
 * @return {Promise<string>} The status, or '' when she is not on it.
 */
async function mirelaStatus(): Promise<string> {
	const res = await admin.get(`${API}/events/${ids.event}/roster`, {
		headers: OCS_HEADERS,
	})
	expect(res.ok(), await res.text()).toBe(true)
	const body = await res.json()
	const row = (body.participants ?? []).find(
		(p: { character: string }) => p.character === ids.mirela,
	)
	return String(row?.status ?? '')
}

/**
 * Open the event and its Check-in tab, and the scan panel in it.
 *
 * @param {Page} page The page.
 * @return {Promise<void>}
 */
async function openScan(page: Page): Promise<void> {
	await page.goto(`${BASE_URL}/index.php${BASE}/events/${ids.event}`)
	await page
		.getByTestId('cn-object-sidebar')
		.waitFor({ state: 'visible', timeout: 30_000 })
	await page.getByTestId('cn-object-sidebar-tab-checkin').click()
	await page.getByTestId('event-roster-scan-toggle').click()
	await expect(page.getByTestId('event-roster-scan-form')).toBeVisible()
}

/**
 * Type a code in the scan field and submit it.
 *
 * @param {Page} page The page.
 * @param {string} value The code.
 * @return {Promise<void>}
 */
async function typeCode(page: Page, value: string): Promise<void> {
	await page.getByTestId('event-roster-scan-code').locator('input').fill(value)
	await page.getByTestId('event-roster-scan-code').locator('input').press('Enter')
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	ids.world = await make('setting', { name: fixtureName('Aldmoor') })
	ids.anna = await make('player', {
		name: fixtureName('Anna de Vries'),
		userUid: process.env.NC_USER || 'admin',
	})
	ids.mirela = await make('character', {
		name: fixtureName('Mirela the Wanderer'),
		ocName: ids.anna,
		status: 'active',
		setting: ids.world,
	})
	ids.event = await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
		capacity: 5,
		approvalRequired: false,
	})
	ids.registration = await make('larping_registration', {
		event: ids.event,
		player: ids.anna,
		character: ids.mirela,
	})
	const registration = await read('larping_registration', ids.registration)
	expect(registration.status).toBe('accepted')
	code = String(registration.checkinCode ?? '')
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	await admin.dispose()
})

test.describe.serial('check in by QR code', () => {
	// @e2e openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#annas-registration-is-accepted
	test('an accepted registration carries a check-in code', async () => {
		expect(code).not.toBe('')
	})

	// @e2e openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#anna-arrives-at-the-gate
	test("typing Anna's code checks Mirela in on the roster", async ({ page }) => {
		expect(await mirelaStatus()).not.toBe('checked-in')
		await openScan(page)
		await typeCode(page, code)

		const result = page.getByTestId('event-roster-scan-result')
		await expect(result).toContainText('Checked in')
		await expect(result).toContainText(fixtureName('Mirela the Wanderer'))
		await expect(
			page.getByTestId('event-roster-table').getByText(fixtureName('Mirela the Wanderer')),
		).toBeVisible()
		expect(await mirelaStatus()).toBe('checked-in')
	})

	// @e2e openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#the-same-code-twice
	test('the same code again says she is already checked in', async ({ page }) => {
		await openScan(page)
		await typeCode(page, code)

		await expect(page.getByTestId('event-roster-scan-result')).toContainText(
			'Already checked in',
		)
		expect(await mirelaStatus()).toBe('checked-in')
	})

	// @e2e openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-a-code-is-checked-in-once-and-only-for-its-event-req-eqc-004
	test('an unknown code checks nobody in', async ({ page }) => {
		await openScan(page)
		await typeCode(page, 'ZZZZ-ZZZZ-ZZZZ')

		await expect(page.getByTestId('event-roster-scan-result')).toContainText(
			'Nobody was checked in',
		)
	})

	// @e2e openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-stewards-check-in-by-scanning-or-typing-a-code-req-eqc-003
	test('a request without a session checks nobody in', async () => {
		const anonymous = await request.newContext({
			storageState: { cookies: [], origins: [] },
		})
		const res = await anonymous.post(`${API}/events/${ids.event}/checkin-code`, {
			headers: OCS_HEADERS,
			data: { code },
		})
		expect(res.ok()).toBe(false)
		await anonymous.dispose()
	})
})
