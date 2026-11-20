/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Check-in by code at the door: a steward types Anna's code in the scan field
 * of the Check-in tab and sees her checked in on the roster; typing it again
 * says she is already checked in (REQ-EQC-003, REQ-EQC-004).
 *
 * The code is masked on every list read (`"reveal": "explicit"`), so the
 * server has to look the registration up inside OpenRegister's trusted reveal
 * scope. This spec is what shows that lookup works on a real instance.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { openSidebarTab } from './_nav.ts'
import { FixtureLedger, fixtureName, newApi, OR_BASE } from './workflows/fixtures.ts'

const BASE = '/apps/larpinq'
const JSON_HEADERS = { Accept: 'application/json', 'Content-Type': 'application/json', 'OCS-APIRequest': 'true' }

let api: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}
let code = ''

/**
 * Create one larpinq object by schema slug and track it for cleanup.
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<object>} The created object.
 */
async function make(slug: string, body: Record<string, unknown>): Promise<Record<string, any>> {
	const res = await api.post(`${OR_BASE}/larpinq/${slug}`, { headers: JSON_HEADERS, data: body })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const created = await res.json()
	ledger.track(slug, String(created.id))
	return created
}

test.beforeAll(async () => {
	api = await newApi()
	ids.world = (await make('setting', { name: fixtureName('Aldmoor') })).id
	ids.anna = (await make('player', { name: fixtureName('Anna de Vries') })).id
	ids.mirela = (await make('character', {
		name: fixtureName('Mirela the Wanderer'),
		ocName: ids.anna,
		status: 'active',
		setting: ids.world,
	})).id
	ids.event = (await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
		capacity: 5,
		approvalRequired: false,
	})).id
	const registration = await make('larping_registration', { event: ids.event, player: ids.anna, character: ids.mirela })
	expect(registration.status).toBe('accepted')

	const revealed = await api.get(`${OR_BASE}/larpinq/larping_registration/${registration.id}?_reveal=checkinCode`, { headers: JSON_HEADERS })
	expect(revealed.ok()).toBe(true)
	code = (await revealed.json()).checkinCode
	expect(code).toMatch(/^[A-Z2-7]{26}$/)
})

test.afterAll(async () => {
	const attendance = await api.get(`${OR_BASE}/larpinq/larping_attendance?event=${ids.event}`, { headers: JSON_HEADERS })
	for (const row of attendance.ok() ? (await attendance.json()).results ?? [] : []) {
		ledger.track('larping_attendance', String(row.id))
	}
	for (const slug of ['larping_attendance', 'larping_registration', 'larping_event', 'character', 'player', 'setting']) {
		for (const id of ledger.ids(slug)) {
			await api.delete(`${OR_BASE}/larpinq/${slug}/${id}`, { headers: JSON_HEADERS }).catch(() => undefined)
		}
	}
	await api.dispose()
})

test('a typed code checks Anna in once, and a second use says she already is', async ({ page }) => {
	await page.goto(`${BASE}/events/${ids.event}`)
	await openSidebarTab(page, 'Check-in')
	const roster = page.getByTestId('event-roster-table')
	await expect(roster).toContainText('Mirela the Wanderer', { timeout: 30_000 })

	await page.getByTestId('event-roster-scan-toggle').click()
	const field = page.getByTestId('event-roster-scan-form').getByRole('textbox')
	const result = page.getByTestId('event-roster-scan-result')

	// Typed the way a person reads it off the screen: lower case, in groups.
	await field.fill(code.toLowerCase().replace(/(.{4})/g, '$1 '))
	await page.getByTestId('event-roster-scan-submit').click()
	await expect(result).toContainText('Checked in:')
	await expect(result).toContainText('Mirela the Wanderer')
	await expect(roster.getByRole('row').filter({ hasText: 'Mirela the Wanderer' })).toContainText('Checked in')

	await expect(result).toBeHidden({ timeout: 10_000 })
	await field.fill(code)
	await page.getByTestId('event-roster-scan-submit').click()
	await expect(result).toContainText('Already checked in')
})
