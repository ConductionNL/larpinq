/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Phone-width suite (admin-phone-friendly-pages).
 *
 * Runs in the `phone` Playwright project (360 by 740, touch, device scale 2).
 * Opens each event-day page with seeded data and fails when the page scrolls
 * sideways. On the Check-in tab it also measures the check-in actions, which
 * must be at least 44 by 44 pixels. Every page saves a screenshot for
 * docs/features/phone-use.md.
 *
 *   npx playwright test --project phone
 *
 * The Check-in roster and the Stats sheet are tabs of the object sidebar,
 * which a phone shows closed: the suite opens it first.
 *
 * @spec openspec/changes/archive/2026-11-25-admin-phone-friendly-pages
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { dismissSupportDialog, openSidebarTab } from '../_nav.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	OR_BASE,
	resolveSchemaIds,
} from '../workflows/fixtures.ts'

/** Smallest tap target on the Check-in tab, in CSS pixels (REQ-APF-002). */
const MIN_TAP = 44

/**
 * Overflow inside nextcloud-vue components that the sideways-scroll check
 * does not catch, reported upstream (REQ-APF-001, task 1.4). Each test that
 * shows it carries the issue as an annotation.
 */
const UPSTREAM = {
	sidebarTabs: 'https://github.com/ConductionNL/nextcloud-vue/issues/2355',
	dashboardColumns: 'https://github.com/ConductionNL/nextcloud-vue/issues/2356',
	filterLabel: 'https://github.com/ConductionNL/nextcloud-vue/issues/2357',
}

/**
 * Mark the running test with a known upstream overflow.
 *
 * @param {string} url The issue.
 * @return {void}
 */
function knownUpstream(url: string): void {
	test.info().annotations.push({ type: 'issue', description: url })
}

/** Headers for OpenRegister object calls by schema slug. */
const JSON_HEADERS = {
	Accept: 'application/json',
	'Content-Type': 'application/json',
	'OCS-APIRequest': 'true',
}

/** Where the screenshots for the docs land. */
const SHOTS = 'docs/static/screenshots/phone'

let api: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}

/**
 * Create one object and remember it for cleanup.
 *
 * @param {string} type The schema.
 * @param {object} body The object.
 * @return {Promise<string>} The UUID.
 */
async function make(type: string, body: object): Promise<string> {
	return ledger.track(type, await createObject(api, type, body))
}

/**
 * Wait for the page to settle and clear first-visit dialogs.
 *
 * @param {Page} page The page.
 * @return {Promise<void>}
 */
async function settle(page: Page): Promise<void> {
	// Never `networkidle`: Nextcloud's notification poll keeps the network
	// busy (ADR-074 rule 4). Wait for the app's content and its spinners.
	await page.waitForLoadState('domcontentloaded')
	await page
		.locator('#app-content-vue, #app-content, main')
		.first()
		.waitFor({ state: 'visible', timeout: 30_000 })
	await expect(page.locator('.loading-icon, .icon-loading')).toHaveCount(0, {
		timeout: 30_000,
	})
	await dismissSupportDialog(page)
}

/**
 * Assert the page does not scroll sideways and save a screenshot.
 *
 * @param {Page} page The page.
 * @param {string} name The screenshot name.
 * @return {Promise<void>}
 */
async function expectNoSidewaysScroll(page: Page, name: string): Promise<void> {
	const widths = await page.evaluate(() => ({
		scroll: document.documentElement.scrollWidth,
		inner: window.innerWidth,
	}))
	await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: false })
	expect(
		widths.scroll,
		`${name}: the page is ${widths.scroll}px wide on a ${widths.inner}px screen`,
	).toBeLessThanOrEqual(widths.inner)
}

