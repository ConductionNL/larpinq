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
 * Pages named by the change that larpinq does not have yet (My registrations,
 * the Cast page, lore pages) are listed as `test.fixme` so the report shows
 * them until those changes land.
 *
 * @spec openspec/changes/admin-phone-friendly-pages
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { dismissSupportDialog } from '../_nav.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
} from '../workflows/fixtures.ts'

/** Smallest tap target on the Check-in tab, in CSS pixels (REQ-APF-002). */
const MIN_TAP = 44

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
	await page.waitForLoadState('networkidle').catch(() => {})
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
	ids.event = await make('event', { name: fixtureName('Winter Court 2026') })
	ids.mirela = await make('character', {
		name: fixtureName('Mirela the Wanderer'),
		ocName: player,
		events: [ids.event],
	})
})

test.afterAll(async () => {
	await cleanupLedger(api, ledger)
	await api.dispose()
})

test.describe('Event-day pages at phone width (REQ-APF-001)', () => {
	for (const [name, path] of [
		['dashboard', '/'],
		['events-index', '/events'],
		['characters-index', '/characters'],
	]) {
		test(`${name} does not scroll sideways`, async ({ page }) => {
			await page.goto(`${BASE}${path}`)
			await settle(page)
			await expectNoSidewaysScroll(page, name)
		})
	}

	test('the character page with its Stats tab does not scroll sideways', async ({
		page,
	}) => {
		await page.goto(`${BASE}/characters/${ids.mirela}`)
		await settle(page)
		await expectNoSidewaysScroll(page, 'character-detail')
		await page.getByRole('tab', { name: 'Stats' }).click()
		await page
			.getByTestId('character-stat-sheet')
			.waitFor({ state: 'visible', timeout: 30_000 })
		await expectNoSidewaysScroll(page, 'character-stats')
	})

	test.fixme('My registrations does not scroll sideways', async () => {
		// The page arrives with the players' self-service changes.
	})

	test.fixme('the Cast page does not scroll sideways', async () => {
		// The page arrives with the casting change.
	})

	test.fixme('lore pages do not scroll sideways', async () => {
		// Lore pages arrive with the lore change.
	})
})

test.describe('Check-in works one-handed (REQ-APF-002)', () => {
	test('the Check-in tab fits and its actions are at least 44 by 44', async ({
		page,
	}) => {
		await page.goto(`${BASE}/events/${ids.event}`)
		await settle(page)
		await page.getByRole('tab', { name: 'Check-in' }).click()
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
		}
	})
})
