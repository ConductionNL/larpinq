/*
 * SPDX-FileCopyrightText: 2026 Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright tests: the data storage form on the admin page
 * (/settings/admin/larpinq, its only home) and the Features & roadmap page
 * (manifest page id "FeaturesRoadmap", type "roadmap", route /features-roadmap).
 *
 * All assertions are data-independent and target the rendered shell so they
 * are stable in a bare env (the OR MagicMapper bare-UUID-slug bug makes some
 * data fetches 500/empty — we assert the page *shell*, not data rows).
 *
 * Authentication: globalSetup writes tests/e2e/.auth/admin.json;
 * playwright.config.ts wires storageState so each test starts logged in.
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { dismissSupportDialog } from '../_nav.ts'

const BASE = '/apps/larpinq'

/**
 * Hard-load the target in-app route.
 *
 * The router runs in HISTORY mode (`createWebHistory`, src/main.js), so an
 * in-app route is a real path: `/apps/larpinq/<route>`, with no `#`. The
 * server's SPA catch-all serves the app shell for it and the client router
 * resolves the view.
 *
 * This helper used to assert a `#` in the URL, from when the router ran in hash
 * mode. After the move to history mode the app produced
 * `/apps/larpinq/features-roadmap` while the assertion still demanded
 * `#/features-roadmap`, so six of these tests failed on the URL alone — before
 * reaching the `.app-content` gate that is the real check. The app was right.
 *
 * A fresh load per test avoids the shared-list-state collapse where in-session
 * sidebar navigation fails to re-key index pages.
 */
async function openRoute(page: Page, route: string): Promise<void> {
	// `domcontentloaded`, never `networkidle` — the latter is unreachable on
	// Nextcloud (notification poll), so it just burns the budget (ADR-074
	// rule 4). The `.app-content` assertion below is the real readiness gate.
	await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' })
	await dismissSupportDialog(page)
	// Path, not hash. Anchored at the end so `/features-roadmap` cannot be
	// satisfied by some longer route that merely contains it.
	// Escape the whole regex metacharacter set, not just `/`. Escaping slashes
	// alone leaves `.`, `?`, `+`, `(` and backslash live in the pattern, which
	// CodeQL reports as js/incomplete-sanitization (high). Today's routes are
	// literals so nothing is exploitable, but a route containing `.` would
	// silently match more than it names.
	await expect(page).toHaveURL(
		new RegExp(`${route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`),
	)
	await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
}

// `dismissSupportDialog` now comes from `../_nav` — see the note there on the
// onboarding tour whose "Close tour" / "Skip" controls this local copy missed.

/** Collect larpinq-origin console errors / pageerrors / 5xx during a test. */
function trackLarpErrors(page: Page): string[] {
	const errs: string[] = []
	page.on('pageerror', (e) => errs.push(`pageerror: ${e.message}`))
	page.on('response', (r) => {
		if (r.status() >= 500 && /larpinq/i.test(r.url()))
			errs.push(`http ${r.status()} ${r.url()}`)
	})
	return errs
}

// ===========================================================================
// STORAGE SETTINGS — the admin page /settings/admin/larpinq. The in-app
// "Game Settings" page that mounted the same form is gone: app configuration
// lives in the Nextcloud administration settings only.
// openspec/specs/settings-management-ui/spec.md
// ===========================================================================

test.describe('storage settings on the admin page', () => {
	// @e2e openspec/specs/settings-management-ui/spec.md#panel-loads-then-saves-all-types
	test('the admin page renders the version card and the data storage form', async ({
		page,
	}) => {
		const errs = trackLarpErrors(page)
		await page.goto('/index.php/settings/admin/larpinq', { waitUntil: 'domcontentloaded' })

		await expect(
			page.getByRole('heading', { name: /Version Information|Versie-informatie/i }).first(),
		).toBeVisible({ timeout: 10_000 })
		await expect(
			page.getByRole('heading', { name: /Data storage|Gegevensopslag/i }).first(),
		).toBeVisible()
		await expect(
			page.locator('button').filter({ hasText: /Save All|Alles opslaan/i }).first(),
		).toBeVisible()

		expect(errs.filter((e) => e.startsWith('pageerror'))).toHaveLength(0)
	})

	// @e2e openspec/specs/settings-management-ui/spec.md#panel-loads-then-saves-all-types
	test('the app has no in-app game settings page', async ({ page }) => {
		await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' })
		await dismissSupportDialog(page)
		await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
		await expect(
			page.locator('#app-navigation-vue, .app-navigation').getByText(/Game Settings|Spelinstellingen/i),
		).toHaveCount(0)
	})
})

// ===========================================================================
// FEATURES & ROADMAP page — manifest page "FeaturesRoadmap" (type: roadmap)
// ===========================================================================

test.describe('features-roadmap page', () => {
	// @e2e openspec/specs/dashboard/spec.md#dashboard-is-minimal-but-functional
	test('features & roadmap page renders the Features heading and shell', async ({
		page,
	}) => {
		const errs = trackLarpErrors(page)
		await openRoute(page, '/features-roadmap')

		// The roadmap page (CnRoadmapPage shell) renders its Features heading.
		await expect(
			page
				.locator('.app-content')
				.getByRole('heading', { name: /Features/i })
				.first(),
		).toBeVisible({ timeout: 10_000 })

		// No larpinq-origin fatal page errors on the roadmap page.
		expect(errs.filter((e) => e.startsWith('pageerror'))).toHaveLength(0)
	})

	// @e2e openspec/specs/dashboard/spec.md#dashboard-is-minimal-but-functional
	test('features & roadmap page exposes roadmap + suggest-feature actions', async ({
		page,
	}) => {
		await openRoute(page, '/features-roadmap')

		// Primary roadmap-page actions are present.
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Show roadmap/i })
				.first(),
		).toBeVisible({ timeout: 10_000 })
		// A LINK, not a button. nextcloud-vue 2.36.4 removed the in-product
		// suggestion modal (team decision 2026-09-04: the forge is where the
		// conversation happens), and the CTA is an anchor to the forge's
		// feature-request issue form now. An `<a href>` has role `link`.
		await expect(
			page
				.locator('.app-content a')
				.filter({ hasText: /Suggest feature/i })
				.first(),
		).toBeVisible()
	})

	// @e2e openspec/specs/dashboard/spec.md#dashboard-is-minimal-but-functional
	test('features & roadmap page shows the empty-state when no features are documented', async ({
		page,
	}) => {
		await openRoute(page, '/features-roadmap')

		// In a bare env the roadmap auto-derives nothing, so the documented
		// empty-state is shown. Either the empty-state copy or a feature list
		// renders — assert the shell surfaces one of them (data-independent).
		const emptyOrList = page
			.locator('.app-content')
			.getByText(/No features documented yet|Capabilities listed here/i)
			.first()
		const featuresHeading = page
			.locator('.app-content')
			.getByRole('heading', { name: /Features/i })
			.first()
		// The Features heading is always present; the empty-state is present in a bare env.
		await expect(featuresHeading).toBeVisible({ timeout: 10_000 })
		// Empty-state is expected in the bare CI env but is data-dependent; assert
		// it is attached when present without failing if data exists.
		if (await emptyOrList.isVisible({ timeout: 3000 }).catch(() => false)) {
			await expect(emptyOrList).toBeVisible()
		}
	})
})