test.beforeAll(async () => {
	api = await newApi()
	await resolveSchemaIds(api)
	const player = await make('player', { name: fixtureName('Anna') })
	ids.event = await make('event', {
		name: fixtureName('Winter Court 2026'),
		capacity: 5,
		approvalRequired: false,
	})
	ids.mirela = await make('character', {
		name: fixtureName('Mirela the Wanderer'),
		ocName: player,
		status: 'active',
		events: [ids.event],
	})
	// An accepted registration puts Mirela on the Check-in roster.
	const res = await api.post(`${OR_BASE}/larpinq/larping_registration`, {
		headers: JSON_HEADERS,
		data: { event: ids.event, player, character: ids.mirela },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const registration = await res.json()
	expect(registration.status).toBe('accepted')
	ids.registration = String(registration.id)
})

test.afterAll(async () => {
	const attendance = await api.get(
		`${OR_BASE}/larpinq/larping_attendance?event=${ids.event}`,
		{ headers: JSON_HEADERS },
	)
	const rows = attendance.ok() ? ((await attendance.json()).results ?? []) : []
	for (const row of rows) {
		await api
			.delete(`${OR_BASE}/larpinq/larping_attendance/${row.id}`, { headers: JSON_HEADERS })
			.catch(() => undefined)
	}
	if (ids.registration) {
		await api
			.delete(`${OR_BASE}/larpinq/larping_registration/${ids.registration}`, {
				headers: JSON_HEADERS,
			})
			.catch(() => undefined)
	}
	await cleanupLedger(api, ledger)
	await api.dispose()
})

test.describe('Event-day pages at phone width (REQ-APF-001)', () => {
	for (const [name, path, issue] of [
		['dashboard', '/', UPSTREAM.dashboardColumns],
		['events-index', '/events', UPSTREAM.filterLabel],
		['characters-index', '/characters', UPSTREAM.filterLabel],
		['my-registrations', '/my-registrations', ''],
		['cast', '/cast', ''],
		['lore', '/lore', ''],
	]) {
		test(`${name} does not scroll sideways`, async ({ page }) => {
			if (issue !== '') {
				knownUpstream(issue)
			}
			await page.goto(`${BASE}${path}`)
			await settle(page)
			await expectNoSidewaysScroll(page, name)
		})
	}

	test('the character page with its Stats tab does not scroll sideways', async ({
		page,
	}) => {
		knownUpstream(UPSTREAM.sidebarTabs)
		await page.goto(`${BASE}/characters/${ids.mirela}`)
		await settle(page)
		await expectNoSidewaysScroll(page, 'character-detail')
		await openSidebarTab(page, 'Stats')
		// A character without abilities renders an empty sheet, so wait for
		// it to finish loading rather than for it to have a size.
		const sheet = page.getByTestId('character-stat-sheet')
		await sheet.waitFor({ state: 'attached', timeout: 30_000 })
		await expect(sheet.getByText('Loading stats')).toHaveCount(0, { timeout: 30_000 })
		await expectNoSidewaysScroll(page, 'character-stats')
	})
})

test.describe('Check-in works one-handed (REQ-APF-002)', () => {
	test('the Check-in tab fits and its actions are at least 44 by 44', async ({
		page,
	}) => {
		knownUpstream(UPSTREAM.sidebarTabs)
		await page.goto(`${BASE}/events/${ids.event}`)
		await settle(page)
		await openSidebarTab(page, 'Check-in')
		await page
			.getByTestId('event-roster')
			.waitFor({ state: 'visible', timeout: 30_000 })
		await expectNoSidewaysScroll(page, 'event-checkin')

		const actions = page.locator(
			'[data-testid="event-roster-checkin"], [data-testid="event-roster-noshow"]',
		)
		const count = await actions.count()
		expect(
			count,
			'the seeded participant shows check-in actions',
		).toBeGreaterThan(0)
		for (let index = 0; index < count; index++) {
			const box = await actions.nth(index).boundingBox()
			expect(box, 'the action is on screen').not.toBeNull()
			expect(box!.width).toBeGreaterThanOrEqual(MIN_TAP)
			expect(box!.height).toBeGreaterThanOrEqual(MIN_TAP)
			// Stacked rows: the action is reachable without scrolling the
			// roster sideways.
			expect(
				box!.x + box!.width,
				'the action sits inside the phone screen',
			).toBeLessThanOrEqual(page.viewportSize()!.width)
		}
	})
})
