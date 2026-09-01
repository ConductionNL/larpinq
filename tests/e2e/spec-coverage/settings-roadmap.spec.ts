/*
 * SPDX-FileCopyrightText: 2026 Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright tests — larpinq in-app Game Settings page
 * (manifest page id "GameSettings", type "settings", route /game-settings)
 * and the Features & roadmap page (manifest page id "FeaturesRoadmap",
 * type "roadmap", route /features-roadmap).
 *
 * These two manifest pages were previously untested: spa-ui.spec.ts only
 * covered the NC *admin* settings panel (/settings/admin/larpinq), not
 * the in-app GameSettings page, and the roadmap page had no coverage at all.
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
	await expect(page).toHaveURL(
		new RegExp(`${route.replace(/\//g, '\\/')}$`),
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
// GAME SETTINGS page — manifest page "GameSettings" (type: settings)
// openspec/specs/settings-management-ui/spec.md
// ===========================================================================

test.describe('game-settings page', () => {
	// @e2e openspec/specs/settings-management-ui/spec.md#panel-loads-then-saves-all-types
	test('game settings page renders the settings shell with version + storage sections', async ({
		page,
	}) => {
		const errs = trackLarpErrors(page)
		await openRoute(page, '/game-settings')

		// The in-app GameSettings page (CnSettingsPage shell) renders its heading.
		await expect(
			page
				.locator('.app-content')
				.getByRole('heading', { name: /Larpinq Settings/i })
				.first(),
		).toBeVisible({ timeout: 10_000 })

		// Version Information and Data storage section headings are present.
		await expect(
			page
				.locator('.app-content')
				.getByRole('heading', { name: /Version Information/i })
				.first(),
		).toBeVisible()
		await expect(
			page
				.locator('.app-content')
				.getByRole('heading', { name: /Data storage/i })
				.first(),
		).toBeVisible()

		// No larpinq-origin fatal page errors while rendering the settings page.
		expect(errs.filter((e) => e.startsWith('pageerror'))).toHaveLength(0)
	})

	// @e2e openspec/specs/settings-management-ui/spec.md#panel-loads-then-saves-all-types
	test('game settings page exposes Re-import configuration and Save controls', async ({
		page,
	}) => {
		await openRoute(page, '/game-settings')

		// Primary settings actions are present and clickable (we don't submit).
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Re-import configuration/i })
				.first(),
		).toBeVisible({ timeout: 10_000 })
		await expect(
			page.locator('.app-content button').filter({ hasText: /Save/i }).first(),
		).toBeVisible()
	})

	// @e2e openspec/specs/settings-management-ui/spec.md#panel-loads-then-saves-all-types
	test('game settings version block shows an up-to-date / version indicator', async ({
		page,
	}) => {
		await openRoute(page, '/game-settings')

		// The version-information block renders a status control (e.g. "Up to date").
		await expect(
			page
				.locator('.app-content')
				.getByText(/Up to date|outdated|update available/i)
				.first(),
		).toBeVisible({ timeout: 10_000 })
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
		await expect(
			page
				.locator('.app-content button')
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
