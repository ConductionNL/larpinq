/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The bottom-left app chrome, in a browser (ADR-114).
 *
 * gate-107 reads the manifest and can prove the entries are DECLARED. It
 * cannot prove they RENDER, and this programme has already produced three
 * defects of exactly that shape: an icon name that is not registered renders
 * NO glyph (not a fallback, not a console error), an entry whose `route` names
 * a page the app does not host renders a row that goes nowhere, and
 * `nav.includePersonalSettings: false` silently removed the entry that reaches
 * the user's notification preferences.
 *
 * The three reports are declarative `type: "dashboard"` pages over larpinq's
 * own register, which adds a fourth failure mode no manifest gate can see: a
 * widget whose `source` names a schema that does not exist renders its card,
 * its title and no value, silently. In THIS app that is a live risk, because
 * the schema slug is not the seed key — `event` is registered as
 * `larping_event` and `skill` as `larping_skill`, since slugs are global on a
 * shared OpenRegister and pipelinq also ships a `skill`. So the assertions
 * below look for VALUES, not just for cards.
 *
 * ⚠️ SCOPE EVERY SELECTOR TO `[data-testid="cn-nav"]`. An unscoped selector
 * also matches Nextcloud's own user menu, which is attached-but-hidden:
 * `waitFor({state:'attached'})` passes on it and the click never becomes
 * actionable, so the spec fails with "Target page has been closed" — a timeout
 * wearing a crash's clothes.
 *
 * ⚠️ SETTINGS ENTRIES ARE ATTACHED, NOT VISIBLE, inside a collapsed foldout.
 */

import { expect, test } from '@playwright/test'

const APP_BASE = '/apps/larpinq'

test.describe('app chrome (ADR-114)', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${APP_BASE}/`, { waitUntil: 'domcontentloaded' })
		await expect(page.locator('[data-testid="cn-nav"]')).toBeVisible({
			timeout: 30_000,
		})
	})

	test('the footer reads Documentation, Reports, Features & roadmap, each with a glyph', async ({
		page,
	}) => {
		const footer = page.locator(
			'[data-testid="cn-nav"] .cn-app-nav__footer-list',
		)
		await expect(footer).toBeAttached({ timeout: 15_000 })

		const rows = footer.locator('li')
		const texts = (await rows.allInnerTexts())
			.map((t) => t.trim())
			.filter(Boolean)

		// ORDER is the rule, not the numbers. This app ran Documentation at 90
		// and Features & roadmap at 91, which left no room between them, so the
		// roadmap moved to 100 rather than Reports being squeezed in.
		const seen = texts.filter((t) => /Documentation|Reports|roadmap/i.test(t))
		expect(seen.length).toBe(3)
		expect(seen[0]).toMatch(/Documentation/i)
		expect(seen[1]).toMatch(/Reports/i)
		expect(seen[2]).toMatch(/roadmap/i)

		// A glyph on every row. ChartBoxOutline had to be added to src/icons.js
		// for the Reports entry; without it the row renders a blank space where
		// the icon belongs and nothing complains.
		for (const row of await rows.all()) {
			await expect(
				row.locator('svg, .material-design-icon').first(),
			).toBeAttached()
		}
	})

	test('Reports lists the three reports', async ({ page }) => {
		const nav = page.locator('[data-testid="cn-nav"]')
		await nav.locator('[data-testid="cn-nav-entry-ReportsMenu"]').click()
		await expect(page).toHaveURL(/\/apps\/larpinq\/reports(\?|$)/, {
			timeout: 15_000,
		})

		for (const label of ['Character roster', 'Progression', 'World content']) {
			await expect(
				page.getByText(label, { exact: false }).first(),
			).toBeVisible({ timeout: 15_000 })
		}
	})

	test('the roster report renders real numbers, not empty cards', async ({
		page,
	}) => {
		// The point of this test. Every widget is declarative over the larpinq
		// register, so a wrong schema slug yields a card that renders its chrome
		// and no value, silently.
		await page.goto(`${APP_BASE}/reports/characters`)
		await expect(page.locator('[data-testid="cn-nav"]')).toBeVisible({
			timeout: 30_000,
		})
		await expect(
			page.getByText('Awaiting approval', { exact: false }).first(),
		).toBeVisible({ timeout: 30_000 })
		await expect(page.locator('main, .app-content').first()).toContainText(
			/\d/,
			{ timeout: 30_000 },
		)
	})

	test('the world report reads the prefixed schema slugs, not the seed keys', async ({
		page,
	}) => {
		// larping_skill and larping_item, NOT skill and item. If a later edit
		// "tidies" those back to the seed keys the cards go blank in place, so
		// this asserts a number reaches the page.
		await page.goto(`${APP_BASE}/reports/content`)
		await expect(page.locator('[data-testid="cn-nav"]')).toBeVisible({
			timeout: 30_000,
		})
		await expect(page.getByText('Skills', { exact: false }).first()).toBeVisible(
			{ timeout: 30_000 },
		)
		await expect(page.locator('main, .app-content').first()).toContainText(
			/\d/,
			{ timeout: 30_000 },
		)
	})

	test('the progression report is reachable and titled', async ({ page }) => {
		await page.goto(`${APP_BASE}/reports/progression`)
		await expect(page).toHaveURL(/\/reports\/progression(\?|$)/, {
			timeout: 15_000,
		})
		await expect(
			page.getByText('Experience awarded', { exact: false }).first(),
		).toBeVisible({ timeout: 30_000 })
	})

	test('the settings foldout carries Personal settings, Admin settings and Flows', async ({
		page,
	}) => {
		const nav = page.locator('[data-testid="cn-nav"]')

		await expect(nav.locator('[data-testid="cn-nav-settings"]')).toBeAttached({
			timeout: 15_000,
		})
		await expect(
			nav.locator('[data-testid="cn-nav-personal-settings"]'),
		).toBeAttached()
		await expect(
			nav.locator('[data-testid="cn-nav-entry-FlowsMenu"]'),
		).toBeAttached()

		const admin = nav.locator('[data-testid="cn-nav-admin-settings"]')
		await expect(admin).toBeAttached()
		await expect(admin).toHaveAttribute('href', /\/settings\/admin\/larpinq$/)
	})
})
