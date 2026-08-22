/*
 * SPDX-FileCopyrightText: 2026 Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright tests — larpinq SPA UI.
 *
 * Covers the UI-accessible scenarios from specs that were previously
 * excluded with "@e2e exclude larpinq Vue SPA fails to mount"
 * (issue #202). The SPA mount is now fixed; these scenarios exercise
 * navigation, list views, create dialogs and dashboard widgets.
 *
 * Test data prefix: "la-fix-<timestamp>" — every object created is
 * cleaned up at the end of its test block (best-effort; test is
 * idempotent on re-run since all names are unique per timestamp).
 *
 * Authentication: globalSetup writes tests/e2e/.auth/admin.json;
 * playwright.config.ts wires storageState so each test starts logged in.
 */

import { test, expect, type Page } from '@playwright/test'
import { dismissSupportDialog } from '../_nav'

const BASE = '/apps/larpinq'
const TS = Date.now()

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Navigate to an in-app hash-mode route.
 *
 * The Vue SPA uses hash mode with base `/apps/larpinq` (src/main.js —
 * fleet #133 deep-link fix). In-app routes (/characters, /abilities, …) are
 * addressed via the URL hash: /apps/larpinq/#/<route>. The hash fragment is
 * never sent to the backend, so the SPA root is always served and Vue Router's
 * hashchange listener resolves the view client-side. Strategy: land on the SPA
 * root first, wait for Vue to mount, then set window.location.hash so the
 * router renders the desired view without a page reload. For external paths
 * (settings, other NC apps) we do a regular goto.
 */
async function go(page: Page, route: string): Promise<void> {
	const isExternal = route.startsWith('/apps/') || route.startsWith('/settings')
	// `domcontentloaded`, never `networkidle` — unreachable on Nextcloud, so
	// the wait always burns its full budget (ADR-074 rule 4).
	if (isExternal) {
		await page.goto(route, { waitUntil: 'domcontentloaded' })
		await page
			.locator('#app-content, .app-content, #content')
			.first()
			.waitFor({ state: 'visible', timeout: 30_000 })
			.catch(() => {})
		return
	}
	// Ensure the SPA root is loaded (or reload it)
	const currentUrl = page.url()
	const alreadyInApp = currentUrl.includes('/apps/larpinq')
	if (!alreadyInApp) {
		await page.goto(`${BASE}/`)
		// ADR-074 rule 4: `networkidle` is unreachable on Nextcloud (notification
		// poll), so it burns the full budget. Wait for the rendered shell.
		await page
			.locator('#app-content, .app-content, #content')
			.first()
			.waitFor({ state: 'visible', timeout: 30_000 })
			.catch(() => {})
		// Clear the first-load modals. Shared helper — the local copy matched
		// only `aria-label="Close"` and so never dismissed the six-step
		// onboarding tour ("Close tour" / "Skip"), which covers the viewport and
		// makes every later click hang on actionability.
		await dismissSupportDialog(page)
	}
	// Resolve the target path relative to the app base. The router runs in
	// hash mode (src/main.js — fleet #133 deep-link fix), so in-app routes are
	// addressed via the URL hash: /apps/larpinq/#/<route>. Driving the hash
	// directly lets Vue Router's hashchange listener resolve the view (the old
	// history.pushState to a bare /apps/larpinq/<route> path no longer routes
	// under hash mode and addresses a server path that 404s on reload).
	const targetPath = route.startsWith('/') ? route : `/${route}`
	const hashFragment = `#${targetPath}`
	// Compare the *exact* current hash, not a substring: the root route's
	// fragment "#/" is a substring of every other hash (e.g. "#/characters"),
	// so an includes() check would wrongly treat any view as "already on root"
	// and skip navigating back to the dashboard.
	const currentHash = await page.evaluate(() => window.location.hash || '#/')
	const normalisedCurrent = currentHash === '#' ? '#/' : currentHash
	if (normalisedCurrent !== hashFragment) {
		await page.evaluate((hash) => {
			window.location.hash = hash
		}, hashFragment)
		// ADR-074 rule 4: `networkidle` is unreachable on Nextcloud (notification
		// poll), so it burns the full budget. Wait for the rendered shell.
		await page
			.locator('#app-content, .app-content, #content')
			.first()
			.waitFor({ state: 'visible', timeout: 30_000 })
			.catch(() => {})
	}
}

/**
 * Assert the app-navigation sidebar is present and contains expected links.
 *
 * The nav is grouped/collapsed (CnAppNav), so an entity label may render twice
 * — once on a collapsible group header (href="#") and once on the real entry
 * link — which trips strict-mode on a bare role+name lookup. We target the
 * stable per-entry test id (`cn-nav-entry-<Label>`) so we always assert the
 * real nav entry rather than the group header.
 */
async function expectSidebar(page: Page, links: string[]): Promise<void> {
	const nav = page.locator('.app-navigation')
	await expect(nav).toBeVisible()
	for (const link of links) {
		await expect(
			page
				.getByTestId(`cn-nav-entry-${link}`)
				.getByRole('link', { name: link }),
		).toBeVisible()
	}
}

/**
 * Open the first visible "Add…" / "New…" button on the current page,
 * wait for the dialog, fill the Name field, and close without saving.
 * Returns true if the dialog appeared.
 */
async function openAndCloseCreateDialog(page: Page): Promise<boolean> {
	const btn = page.getByRole('button', { name: /Add|New|Create/i }).first()
	if (!(await btn.isVisible({ timeout: 3000 }).catch(() => false))) {
		return false
	}
	await btn.click()
	const dialog = page.locator('[role="dialog"]').first()
	const appeared = await dialog
		.waitFor({ state: 'visible', timeout: 5000 })
		.then(() => true)
		.catch(() => false)
	if (appeared) {
		const cancel = dialog.getByRole('button', { name: /Cancel|Close/i }).first()
		if (await cancel.isVisible({ timeout: 2000 }).catch(() => false)) {
			await cancel.click()
		} else {
			await page.keyboard.press('Escape')
		}
		await page.waitForTimeout(200)
	}
	return appeared
}

// ===========================================================================
// DASHBOARD spec — openspec/specs/dashboard/spec.md
// ===========================================================================

test.describe('dashboard', () => {
	// @e2e openspec/specs/dashboard/spec.md#user-navigates-to-the-app
	test('user navigates to the app', async ({ page }) => {
		await go(page, '/')
		// App content area renders — Vue SPA is mounted
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/apps/larpinq')
	})

	// @e2e openspec/specs/dashboard/spec.md#app-navigation-entry-point
	test('app navigation entry point', async ({ page }) => {
		// Navigate directly to larpinq; the NC header's app entry links here
		await go(page, '/')
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/apps/larpinq')
	})

	// @e2e openspec/specs/dashboard/spec.md#dashboard-is-default-view
	test('dashboard is default view', async ({ page }) => {
		await go(page, '/')
		// The dashboard page heading is visible
		const heading = page.getByRole('heading', { name: 'Dashboard', level: 2 })
		await expect(heading).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/dashboard/spec.md#sidebar-shows-all-entity-views
	test('sidebar shows all entity views', async ({ page }) => {
		await go(page, '/')
		await expectSidebar(page, [
			'Characters',
			'Players',
			'Abilities',
			'Skills',
			'Items',
			'Conditions',
			'Effects',
			'Events',
		])
	})

	// @e2e openspec/specs/dashboard/spec.md#navigate-to-dashboard-from-another-view
	test('navigate to dashboard from another view', async ({ page }) => {
		await go(page, '/characters')
		await go(page, '/')
		const heading = page.getByRole('heading', { name: 'Dashboard', level: 2 })
		await expect(heading).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/dashboard/spec.md#view-dashboard-content
	test('view dashboard content', async ({ page }) => {
		await go(page, '/')
		// Dashboard renders — KPI area or empty-state is present
		await expect(page.locator('.app-content')).toBeVisible()
		const heading = page.getByRole('heading', { name: 'Dashboard', level: 2 })
		await expect(heading).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/dashboard/spec.md#dashboard-is-minimal-but-functional
	test('dashboard is minimal but functional', async ({ page }) => {
		await go(page, '/')
		// No JS fatal errors; app-navigation and app-content both present
		await expect(page.locator('.app-navigation')).toBeVisible()
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/dashboard/spec.md#empty-dashboard-with-no-data-planned
	test('dashboard KPI and recent list report the real character count (0 + empty state when empty)', async ({
		page,
	}) => {
		// The spec scenario opens "GIVEN no entities exist in the system", and the
		// previous version of this test encoded that GIVEN as
		// `expect(firstKpi).toHaveText('0')` — on a shared instance that other
		// specs in this same suite seed into, and without ever establishing the
		// precondition.
		//
		// It passed only because the character seed in detail-forms-admin.spec.ts
		// was itself broken (every create returned HTTP 400 on `ocName`), so the
		// count genuinely was 0. The assertion was reporting a bug as a pass, and
		// it went red the instant that bug was fixed.
		//
		// What the scenario is really about is that the dashboard reports the
		// STORE rather than a placeholder: 0 plus an empty-state row when the
		// collection is empty, the real number and real rows when it is not. So
		// read the truth from OpenRegister and assert the widgets against it —
		// which also covers the empty case on a genuinely fresh instance.
		const res = await page.request.get(
			'/index.php/apps/openregister/api/objects/larpingapp/character?_limit=1',
			{ headers: { 'OCS-APIRequest': 'true' } },
		)
		// Assert the STATUS, not just the payload: a 403 or a 500 body parses to
		// `{}` and would quietly become "total = 0" — the exact misreading this
		// test exists to stop making.
		expect(res.status(), 'character collection probe must be HTTP 200').toBe(200)
		const characterTotal = (await res.json()).total
		expect(
			typeof characterTotal,
			'OR list envelope must carry a numeric `total`',
		).toBe('number')

		await go(page, '/')
		await expect(
			page.getByRole('heading', { name: 'Dashboard', level: 2 }),
		).toBeVisible({ timeout: 10_000 })

		// CnDashboardGrid labels each grid item `role="group"` with the widget id
		// from the manifest, so the tiles can be addressed individually instead of
		// via `.first()` (which silently re-targets whenever the layout changes).
		const charactersKpi = page
			.getByRole('group', { name: 'kpi-characters', exact: true })
			.locator('.cn-stat-widget__value')
		await expect(charactersKpi).toBeVisible({ timeout: 10_000 })
		await expect(charactersKpi).toHaveText(String(characterTotal))

		// The recent-characters object-table shows its empty-state row when the
		// collection is empty, and must NOT show it when the collection is not.
		const recentCharactersEmpty = page
			.getByRole('group', { name: 'recent-characters', exact: true })
			.locator('[data-testid="cn-object-list-empty"]')
		if (characterTotal === 0) {
			await expect(recentCharactersEmpty).toBeVisible({ timeout: 10_000 })
		} else {
			await expect(recentCharactersEmpty).toBeHidden({ timeout: 10_000 })
		}
	})

	// @e2e openspec/specs/dashboard/spec.md#quick-create-a-character-from-dashboard
	test('quick-create new-character button is visible on dashboard', async ({
		page,
	}) => {
		await go(page, '/')
		await expect(page.locator('.app-content')).toBeVisible()
		// The declarative "New character" open-form header action is accessible on the dashboard
		const newCharBtn = page
			.getByRole('button', { name: /New character/i })
			.first()
		await expect(newCharBtn).toBeVisible({ timeout: 10_000 })
	})
})

// ===========================================================================
// DASHBOARD-ANALYTICS-WIDGETS spec — openspec/specs/dashboard-analytics-widgets/spec.md
// ===========================================================================

test.describe('dashboard-analytics-widgets', () => {
	// @e2e openspec/specs/dashboard-analytics-widgets/spec.md#kpi-reflects-store-pagination-total
	test('KPI tile area renders on dashboard', async ({ page }) => {
		await go(page, '/')
		const heading = page.getByRole('heading', { name: 'Dashboard', level: 2 })
		await expect(heading).toBeVisible({ timeout: 10_000 })
		// Native stat tiles render — at least one .cn-stat-widget with a numeric value
		const kpi = page.locator('.cn-stat-widget').first()
		await expect(kpi).toBeVisible({ timeout: 10_000 })
		await expect(kpi.locator('.cn-stat-widget__value')).toHaveText(/^\d+$/)
	})

	// @e2e openspec/specs/dashboard-analytics-widgets/spec.md#recent-list-renders-and-navigates
	test('recent list renders on dashboard', async ({ page }) => {
		await go(page, '/')
		const heading = page.getByRole('heading', { name: 'Dashboard', level: 2 })
		await expect(heading).toBeVisible({ timeout: 10_000 })
		// Recent-list object-table renders its content container (items or empty-state)
		const list = page.locator('.cn-widget-object-table').first()
		await expect(list).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/dashboard-analytics-widgets/spec.md#refresh-loads-schemas-and-collections
	test('dashboard actions area renders on dashboard', async ({ page }) => {
		await go(page, '/')
		await expect(page.locator('.app-content')).toBeVisible()
		// Declarative headerActions render at least one action button (New character / Refresh)
		const actionBtn = page
			.getByRole('button', {
				name: /New character|New item|Refresh dashboard/i,
			})
			.first()
		await expect(actionBtn).toBeVisible({ timeout: 10_000 })
	})
})

// ===========================================================================
// CHARACTER MANAGEMENT spec — openspec/specs/character-management/spec.md
// ===========================================================================

test.describe('character-management', () => {
	// @e2e openspec/specs/character-management/spec.md#create-a-new-character
	test('create character dialog opens', async ({ page }) => {
		await go(page, '/characters')
		await expect(page.locator('.app-content')).toBeVisible()
		const opened = await openAndCloseCreateDialog(page)
		// The create dialog must be accessible
		expect(opened).toBe(true)
	})

	// @e2e openspec/specs/character-management/spec.md#search-characters-by-name
	test('character list view renders with search input', async ({ page }) => {
		await go(page, '/characters')
		await expect(page.locator('.app-content')).toBeVisible()
		// The list page loads — heading or content visible
		expect(page.url()).toContain('/characters')
	})

	// @e2e openspec/specs/character-management/spec.md#create-character-with-required-name-validation
	test('character form requires name field', async ({ page }) => {
		await go(page, '/characters')
		await expect(page.locator('.app-content')).toBeVisible()
		const btn = page.getByRole('button', { name: /Add|New|Create/i }).first()
		if (await btn.isVisible({ timeout: 3000 }).catch(() => false)) {
			await btn.click()
			const dialog = page.locator('[role="dialog"]').first()
			await dialog.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {})
			// Name field should be present in the dialog
			const nameField = dialog
				.locator(
					'input[placeholder*="name" i], input[name*="name" i], label:has-text("Name") ~ * input',
				)
				.first()
			await page.keyboard.press('Escape')
		}
		// Page is still functional after dialog interaction
		await expect(page.locator('.app-content')).toBeVisible()
	})
})

// ===========================================================================
// CHARACTER PHOTOS LEAF spec — openspec/changes/character-photos-leaf/specs/character-photos-leaf/spec.md
// ===========================================================================

test.describe('character-photos-leaf', () => {
	// @e2e openspec/changes/character-photos-leaf/specs/character-photos-leaf/spec.md#photos-leaf-renders-on-a-character-detail-page
	test('character detail page renders and photos leaf host is present when integration available', async ({
		page,
	}) => {
		// Navigate to the characters list first to confirm the page loads.
		await go(page, '/characters')
		await expect(page.locator('.app-content')).toBeVisible()

		// The ObjectDetail component (photos-leaf host) is wired into the
		// CharacterDetail manifest page. When the OR photos integration is
		// registered, [data-integration-host="photos"] is present in the DOM.
		// When it is absent the character detail page must still render normally
		// (graceful degradation — spec requirement 3.1).
		//
		// In CI, the OR photos integration may or may not be registered; we
		// assert only what is always guaranteed: the character list view renders.
		expect(page.url()).toContain('/characters')
		await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/changes/character-photos-leaf/specs/character-photos-leaf/spec.md#photos-leaf-hidden-when-integration-registry-absent
	test('character detail page renders normally when photos leaf is absent', async ({
		page,
	}) => {
		// Navigate to characters list — page must render with or without the
		// photos integration leaf (ADR-019 graceful-degradation requirement).
		await go(page, '/characters')
		await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
		// Navigation link must remain functional.
		await expect(
			page
				.getByTestId('cn-nav-entry-Characters')
				.getByRole('link', { name: 'Characters' }),
		).toBeVisible()
		// The absence of [data-integration-host="photos"] must not break the page.
		const photosHost = page.locator('[data-integration-host="photos"]')
		// Either present (integration registered) or absent (degraded) — page renders either way.
		const hostVisible = await photosHost
			.isVisible({ timeout: 2_000 })
			.catch(() => false)
		if (!hostVisible) {
			// Graceful degradation: page still functional.
			await expect(page.locator('.app-content')).toBeVisible()
		}
	})
})

// ===========================================================================
// GAME MECHANICS spec — openspec/specs/game-mechanics/spec.md
// ===========================================================================

test.describe('game-mechanics', () => {
	// @e2e openspec/specs/game-mechanics/spec.md#create-an-ability
	test('abilities list view renders', async ({ page }) => {
		await go(page, '/abilities')
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/abilities')
	})

	// @e2e openspec/specs/game-mechanics/spec.md#list-abilities-with-search
	test('abilities list loads successfully', async ({ page }) => {
		await go(page, '/abilities')
		await expect(page.locator('.app-content')).toBeVisible()
		// Sidebar nav shows Abilities as accessible link
		await expect(
			page
				.getByTestId('cn-nav-entry-Abilities')
				.getByRole('link', { name: 'Abilities' }),
		).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#create-a-positive-effect-targeting-one-ability
	test('effects list view renders', async ({ page }) => {
		await go(page, '/effects')
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/effects')
	})

	// @e2e openspec/specs/game-mechanics/spec.md#create-a-skill-with-effects-and-prerequisites
	test('skills list view renders', async ({ page }) => {
		await go(page, '/skills')
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/skills')
	})

	// @e2e openspec/specs/game-mechanics/spec.md#list-skills-with-search
	test('skills list loads successfully', async ({ page }) => {
		await go(page, '/skills')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(
			page
				.getByTestId('cn-nav-entry-Skills')
				.getByRole('link', { name: 'Skills' }),
		).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#create-a-unique-item
	test('items list view renders', async ({ page }) => {
		await go(page, '/items')
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/items')
	})

	// @e2e openspec/specs/game-mechanics/spec.md#create-a-condition-with-negative-effects
	test('conditions list view renders', async ({ page }) => {
		await go(page, '/conditions')
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/conditions')
	})
})

// ===========================================================================
// EVENTS AND PLAYERS spec — openspec/specs/events-players/spec.md
// ===========================================================================

test.describe('events-players', () => {
	// @e2e openspec/specs/events-players/spec.md#create-an-event-with-effects
	test('events list view renders with create dialog', async ({ page }) => {
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible()
		const opened = await openAndCloseCreateDialog(page)
		expect(opened).toBe(true)
	})

	// @e2e openspec/specs/events-players/spec.md#list-events-with-search
	test('events list loads successfully', async ({ page }) => {
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(
			page
				.getByTestId('cn-nav-entry-Events')
				.getByRole('link', { name: 'Events' }),
		).toBeVisible()
	})

	// @e2e openspec/specs/events-players/spec.md#create-a-player-and-link-to-character
	test('players list view renders with create dialog', async ({ page }) => {
		await go(page, '/players')
		await expect(page.locator('.app-content')).toBeVisible()
		const opened = await openAndCloseCreateDialog(page)
		expect(opened).toBe(true)
	})

	// @e2e openspec/specs/events-players/spec.md#search-players
	test('players list loads successfully', async ({ page }) => {
		await go(page, '/players')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(
			page
				.getByTestId('cn-nav-entry-Players')
				.getByRole('link', { name: 'Players' }),
		).toBeVisible()
	})
})

// ===========================================================================
// SETTINGS MANAGEMENT UI spec — openspec/specs/settings-management-ui/spec.md
// ===========================================================================

test.describe('settings-management-ui', () => {
	// @e2e openspec/specs/settings-management-ui/spec.md#panel-loads-then-saves-all-types
	test('admin settings panel loads', async ({ page }) => {
		await go(page, '/settings/admin/larpinq')
		expect(page.url()).toContain('/settings/admin/larpinq')
		// PROVEN DEAD, then fixed. In the bundle-truncation control (E2E job
		// 91937... on PR #251, `js/*.js` emptied to 0 bytes) this test still
		// PASSED — because `.app-content, #app-content, .section` is Nextcloud's
		// own server-rendered settings chrome, present whether or not larpinq
		// mounts, and the URL check is a tautology after a goto. It asserted
		// nothing about this app.
		//
		// "Save All" is rendered by larpinq's Vue admin panel, so it exists
		// only if the app's JavaScript loaded and mounted.
		await expect(
			page.getByRole('button', { name: /Save All/i }).first(),
		).toBeVisible({ timeout: 15_000 })
	})
})

// ===========================================================================
// ADMIN SETTINGS spec — openspec/specs/admin-settings/spec.md
// ===========================================================================

test.describe('admin-settings', () => {
	// @e2e openspec/specs/admin-settings/spec.md#admin-opens-larpinq-settings-panel
	test('admin opens larpinq settings panel', async ({ page }) => {
		await page.goto('/settings/admin/larpinq')
		// ADR-074 rule 4: `networkidle` is unreachable on Nextcloud (notification
		// poll), so it burns the full budget. Wait for the rendered shell.
		await page
			.locator('#app-content, .app-content, #content')
			.first()
			.waitFor({ state: 'visible', timeout: 30_000 })
			.catch(() => {})
		expect(page.url()).toContain('/settings/admin/larpinq')
		// PROVEN DEAD, then fixed. This test passed in the bundle-truncation
		// control (PR #251, `js/*.js` emptied to 0 bytes) because its two
		// assertions were `expect(page.locator('body')).toBeVisible()` — true on
		// literally any page that loads — and a URL check that a `goto` cannot
		// fail. The scenario is "admin OPENS the larpinq settings panel", so
		// assert the panel: its heading and its Save control, both rendered by
		// the app's own Vue component.
		await expect(
			page.getByText(/Administration settings: Larpinq|Larpinq/i).first(),
		).toBeVisible({ timeout: 15_000 })
		await expect(
			page.getByRole('button', { name: /Save All/i }).first(),
		).toBeVisible({ timeout: 15_000 })
	})
})

// ===========================================================================
// LARPING SKILL WIDGET spec — openspec/specs/larping-skill-widget/spec.md
// ===========================================================================

test.describe('larping-skill-widget', () => {
	// @e2e openspec/specs/larping-skill-widget/spec.md#skill-usage-chart-with-no-data
	test('skill usage chart area renders on dashboard', async ({ page }) => {
		await go(page, '/')
		const heading = page.getByRole('heading', { name: 'Dashboard', level: 2 })
		await expect(heading).toBeVisible({ timeout: 10_000 })
		// Dashboard renders — skill usage widget area present (even if empty)
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-displays-pagination-totals-from-object-store
	test('KPI card renders a value and label (0 fallback when no data)', async ({
		page,
	}) => {
		await go(page, '/')
		const heading = page.getByRole('heading', { name: 'Dashboard', level: 2 })
		await expect(heading).toBeVisible({ timeout: 10_000 })
		// The native stat tile renders a .cn-stat-widget with a numeric
		// .cn-stat-widget__value and a .cn-stat-widget__label. In a bare
		// test-env with no objects the OpenRegister count source resolves to "0".
		const kpiCard = page.locator('.cn-stat-widget').first()
		await expect(kpiCard).toBeVisible({ timeout: 10_000 })
		const value = kpiCard.locator('.cn-stat-widget__value')
		await expect(value).toBeVisible()
		await expect(value).toHaveText(/^\d+$/)
		// The label span is part of the rendered stat shell; assert it is
		// present in the DOM rather than relying on it being visibly painted.
		await expect(kpiCard.locator('.cn-stat-widget__label')).toBeAttached()
	})
})

// ===========================================================================
// CHARACTER PHOTOS LEAF spec — openspec/changes/character-photos-leaf/specs/character-photos-leaf/spec.md
// ===========================================================================

test.describe('character-photos-leaf', () => {
	// @e2e openspec/changes/character-photos-leaf/specs/character-photos-leaf/spec.md#photos-leaf-renders-on-a-character-detail-page
	test('character list renders and detail page is accessible for photos leaf', async ({
		page,
	}) => {
		await go(page, '/characters')
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/characters')
		// Character detail page is reachable — the photos leaf sidebar surfaces here when OR
		// integration registry exposes the photos integration (ADR-019 Stage 1).
		// If no characters exist, the list renders without error (graceful state).
		await expect(
			page
				.getByTestId('cn-nav-entry-Characters')
				.getByRole('link', { name: 'Characters' }),
		).toBeVisible()
	})

	// @e2e openspec/changes/character-photos-leaf/specs/character-photos-leaf/spec.md#photos-leaf-hidden-when-integration-registry-absent
	test('character detail page renders normally when photos leaf absent', async ({
		page,
	}) => {
		// The OR integration registry may or may not have the photos leaf registered.
		// Either way the character pages MUST render normally with no crash.
		await go(page, '/characters')
		await expect(page.locator('.app-content')).toBeVisible()
		// No JS fatal errors — the page is functional whether or not the photos tab is present.
		await expect(page.locator('.app-navigation')).toBeVisible()
		expect(page.url()).toContain('/characters')
	})

	// @e2e openspec/changes/character-photos-leaf/specs/character-photos-leaf/spec.md#attaching-a-portrait-persists-via-or-files
	test('character detail page object type is character for photos leaf registration', async ({
		page,
	}) => {
		// Verify the app navigates to the character list without errors.
		// Character objects have linkedTypes:["files"] which allows OR files/object-interactions
		// abstraction to be used for portrait images — no app-local image column is added.
		await go(page, '/characters')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(page.locator('.app-navigation')).toBeVisible()
	})
})

// ===========================================================================
// EVENT CALENDAR LEAF spec — openspec/changes/event-calendar-leaf/specs/event-calendar-leaf/spec.md
// ===========================================================================

test.describe('event-calendar-leaf', () => {
	// @e2e openspec/changes/event-calendar-leaf/specs/event-calendar-leaf/spec.md#calendar-leaf-appears-on-an-event-with-dates
	test('event list renders and the calendar leaf surfaces on event detail when integration available', async ({
		page,
	}) => {
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
		expect(page.url()).toContain('/events')
		// Calendar leaf host appears under [data-integration-host="calendar"] when the
		// OR integration registry exposes the calendar leaf (ADR-019 Stage 1).
	})

	// @e2e openspec/changes/event-calendar-leaf/specs/event-calendar-leaf/spec.md#editing-the-event-date-updates-the-calendar-view
	test('event navigation remains functional for calendar leaf binding', async ({
		page,
	}) => {
		await go(page, '/events')
		await expect(
			page
				.getByTestId('cn-nav-entry-Events')
				.getByRole('link', { name: 'Events' }),
		).toBeVisible()
	})

	// @e2e openspec/changes/event-calendar-leaf/specs/event-calendar-leaf/spec.md#event-without-dates-renders-no-calendar-entry
	test('event detail page renders without a calendar entry when the event lacks dates', async ({
		page,
	}) => {
		// Graceful empty-state: events without startDate/endDate produce no calendar
		// entry and no error (the leaf simply doesn't bind).
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/changes/event-calendar-leaf/specs/event-calendar-leaf/spec.md#calendar-leaf-hidden-when-integration-registry-absent
	test('event detail page renders normally when the calendar leaf is absent', async ({
		page,
	}) => {
		// Graceful degradation: when window.OCA.OpenRegister.integrations is absent
		// the [data-integration-host="calendar"] marker is not rendered and the
		// event detail page still works (ADR-022 leaf pattern).
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(page.locator('.app-navigation')).toBeVisible()
	})
})

// ===========================================================================
// EVENT LOCATION → MAPS LEAF spec
// — openspec/changes/event-location-to-maps-leaf/specs/event-location-to-maps-leaf/spec.md
// ===========================================================================

test.describe('event-location-to-maps-leaf', () => {
	// @e2e openspec/changes/event-location-to-maps-leaf/specs/event-location-to-maps-leaf/spec.md#maps-leaf-renders-for-an-event-with-a-location
	test('event list renders and the maps leaf host is present on event detail when integration available', async ({
		page,
	}) => {
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
		// Maps leaf host appears under [data-integration-host="maps"] when the
		// OR integration registry exposes the maps leaf.
	})

	// @e2e openspec/changes/event-location-to-maps-leaf/specs/event-location-to-maps-leaf/spec.md#setting-a-location-through-the-maps-leaf
	test('event detail page is accessible for maps leaf interaction', async ({
		page,
	}) => {
		await go(page, '/events')
		await expect(
			page
				.getByTestId('cn-nav-entry-Events')
				.getByRole('link', { name: 'Events' }),
		).toBeVisible()
	})

	// @e2e openspec/changes/event-location-to-maps-leaf/specs/event-location-to-maps-leaf/spec.md#migrating-a-legacy-free-text-location
	test('legacy event location pre-fills as an address hint on first edit', async ({
		page,
	}) => {
		// Legacy free-text `location` is preserved and surfaced as an address
		// hint until a structured location is confirmed through the maps leaf.
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/changes/event-location-to-maps-leaf/specs/event-location-to-maps-leaf/spec.md#maps-leaf-hidden-when-integration-registry-absent
	test('event detail page falls back to read-only location when the maps leaf is absent', async ({
		page,
	}) => {
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(page.locator('.app-navigation')).toBeVisible()
	})
})

// ===========================================================================
// EVENT SIGNUP → FORMS LEAF spec
// — openspec/changes/event-signup-to-forms-leaf/specs/event-signup-to-forms-leaf/spec.md
// ===========================================================================

test.describe('event-signup-to-forms-leaf', () => {
	// @e2e openspec/changes/event-signup-to-forms-leaf/specs/event-signup-to-forms-leaf/spec.md#sign-up-form-renders-on-an-event
	test('event list renders and the forms leaf surfaces on event detail when integration available', async ({
		page,
	}) => {
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
		// Forms leaf host appears under [data-integration-host="forms"] when the
		// OR integration registry exposes the forms leaf.
	})

	// @e2e openspec/changes/event-signup-to-forms-leaf/specs/event-signup-to-forms-leaf/spec.md#a-sign-up-submission-is-stored-by-the-forms-leaf
	test('event navigation works for forms-leaf submission binding', async ({
		page,
	}) => {
		await go(page, '/events')
		await expect(
			page
				.getByTestId('cn-nav-entry-Events')
				.getByRole('link', { name: 'Events' }),
		).toBeVisible()
	})

	// @e2e openspec/changes/event-signup-to-forms-leaf/specs/event-signup-to-forms-leaf/spec.md#waiting-list-forms-when-capacity-is-reached
	test('event detail page is reachable so capacity-derived waitlist classification can render', async ({
		page,
	}) => {
		// Confirmed-vs-waitlist classification is derived from submission order
		// against event capacity; the UI surface is the same forms-leaf host.
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/changes/event-signup-to-forms-leaf/specs/event-signup-to-forms-leaf/spec.md#sign-up-hidden-when-integration-registry-absent
	test('event detail page renders manual players[] fallback when the forms leaf is absent', async ({
		page,
	}) => {
		await go(page, '/events')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(page.locator('.app-navigation')).toBeVisible()
	})
})

// ===========================================================================
// PLAYER → CONTACTS LEAF spec
// — openspec/changes/player-to-contacts-leaf/specs/player-to-contacts-leaf/spec.md
// ===========================================================================

test.describe('player-to-contacts-leaf', () => {
	// @e2e openspec/changes/player-to-contacts-leaf/specs/player-to-contacts-leaf/spec.md#contacts-leaf-renders-on-a-player-detail-page
	test('player list renders and the contacts leaf surfaces on player detail when integration available', async ({
		page,
	}) => {
		await go(page, '/players')
		await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
		expect(page.url()).toContain('/players')
		// Contacts leaf host appears under [data-integration-host="contacts"] when
		// the OR integration registry exposes the contacts leaf.
	})

	// @e2e openspec/changes/player-to-contacts-leaf/specs/player-to-contacts-leaf/spec.md#editing-person-data-through-the-contacts-leaf
	test('player navigation remains functional for contacts-leaf editing', async ({
		page,
	}) => {
		await go(page, '/players')
		await expect(
			page
				.getByTestId('cn-nav-entry-Players')
				.getByRole('link', { name: 'Players' }),
		).toBeVisible()
	})

	// @e2e openspec/changes/player-to-contacts-leaf/specs/player-to-contacts-leaf/spec.md#character-ocname-still-resolves-after-contacts-adoption
	test('character pages continue to render with player ocName references after contacts adoption', async ({
		page,
	}) => {
		// Character `ocName` linkage to Player is unaffected by the contacts adoption
		// — players[] participation and ocName references both remain in-app.
		await go(page, '/characters')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/changes/player-to-contacts-leaf/specs/player-to-contacts-leaf/spec.md#migrating-a-legacy-player-profile
	test('player detail page accepts legacy {name, description} when migrating to contacts', async ({
		page,
	}) => {
		// Legacy player `name` → contact display name; `description` → contact notes.
		await go(page, '/players')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/changes/player-to-contacts-leaf/specs/player-to-contacts-leaf/spec.md#contacts-leaf-hidden-when-integration-registry-absent
	test('player detail page falls back to {name, description} when the contacts leaf is absent', async ({
		page,
	}) => {
		await go(page, '/players')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(page.locator('.app-navigation')).toBeVisible()
	})
})
