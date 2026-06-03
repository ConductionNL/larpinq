/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Documentation screenshot capture suite — larpingapp.
 *
 * This spec is *not* a regression test — it drives the Larping UI
 * through every flow documented under `docs/tutorials/{user,admin}/*.md`
 * and writes a fresh PNG into `docs/static/screenshots/tutorials/<track>/`
 * for each step the markdown references.
 *
 * Run manually whenever the UI changes and tutorial screenshots need
 * to be refreshed:
 *
 *     NEXTCLOUD_URL=http://localhost:8080 \
 *       npx playwright test --project docs-capture
 *
 * Excluded from the default `npm run test:e2e` run via the
 * `docs-capture` project flag in `playwright.config.ts` so PR
 * pipelines don't reshoot screenshots on every push.
 *
 * Authentication: `playwright.config.ts` wires `globalSetup` (a one-time
 * Nextcloud login → storage state) and `use.storageState`, so the
 * `page` fixture here arrives already signed in.
 *
 * Selector convention: capture-relevant surfaces in the app source
 * carry `data-testid="<scope>-<element>"` attributes. Add a story by
 * appending a new `test(...)` block — see `journeydoc-add-story`.
 *
 * Data dependency: Larping is not yet installed in the dev container at
 * the time of writing — these tests are scaffolded for a future capture
 * run. Selector misses are the expected first-run failure mode (UI markup
 * drifts faster than docs); failures land per-test in `test-results/`
 * rather than killing the suite. The tutorial markdown is the source of
 * truth for what each step should show.
 *
 * Pattern reference: ADR-030 (hydra/openspec/architecture/).
 */

import { test, expect, type Page } from '@playwright/test'
import * as path from 'path'
import * as fs from 'fs'

const SHOT_ROOT = path.resolve(__dirname, '..', '..', 'docs', 'static', 'screenshots', 'tutorials')
const APP = '/apps/larpingapp'

/**
 * Save a viewport screenshot under
 * `docs/static/screenshots/tutorials/<track>/<file>`.
 * Lives under `static/` so Docusaurus copies the PNG into the build
 * root — markdown image refs use `/screenshots/...` (root-absolute).
 */
async function shoot(page: Page, track: 'user' | 'admin', file: string): Promise<void> {
	const dir = path.join(SHOT_ROOT, track)
	if (!fs.existsSync(dir)) {
		fs.mkdirSync(dir, { recursive: true })
	}
	await page.screenshot({ path: path.join(dir, file), fullPage: false, type: 'png' })
}

/**
 * Dismiss anything that overlays the app chrome before we try to click —
 * chiefly Nextcloud's first-run wizard modal, but also any leftover
 * dialog. Best-effort: silently no-op when nothing's there.
 */
async function dismissOverlays(page: Page): Promise<void> {
	const wizard = page.locator('#firstrunwizard')
	if (await wizard.isVisible().catch(() => false)) {
		const close = wizard.getByRole('button', { name: /close|got it|finish|skip/i }).first()
		if (await close.isVisible().catch(() => false)) {
			await close.click().catch(() => {})
		} else {
			await page.keyboard.press('Escape').catch(() => {})
		}
		await wizard.waitFor({ state: 'hidden', timeout: 4000 }).catch(() => {})
	}
	const stray = page.locator('[role="dialog"]:not(#firstrunwizard)')
	if (await stray.first().isVisible().catch(() => false)) {
		await page.keyboard.press('Escape').catch(() => {})
		await page.waitForTimeout(300)
	}
}

/**
 * Navigate to an app route (relative paths join /apps/larpingapp) or to
 * an absolute Nextcloud route (paths starting with `/apps/` or
 * `/settings` are passed through). Settles network + dismisses overlays.
 *
 * Larping's Vue router uses hash routing, so in-app routes need a
 * `#/…` prefix. The helper inserts that for relative routes.
 */
