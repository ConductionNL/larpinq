/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright tests — larpingapp index (list) pages, deepened.
 *
 * spa-ui.spec.ts already asserts each index route is reachable, but because
 * it reuses a single in-session sidebar navigation it cannot distinguish the
 * pages: an in-session sidebar click does NOT re-key the index component, so
 * every list renders the Characters surface (shared-list-state collapse).
 *
 * This file does an honest per-page check: it hard-loads the SPA root once
 * per test, navigates to the target index, and asserts the *entity-specific*
 * surface — the correct "Add <Entity>" primary action, the view-mode toggle,
 * and a list/empty-state container. This proves each manifest index page
 * renders its own schema's UI rather than a generic shell.
 *
 * Data-independent: the OR MagicMapper bare-UUID-slug bug makes the object
 * fetch 500/empty in a bare env, so we assert the page *shell* and primary
 * controls, never data rows. The data-fetch 500 is a known OR backend issue,
 * not a larpingapp UI regression, so we only fail on larpingapp *pageerrors*.
 *
 * Authentication: globalSetup writes tests/e2e/.auth/admin.json;
 * playwright.config.ts wires storageState so each test starts logged in.
 */

import { test, expect, type Page } from '@playwright/test'

const BASE = '/apps/larpingapp'

/**
 * Each index manifest page: route slug, sidebar nav label, and the singular
 * entity word that appears in its primary "Add <Entity>" button.
 */
const INDEX_PAGES: Array<{ slug: string, nav: string, entity: string }> = [
	{ slug: 'characters', nav: 'Characters', entity: 'Character' },
	{ slug: 'players', nav: 'Players', entity: 'Player' },
	{ slug: 'abilities', nav: 'Abilities', entity: 'Ability' },
	{ slug: 'skills', nav: 'Skills', entity: 'Skill' },
	{ slug: 'items', nav: 'Items', entity: 'Item' },
	{ slug: 'conditions', nav: 'Conditions', entity: 'Condition' },
	{ slug: 'effects', nav: 'Effects', entity: 'Effect' },
	{ slug: 'events', nav: 'Events', entity: 'Event' },
]

/** Dismiss the cn-support-dialog / first-load support modal if it blocks clicks. */
async function dismissSupportDialog(page: Page): Promise<void> {
	const close = page.locator('[role="dialog"] button[aria-label="Close"], .cn-support-dialog button[aria-label="Close"]').first()
	if (await close.isVisible({ timeout: 1500 }).catch(() => false)) {
		await close.click().catch(() => {})
		await page.waitForTimeout(200)
	}
}

/**
 * Hard-load the SPA root, dismiss the support modal, then nav-click the
 * sidebar entry. A fresh root load per test re-keys the index component so
 * the entity-specific surface renders (avoids the shared-list-state collapse).
 */
async function freshNav(page: Page, slug: string, navLabel: string): Promise<void> {
	await page.goto(`${BASE}/`)
	await page.waitForLoadState('networkidle').catch(() => {})
	await dismissSupportDialog(page)
	const link = page.locator(`.app-navigation a[href="${BASE}/#/${slug}"]`).first()
	await expect(link).toBeVisible({ timeout: 10_000 })
	await link.click()
	await expect(page).toHaveURL(new RegExp(`#/${slug}(\\b|/|$|\\?)`))
	await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
	await page.waitForLoadState('networkidle').catch(() => {})
}

/** Collect larpingapp-origin fatal page errors during a test. */
function trackPageErrors(page: Page): string[] {
	const errs: string[] = []
	page.on('pageerror', (e) => errs.push(e.message))
	return errs
}

// ===========================================================================
// Per-entity index pages — each renders its own schema surface.
//   character/player  → events-players + character-management specs
//   ability/skill/item/condition/effect → game-mechanics spec
// ===========================================================================

for (const { slug, nav, entity } of INDEX_PAGES) {
	test.describe(`index page: ${slug}`, () => {
		// @e2e openspec/specs/game-mechanics/spec.md#list-abilities-with-search
		test(`${slug} index renders the "Add ${entity}" primary action`, async ({ page }) => {
			const errs = trackPageErrors(page)
			await freshNav(page, slug, nav)

			// The page-specific primary create action proves this is the right
			// index page (not the generic/Characters shell).
			await expect(
				page.locator('.app-content button').filter({ hasText: new RegExp(`Add ${entity}`, 'i') }).first(),
			).toBeVisible({ timeout: 10_000 })

			// The sidebar still exposes this entity as an accessible link. The nav
			// is grouped/collapsed (CnAppNav), so the entity label can appear twice
			// — once on the collapsible group header (href="#") and once on the
			// real entry link — which trips strict-mode on a bare role+name lookup.
			// Assert on the stable per-entry test id so we target the actual nav
			// entry (its link carries the hash route href).
			await expect(
				page.getByTestId(`cn-nav-entry-${nav}`).getByRole('link', { name: nav }),
			).toBeVisible()

			// No larpingapp-origin fatal page errors (the OR data-fetch 500 is a
			// backend MagicMapper issue, not a UI crash, and surfaces as a console
			// log not a pageerror — so pageerrors must be empty).
			expect(errs).toHaveLength(0)
		})

		// @e2e openspec/specs/game-mechanics/spec.md#list-skills-with-search
		test(`${slug} index renders the view-mode toggle and a list-or-empty surface`, async ({ page }) => {
			await freshNav(page, slug, nav)

			// The index toolbar exposes a view-mode toggle (table / cards radios).
			await expect(
				page.locator('.app-content input[type="radio"]').first(),
			).toBeVisible({ timeout: 10_000 })

			// A list container or an empty-state is rendered (data-independent:
			// in a bare env the empty-state shows; with data the list shows).
			const listOrEmpty = page.locator(
				'.app-content .empty-content, .app-content [class*="empty"], .app-content table, .app-content [role="table"], .app-content ul, .app-content [class*="list"]',
			).first()
			await expect(listOrEmpty).toBeVisible({ timeout: 10_000 })
		})

		// @e2e openspec/specs/character-management/spec.md#create-a-new-character
		test(`${slug} index opens the "Add ${entity}" create dialog`, async ({ page }) => {
			await freshNav(page, slug, nav)

			const addBtn = page.locator('.app-content button').filter({ hasText: new RegExp(`Add ${entity}`, 'i') }).first()
			await expect(addBtn).toBeVisible({ timeout: 10_000 })
			await addBtn.click()

			// The create modal/dialog appears with a name field, then we dismiss it.
			const dialog = page.locator('[role="dialog"]').first()
			await expect(dialog).toBeVisible({ timeout: 8_000 })
			await expect(dialog.locator('input, textarea').first()).toBeVisible({ timeout: 5_000 })

			const cancel = dialog.locator('button').filter({ hasText: /Cancel|Close/i }).first()
			if (await cancel.isVisible({ timeout: 1500 }).catch(() => false)) {
				await cancel.click().catch(() => {})
			} else {
				await page.keyboard.press('Escape').catch(() => {})
			}
		})
	})
}
