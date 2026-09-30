/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: the event's world, the active-world switcher and upcoming events
 * first (events-world-scope-and-upcoming).
 *
 * As admin (a game master), builds worlds Aldmoor and Outer Rim with a past
 * Aldmoor event, an upcoming Aldmoor event and an upcoming Outer Rim event.
 * Checks the dashboard lists upcoming events only, that choosing Aldmoor in
 * the switcher narrows the Events index in the list query itself, that an
 * empty list under a world offers the way back to all worlds, and that the
 * choice survives a reload. Ends on "All worlds" so other runs start clean.
 *
 * @spec openspec/specs/setting-management/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
} from './fixtures.ts'

let admin: APIRequestContext
const ledger = new FixtureLedger()
const worlds: Record<string, string> = {}
const names = {
	aldmoor: fixtureName('Aldmoor'),
	outerRim: fixtureName('Outer Rim'),
	past: fixtureName('Summer Siege 2025'),
	winter: fixtureName('Winter Court'),
	omega: fixtureName('Station Omega night'),
}

/**
 * An ISO date-time some days from now.
 *
 * @param {number} days Days from now, negative for the past.
 * @return {string} The date-time.
 */
function inDays(days: number): string {
	return new Date(Date.now() + days * 86400000).toISOString()
}

/**
 * Choose a world, or "All worlds", in the switcher on the current page.
 *
 * @param {Page} page The page.
 * @param {string} label The option label.
 * @return {Promise<void>}
 */
async function chooseWorld(page: Page, label: string): Promise<void> {
	await page.getByTestId('world-switcher-select').click()
	await page.getByRole('option', { name: label }).click()
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	worlds.aldmoor = ledger.track(
		'setting',
		await createObject(admin, 'setting', { name: names.aldmoor }),
	)
	worlds.outerRim = ledger.track(
		'setting',
		await createObject(admin, 'setting', { name: names.outerRim }),
	)
	const events: Array<[string, string, number]> = [
		[names.past, worlds.aldmoor, -300],
		[names.winter, worlds.aldmoor, 60],
		[names.omega, worlds.outerRim, 30],
	]
	for (const [name, setting, days] of events) {
		ledger.track(
			'event',
			await createObject(admin, 'event', {
				name,
				setting,
				startDate: inDays(days),
				endDate: inDays(days + 1),
			}),
		)
	}
})

test.afterAll(async () => {
	await admin.put(`${BASE_URL}/index.php${BASE}/api/preferences/active-world`, {
		data: { value: '' },
	})
	await cleanupLedger(admin, ledger)
	await admin.dispose()
})

test.describe('worlds and upcoming events', () => {
	// @e2e openspec/specs/setting-management/spec.md#a-player-checks-what-is-next
	test('the dashboard lists upcoming events, not past ones', async ({ page }) => {
		await page.goto(`${BASE_URL}/index.php${BASE}/`)
		await expect(page.getByText('Upcoming events')).toBeVisible()
		await expect(page.getByText(names.winter)).toBeVisible()
		await expect(page.getByText(names.past)).toHaveCount(0)
	})

	// @e2e openspec/specs/setting-management/spec.md#winter-court-belongs-to-aldmoor
	test("the Events index shows each event's world", async ({ page }) => {
		await page.goto(`${BASE_URL}/index.php${BASE}/events`)
		const row = page.getByRole('row', { name: new RegExp(names.winter) })
		await expect(row).toContainText(names.aldmoor)
	})

	test('choosing Aldmoor narrows the Events index in the list query', async ({
		page,
	}) => {
		await page.goto(`${BASE_URL}/index.php${BASE}/events`)
		const narrowed = page.waitForRequest((req) =>
			req.url().includes(`setting=${worlds.aldmoor}`),
		)
		await chooseWorld(page, names.aldmoor)
		await narrowed
		await expect(page.getByText(names.winter)).toBeVisible()
		await expect(page.getByText(names.omega)).toHaveCount(0)
		await expect(page.getByTestId('world-switcher-note')).toContainText(
			names.aldmoor,
		)

		// The choice is stored per user: a reload keeps it.
		await page.reload()
		await expect(page.getByText(names.omega)).toHaveCount(0)
		await expect(page.getByTestId('world-switcher-note')).toContainText(
			names.aldmoor,
		)
	})

	// @e2e openspec/specs/setting-management/spec.md#an-empty-list-under-a-world
	test('an empty list under a world offers all worlds', async ({ page }) => {
		await page.goto(`${BASE_URL}/index.php${BASE}/items`)
		await chooseWorld(page, names.outerRim)
		await expect(page.getByTestId('world-switcher-note')).toContainText(
			names.outerRim,
		)
		await page.getByTestId('world-switcher-all').click()
		await expect(page.getByTestId('world-switcher-note')).toHaveCount(0)
	})
})