async function go(page: Page, route: string): Promise<void> {
	let url: string
	if (route.startsWith('/apps/') || route.startsWith('/settings')) {
		url = route
	} else {
		const hashRoute = route.startsWith('/') ? route : `/${route}`
		url = `${APP}/#${hashRoute}`
	}
	await page.goto(url).catch(() => { /* tolerate 404 — caller decides */ })
	await page.waitForLoadState('networkidle').catch(() => { /* idle never fires on some pages */ })
	await dismissOverlays(page)
	await page.waitForTimeout(900)
}

/**
 * Open the create dialog on a list view ("Add Item") if the button is
 * present, screenshot it, and close it again. Returns whether the dialog
 * appeared (it does on every list view; the dialog body is empty unless
 * the relevant schema is mapped).
 */
async function captureCreateDialog(page: Page, track: 'user' | 'admin', file: string): Promise<boolean> {
	const addBtn = page.getByRole('button', { name: /Add Item/i }).first()
	if (!(await addBtn.isVisible().catch(() => false))) {
		return false
	}
	await addBtn.click().catch(() => {})
	const dialog = page.locator('[role="dialog"]:not(#firstrunwizard)').first()
	await dialog.waitFor({ state: 'visible', timeout: 5000 }).catch(() => { /* no dialog */ })
	await page.waitForTimeout(400)
	await shoot(page, track, file)
	const cancel = dialog.getByRole('button', { name: /Cancel/i }).first()
	if (await cancel.isVisible().catch(() => false)) {
		await cancel.click().catch(() => {})
	} else {
		await page.keyboard.press('Escape').catch(() => {})
	}
	await page.waitForTimeout(300)
	return true
}

test.describe.configure({ mode: 'default' })

test.beforeEach(async ({ page }) => {
	page.setViewportSize({ width: 1280, height: 800 })
})

// ---------------------------------------------------------------------------
// USER TRACK — see docs/tutorials/user/
// ---------------------------------------------------------------------------

test.describe('docs: user track', () => {
	test('UN first-launch', async ({ page }) => {
		// docs/tutorials/user/01-first-launch.md
		await go(page, '/')
		await shoot(page, 'user', '01-first-launch-01.png')
		await shoot(page, 'user', '01-first-launch-02.png')
		await shoot(page, 'user', '01-first-launch-03.png')
		await go(page, '/characters')
		await shoot(page, 'user', '01-first-launch-04.png')
		expect(page.url()).toContain('/apps/larpingapp')
	})

	test('UN create-character', async ({ page }) => {
		// docs/tutorials/user/02-create-character.md
		await go(page, '/characters')
		const had = await captureCreateDialog(page, 'user', '02-create-character-01.png')
		if (had) {
			await captureCreateDialog(page, 'user', '02-create-character-02.png')
		}
		// Steps 3-4 (detail page, approval flag) need an existing character;
		// the list stands in.
		await go(page, '/characters')
		await shoot(page, 'user', '02-create-character-03.png')
		await shoot(page, 'user', '02-create-character-04.png')
	})

	test('UN character-skills', async ({ page }) => {
		// docs/tutorials/user/03-character-skills.md
		await go(page, '/characters')
		await shoot(page, 'user', '03-character-skills-01.png')
		await go(page, '/skills')
		await shoot(page, 'user', '03-character-skills-02.png')
		await shoot(page, 'user', '03-character-skills-03.png')
		await go(page, '/abilities')
		await shoot(page, 'user', '03-character-skills-04.png')
		await go(page, '/skills')
		await shoot(page, 'user', '03-character-skills-05.png')
	})

	test('UN manage-items', async ({ page }) => {
		// docs/tutorials/user/04-manage-items.md
		await go(page, '/items')
		const had = await captureCreateDialog(page, 'user', '04-manage-items-01.png')
		if (had) {
			await captureCreateDialog(page, 'user', '04-manage-items-02.png')
		}
		await go(page, '/characters')
		await shoot(page, 'user', '04-manage-items-03.png')
		await go(page, '/items')
		await shoot(page, 'user', '04-manage-items-04.png')
	})

	test('UN conditions', async ({ page }) => {
		// docs/tutorials/user/05-conditions.md
		await go(page, '/conditions')
		await shoot(page, 'user', '05-conditions-01.png')
		await shoot(page, 'user', '05-conditions-02.png')
		await go(page, '/characters')
		await shoot(page, 'user', '05-conditions-03.png')
		await go(page, '/abilities')
		await shoot(page, 'user', '05-conditions-04.png')
		await go(page, '/characters')
		await shoot(page, 'user', '05-conditions-05.png')
	})

	test('UN event-subscription', async ({ page }) => {
		// docs/tutorials/user/06-event-subscription.md
		await go(page, '/events')
		await shoot(page, 'user', '06-event-subscription-01.png')
		await shoot(page, 'user', '06-event-subscription-02.png')
		const had = await captureCreateDialog(page, 'user', '06-event-subscription-03.png')
		if (!had) {
			await shoot(page, 'user', '06-event-subscription-03.png')
		}
		await go(page, '/events')
		await shoot(page, 'user', '06-event-subscription-04.png')
		await go(page, '/characters')
		await shoot(page, 'user', '06-event-subscription-05.png')
	})

	test('UN export-character-sheet', async ({ page }) => {
		// docs/tutorials/user/07-export-character-sheet.md — PDF export
		// requires DocuDesk; without it the action is hidden. The character
		// list / detail screens stand in for steps 1-4.
		await go(page, '/characters')
		await shoot(page, 'user', '07-export-character-sheet-01.png')
		await shoot(page, 'user', '07-export-character-sheet-02.png')
		await shoot(page, 'user', '07-export-character-sheet-03.png')
		await shoot(page, 'user', '07-export-character-sheet-04.png')
	})

	test('UN track-xp', async ({ page }) => {
		// docs/tutorials/user/08-track-xp.md
		await go(page, '/characters')
		await shoot(page, 'user', '08-track-xp-01.png')
		await go(page, '/events')
		await shoot(page, 'user', '08-track-xp-02.png')
		await go(page, '/skills')
		await shoot(page, 'user', '08-track-xp-03.png')
		await go(page, '/abilities')
		await shoot(page, 'user', '08-track-xp-04.png')
		await go(page, '/characters')
		await shoot(page, 'user', '08-track-xp-05.png')
	})
})

// ---------------------------------------------------------------------------
// ADMIN TRACK — see docs/tutorials/admin/
// ---------------------------------------------------------------------------

test.describe('docs: admin track', () => {
	test('AN configure-game-system', async ({ page }) => {
		// docs/tutorials/admin/01-configure-game-system.md
		await go(page, '/abilities')
		await shoot(page, 'admin', '01-configure-game-system-01.png')
		await go(page, '/effects')
		await shoot(page, 'admin', '01-configure-game-system-02.png')
		await go(page, '/skills')
		await shoot(page, 'admin', '01-configure-game-system-03.png')
		await go(page, '/items')
		await shoot(page, 'admin', '01-configure-game-system-04.png')
		await go(page, '/events')
		await shoot(page, 'admin', '01-configure-game-system-05.png')
	})

	test('AN approve-players', async ({ page }) => {
		// docs/tutorials/admin/02-approve-players.md
		await go(page, '/players')
		await shoot(page, 'admin', '02-approve-players-01.png')
		await go(page, '/characters')
		await shoot(page, 'admin', '02-approve-players-02.png')
		await shoot(page, 'admin', '02-approve-players-03.png')
		await shoot(page, 'admin', '02-approve-players-04.png')
		await go(page, '/events')
		await shoot(page, 'admin', '02-approve-players-05.png')
	})

	test('AN admin-settings', async ({ page }) => {
		// docs/tutorials/admin/03-admin-settings.md — settings live under
		// /settings/admin/larpingapp in the Nextcloud administration panel.
		await go(page, '/settings/admin/larpingapp')
		await shoot(page, 'admin', '03-admin-settings-01.png')
		await page.evaluate(() => window.scrollTo(0, 0))
		await page.waitForTimeout(300)
		await shoot(page, 'admin', '03-admin-settings-02.png')
		await shoot(page, 'admin', '03-admin-settings-03.png')
		await shoot(page, 'admin', '03-admin-settings-04.png')
		await go(page, '/')
		await shoot(page, 'admin', '03-admin-settings-05.png')
	})
})
